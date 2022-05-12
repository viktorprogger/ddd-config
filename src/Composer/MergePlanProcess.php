<?php

declare(strict_types=1);

namespace Viktorprogger\DDD\Config\Composer;

use Composer\Composer;
use Composer\Package\PackageInterface;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;
use Viktorprogger\DDD\Config\MergePlan;
use Viktorprogger\DDD\Config\Options;

use Yiisoft\VarDumper\VarDumper;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_file;
use function ksort;
use function strtr;
use function substr;

/**
 * @internal
 */
final class MergePlanProcess
{
    private MergePlan $mergePlan;
    private ProcessHelper $helper;

    /**
     * @param Composer $composer The composer instance.
     */
    public function __construct(Composer $composer)
    {
        $this->mergePlan = new MergePlan();
        $this->helper = new ProcessHelper($composer);

        if (!$this->helper->shouldBuildMergePlan()) {
            return;
        }

        $modules = $this->helper->getModules();
        $configMap = $this->createConfigMap($modules, $this->helper->getRootModule());
        foreach ($configMap as $moduleConfig) {
            $this->addPackagesConfigsToMergePlan($moduleConfig);
        }

        $this->updateMergePlan();
    }

    private function addPackagesConfigsToMergePlan(array $moduleConfig): void
    {
        if (isset($moduleConfig['package'])) {
            $this->addPackageToMergePlan($moduleConfig);
        } else {
            $this->addLocalModuleToMergePlan($moduleConfig);
        }
    }

    private function addLocalModuleToMergePlan(array $moduleConfig): void
    {
        $defaultEnvironment = [Options::DEFAULT_ENVIRONMENT => $this->helper->getRootPackageConfig()];
        $environments = array_merge($this->helper->getEnvironmentConfig(), $defaultEnvironment);
        foreach ($environments as $environment => $groups) {
            if ($groups === []) {
                $this->mergePlan->addEnvironmentWithoutConfigs($environment);
            } else {
                foreach ($groups as $group => $files) {
                    $this->mergePlan->addMultiple(
                        array_map(
                            static fn(string $file) => implode(
                                '/',
                                array_filter([$moduleConfig['path'], $moduleConfig['configDirectory'], $file])
                            ),
                            (array) $files
                        ),
                        Options::ROOT_PACKAGE_NAME,
                        $group,
                        $moduleConfig['module'],
                        $environment,
                    );
                }
            }
        }
    }

    private function addPackageToMergePlan(array $moduleConfig): void
    {
        $package = $this->helper->getPackages()[$moduleConfig['package']]
            ?? throw new RuntimeException("Package \"{$moduleConfig['package']}\" is not installed");
        $options = new Options($package->getExtra());

        foreach ($this->helper->getPackageConfig($package) as $group => $files) {
            $files = (array) $files;

            foreach ($files as $file) {
                $isOptional = false;

                if (Options::isOptional($file)) {
                    $isOptional = true;
                    $file = substr($file, 1);
                }

                if (Options::isVariable($file)) {
                    $this->mergePlan->add($file, $moduleConfig['package'], $group, $moduleConfig['module']);
                    continue;
                }

                $absoluteFilePath = $this->helper->getAbsolutePackageFilePath($package, $options, $file);

                if (Options::containsWildcard($file)) {
                    $matches = glob($absoluteFilePath);

                    if (empty($matches)) {
                        continue;
                    }

                    foreach ($matches as $match) {
                        $this->mergePlan->add(
                            $this->normalizePackageFilePath($package, $match, $moduleConfig['module'] === Options::VENDOR_OVERRIDE_PACKAGE_NAME),
                            $moduleConfig['package'],
                            $group,
                            $moduleConfig['module']
                        );
                    }

                    continue;
                }

                if ($isOptional && !is_file($absoluteFilePath)) {
                    continue;
                }

                $this->mergePlan->add(
                    $this->normalizePackageFilePath($package, $absoluteFilePath, $moduleConfig['module'] === Options::VENDOR_OVERRIDE_PACKAGE_NAME),
                    $moduleConfig['package'],
                    $group,
                    $moduleConfig['module'],
                );
            }
        }
    }

    private function updateMergePlan(): void
    {
        $mergePlan = $this->mergePlan->toArray();
        ksort($mergePlan);

        $filePath = $this->helper->getPaths()->absolute(Options::MERGE_PLAN_FILENAME);
        $oldContent = is_file($filePath) ? file_get_contents($filePath) : '';

        $content = '<?php'
            . "\n\ndeclare(strict_types=1);"
            . "\n\n// Do not edit. Content will be replaced."
            . "\nreturn " . VarDumper::create($mergePlan)->export() . ";\n";

        if ($this->normalizeLineEndings($oldContent) !== $this->normalizeLineEndings($content)) {
            file_put_contents($filePath, $content, LOCK_EX);
        }
    }

    private function normalizeLineEndings(string $value): string
    {
        return strtr($value, [
            "\r\n" => "\n",
            "\r" => "\n",
        ]);
    }

    private function normalizePackageFilePath(
        PackageInterface $package,
        string $absoluteFilePath,
        bool $isVendorOverrideLayer
    ): string {
        if ($isVendorOverrideLayer) {
            return $this->helper->getRelativePackageFilePathWithPackageName($package, $absoluteFilePath);
        }

        return $this->helper->getRelativePackageFilePath($package, $absoluteFilePath);
    }

    /**
     * @param array $modules
     * @param string $root
     *
     * @return array[]
     * @psalm-return array<int, array{
     *         package: string,
     *         module: string,
     *         path: string|null,
     *         configDirectory: string|null,
     *         parent: string|null}>
     */
    private function createConfigMap(array $modules, string $root): array
    {
        $configNew = [];
        $packagesExcluded = [];
        foreach ($modules as $title => $config) {
            if (isset($config['path'], $config['package'])) {
                throw new RuntimeException(
                    "Module \"$title\" configuration contains both \"path\" and \"package\" keys."
                );
            }

            if (isset($config['path'])) {
                $configNew[] = [
                    'module' => $title,
                    'path' => $config['path'],
                    'configDirectory' => $config['config-directory'] ?? null,
                    'parent' => $title === $root ? Options::VENDOR_PACKAGE_NAME : $config['parent'] ?? null,
                ];
            } elseif (isset($config['package'])) {
                $packagesExcluded[$config['package']] = true;

                $configNew[] = [
                    'package' => $config['package'],
                    'module' => $title,
                    'configDirectory' => $config['config-directory'] ?? null,
                    'parent' => $config['parent'] ?? $root,
                ];
            } else {
                throw new RuntimeException('Module config must have either "path" or "package" key');
            }
        }

        $vendor = [];
        foreach ($this->helper->getVendorPackages() as $name => $package) {
            if (!isset($packagesExcluded[$name])) {
                $vendor[] = [
                    'package' => $name,
                    'module' => Options::VENDOR_PACKAGE_NAME,
                ];
            }
        }

        $vendorOverride = [];
        foreach ($this->helper->getVendorOverridePackages() as $name => $package) {
            if (isset($packagesExcluded[$name])) {
                throw new RuntimeException("Package $name is defined in both vendor-override and module sections");
            }

            $vendorOverride[] = [
                'package' => $name,
                'module' => Options::VENDOR_OVERRIDE_PACKAGE_NAME,
            ];
        }

        return [...$vendor, ...$vendorOverride, ...$configNew];
    }
}

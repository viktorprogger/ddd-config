<?php

namespace Viktorprogger\DDD\Config\Tests\Command;

use Composer\Composer;
use Composer\Config;
use Composer\Console\Application;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Viktorprogger\DDD\Config\RebuildCommand;

class RebuildTest extends TestCase
{
    private Filesystem $filesystem;
    private string $sourceDirectory;
    private string $tempDirectory;
    private string $tempConfigsDirectory;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->filesystem = new Filesystem();
        $this->sourceDirectory = dirname(__DIR__) . '/TestAsset/packages';
        $this->tempDirectory = sys_get_temp_dir() . '/yiisoft';
        $this->tempConfigsDirectory = "$this->tempDirectory/config";
    }

    public function testMergePlan(): void
    {
        $command = new RebuildCommand();
        $command->setComposer($this->createComposerMock());
        $command->setApplication($this->createMock(Application::class));
        $command->setIO($this->getMockBuilder(IOInterface::class)->getMockForAbstractClass());
        (new CommandTester($command))->execute([]);
    }

    protected function createComposerMock(
        array $extraEnvironments = [],
        array $vendorOverridePackage = null,
        bool $buildMergePlan = true,
        string $extraConfigFile = null
    ) {
        $rootPath = $this->tempDirectory;
        $sourcePath = $this->sourceDirectory;
        $targetPath = "$this->tempDirectory/vendor";

        $extra = array_merge(
            [
                'config-plugin-file' => $extraConfigFile,
                'config-plugin-options' => [
                    'source-directory' => 'config',
                    'vendor-override-layer' => $vendorOverridePackage ?? 'test/over',
                    'build-merge-plan' => $buildMergePlan,
                    'modules' => [
                        'main' => ['path' => ''],
                    ],
                    'module-root' => 'main',
                ],
                'config-plugin' => [
                    'empty' => [],
                    'common' => 'common/*.php',
                    'params' => [
                        'params.php',
                        '?params-local.php',
                    ],
                    'web' => [
                        '$common',
                        'web.php',
                    ],
                ],
            ],
            ['config-plugin-environments' => $extraEnvironments]
        );

        $config = $this->createMock(Config::class);
        $config->method('get')->willReturn(dirname(__DIR__, 2) . '/vendor');

        $rootPackage = $this->getMockBuilder(RootPackageInterface::class)
            ->onlyMethods(['getRequires', 'getDevRequires', 'getExtra'])
            ->getMockForAbstractClass();
        $rootPackage->method('getRequires')->willReturn(
            [
                'test/a' => new Link(
                    "$sourcePath/a",
                    "$targetPath/test/a",
                    new Constraint('>=', '1.0.0')
                ),
                'test/ba' => new Link(
                    "$sourcePath/ba",
                    "$targetPath/test/ba",
                    new Constraint('>=', '1.0.0')
                ),
                'test/c' => new Link(
                    "$sourcePath/c",
                    "$targetPath/test/c",
                    new Constraint('>=', '1.0.0')
                ),
                'test/custom-source' => new Link(
                    "$sourcePath/custom-source",
                    "$targetPath/test/custom-source",
                    new Constraint('>=', '1.0.0')
                ),
                'test/over' => new Link(
                    "$sourcePath/over",
                    "$targetPath/test/over",
                    new Constraint('>=', '1.0.0')
                ),
            ]
        );
        $rootPackage->method('getDevRequires')->willReturn(
            [
                'test/d-dev-c' => new Link(
                    "$sourcePath/d-dev-c",
                    "$targetPath/test/d-dev-c",
                    new Constraint('>=', '1.0.0')
                ),
            ]
        );
        $rootPackage->method('getExtra')->willReturn($extra);

        $packages = [
            new CompletePackage('test/a', '1.0.0', '1.0.0'),
            new CompletePackage('test/ba', '1.0.0', '1.0.0'),
            new CompletePackage('test/c', '1.0.0', '1.0.0'),
            new CompletePackage('test/custom-source', '1.0.0', '1.0.0'),
            new CompletePackage('test/d-dev-c', '1.0.0', '1.0.0'),
            new CompletePackage('test/over', '1.0.0', '1.0.0'),
            new Package('test/e', '1.0.0', '1.0.0'),
        ];

        foreach ($packages as $package) {
            $path = str_replace('test/', '', "$sourcePath/{$package->getName()}") . '/composer.json';
            $package->setExtra(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['extra']);
        }

        $repository = $this->getMockBuilder(InstalledRepositoryInterface::class)
            ->onlyMethods(['getPackages'])
            ->getMockForAbstractClass();
        $repository->method('getPackages')->willReturn($packages);

        $repositoryManager = $this->getMockBuilder(RepositoryManager::class)
            ->onlyMethods(['getLocalRepository'])
            ->disableOriginalConstructor()
            ->getMock();
        $repositoryManager->method('getLocalRepository')->willReturn($repository);

        $installationManager = $this->getMockBuilder(InstallationManager::class)
            ->onlyMethods(['getInstallPath'])
            ->disableOriginalConstructor()
            ->getMock();
        $installationManager->method('getInstallPath')->willReturnCallback(
            static function (PackageInterface $package) use ($sourcePath, $rootPath) {
                if ($package instanceof RootPackageInterface) {
                    return $rootPath;
                }

                return str_replace('test/', '', "$sourcePath/{$package->getName()}");
            }
        );

        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->onlyMethods(['dispatch'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventDispatcher->method('dispatch')->willReturn(0);

        $composer = $this->getMockBuilder(Composer::class)
            ->onlyMethods(
                [
                    'getConfig',
                    'getPackage',
                    'getRepositoryManager',
                    'getInstallationManager',
                    'getEventDispatcher',
                ]
            )
            ->getMock();

        $composer->method('getConfig')->willReturn($config);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->method('getRepositoryManager')->willReturn($repositoryManager);
        $composer->method('getInstallationManager')->willReturn($installationManager);
        $composer->method('getEventDispatcher')->willReturn($eventDispatcher);

        return $composer;
    }
}

<?php

declare(strict_types=1);

namespace Viktorprogger\DDD\Config;

use InvalidArgumentException;

use function is_array;
use function str_replace;
use function strpos;
use function trim;

/**
 * @internal
 */
final class Options
{
    public const MERGE_PLAN_FILENAME = '.merge-plan.php';
    public const DEFAULT_CONFIG_DIRECTORY = 'config';
    public const DEFAULT_VENDOR_DIRECTORY = 'vendor';
    public const DEFAULT_ENVIRONMENT = '/';
    public const ROOT_PACKAGE_NAME = '/';
    public const VENDOR_OVERRIDE_PACKAGE_NAME = '//';
    public const VENDOR_PACKAGE_NAME = 'vendor';

    private bool $buildMergePlan = true;
    private array $vendorOverrideLayerPackages = [];
    private string $sourceDirectory = self::DEFAULT_CONFIG_DIRECTORY;
    private array $modules;
    private string $moduleRoot;

    public function __construct(array $extra, bool $root = false)
    {
        if (!isset($extra['config-plugin-options']) || !is_array($extra['config-plugin-options'])) {
            return;
        }

        $options = $extra['config-plugin-options'];

        if ($root) {
            $this->modules = $options['modules']
                ?? throw new InvalidArgumentException(
                    'Module list must be set in the "config-plugin-options" section of the composer.json'
                );
            $this->moduleRoot = $options['module-root'] ?? throw new InvalidArgumentException(
                    'Module root name must be set in the "config-plugin-options" section of the composer.json'
                );
            if (!isset($this->modules[$this->moduleRoot])) {
                throw new InvalidArgumentException(
                    "Root module \"$this->moduleRoot\" does not present in configuration"
                );
            }
        }

        if (isset($options['build-merge-plan'])) {
            $this->buildMergePlan = (bool) $options['build-merge-plan'];
        }

        if (isset($options['vendor-override-layer'])) {
            $this->vendorOverrideLayerPackages = (array) $options['vendor-override-layer'];
        }

        if (isset($options['source-directory'])) {
            $this->sourceDirectory = $this->normalizePath((string) $options['source-directory']);
        }
    }

    public static function containsWildcard(string $file): bool
    {
        return strpos($file, '*') !== false;
    }

    public function getModuleRootName(): string
    {
        return $this->moduleRoot;
    }

    public static function isOptional(string $file): bool
    {
        return strpos($file, '?') === 0;
    }

    public static function isVariable(string $file): bool
    {
        return strpos($file, '$') === 0;
    }

    public function buildMergePlan(): bool
    {
        return $this->buildMergePlan;
    }

    public function vendorOverrideLayerPackages(): array
    {
        return $this->vendorOverrideLayerPackages;
    }

    public function sourceDirectory(): string
    {
        return $this->sourceDirectory;
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    private function normalizePath(string $value): string
    {
        return trim(str_replace('\\', '/', $value), '/');
    }
}

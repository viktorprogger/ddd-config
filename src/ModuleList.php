<?php

declare(strict_types=1);

namespace Viktorprogger\DDD\Config;

use Yiisoft\Config\Config;
use Yiisoft\Config\ConfigPaths;

final class ModuleList
{
    public function __construct(private readonly string $rootPath, private readonly array $modules)
    {
    }

    public function getModuleConfiguration(string $module): Config
    {
        $modulePath = $this->getModulePath($module);
        return new Config(
            new ConfigPaths(
                "$this->rootPath/$modulePath",
                $this->modules[$module]['configDirectory'] ?? 'config',
            )
        );
    }

    private function getModulePath(string $module): string
    {

    }
}

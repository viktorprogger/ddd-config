<?php

namespace Viktorprogger\DDD\Config;

final class PackageConfiguration
{
    private array $configuration = [];

    public function __construct(array $configuration, string $configurationDirectoryPath)
    {
        /**
         * @var string $group
         * @var string|string[] $value
         */
        foreach ($configuration as $group => $value) {
            $this->configuration[$group] = (array) $value;
            foreach ((array) $value as $item) {
                $item = new PackageConfigurationItem($item, $configurationDirectoryPath);
                if ($item->hasWildcard()) {
                    foreach (glob($item->getFilePath()) as $subItem) {
                        $this->configuration[$group] = new PackageConfigurationItem(
                            substr($subItem, mb_strlen($configurationDirectoryPath)),
                            $configurationDirectoryPath,
                        );
                    }
                } else {
                    $this->configuration[$group][] = $item;
                }
            }
        }
    }

    /**
     * @param string $group
     *
     * @return PackageConfigurationItem[]
     */
    public function getGroupFiles(string $group): array
    {
        return $this->configuration[$group] ?? [];
    }
}

<?php

namespace Viktorprogger\DDD\Config;

use Composer\Composer;
use ErrorException;
use RuntimeException;
use Yiisoft\Arrays\ArrayHelper;


final class ConfigurationYiiConfig
{
    /** @var string[][]|string[] */
    private array $rootPackageConfig;
    private array $moduleConfigurations;
    private array $building = [];
    private array $params = [];
    private array $cacheKeys = [];

    public function __construct(Composer $composer, private string $paramsGroupName = 'params', array ...$configurations)
    {
        $this->moduleConfigurations = $configurations;

        /** @psalm-suppress MixedAssignment */
        $this->rootPackageConfig = $composer->getPackage()->getExtra()['config-plugin'] ?? [];

        /** @var string|string[] $value */
        foreach ($this->rootPackageConfig as &$value) {
            $value = (array) $value;
        }
        unset($value);
    }

    /**
     * Возвращает конфиг для {@see Psr4ConfigurationCollection}
     * TODO Добавить vendor
     * TODO Добавить окружения
     *
     * @param string $group
     *
     * @return array
     */
    public function getModuleConfiguration(string $group): array
    {
        if (isset($this->rootPackageConfig[$group])) {
            foreach ($this->moduleConfigurations as &$configuration) {
                if ($group !== $this->paramsGroupName) {
                    $this->params[$configuration['id']] = $this->getModuleDefinitions($configuration, $this->paramsGroupName);
                }

                $configuration['definitions'] = $this->getModuleDefinitions($configuration, $group);
            }
        }

        return [];
    }

    private function getModuleDefinitions(array $configuration, string $group): array
    {
        if (isset($this->building[$configuration['id']][$group])) {
            throw new RuntimeException("$group is already building"); // FIXME make a more concrete exception
        }
        $this->building[$configuration['id']][$group] = true;
        $result = [];

        /** @var string[] $fileList */
        $fileList = $this->rootPackageConfig[$group];
        foreach ($fileList as $item) {
            if ($this->isVariable($item)) {
                $parentGroup = substr($item, 1);
                $definitions = $this->getModuleDefinitions($configuration, $parentGroup);

                $result = $this->merge(
                    $result,
                    $definitions,
                    [],
                    false,
                    false,
                );
            } else {
                if ($this->isOptional($item)) {
                    $item = substr($item, 1);
                }

                $filePath = "{$configuration['config_dir']}/$item";
                $files = $this->containsWildcard($filePath) ? glob($filePath) : [$filePath];

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $definitions = $this->getFileDefinitions(
                            $file,
                            isset($this->building[$configuration['id']][$this->paramsGroupName])
                                ? []
                                : $this->params[$configuration['id']]
                        );

                        $result = $this->merge(
                            $result,
                            $definitions,
                            [],
                            false,
                            false,
                        );
                    }
                }
            }
        }

        unset($this->building[$configuration['id']][$group]);

        return $result;
    }

    private function containsWildcard(string $file): bool
    {
        return strpos($file, '*') !== false;
    }

    private function isOptional(string $file): bool
    {
        return strpos($file, '?') === 0;
    }

    private function isVariable(string $file): bool
    {
        return strpos($file, '$') === 0;
    }

    private function merge(
        array $arrayA,
        array $arrayB,
        array $recursiveKeyPath,
        bool $isRecursiveMerge,
        bool $isReverseMerge
    ): array {
        $result = $arrayA;

        /** @psalm-var mixed $value */
        foreach ($arrayB as $key => $value) {
            if (is_int($key)) {
                if (array_key_exists($key, $result) && $result[$key] !== $value) {
                    /** @var mixed */
                    $result[] = $value;
                } else {
                    /** @var mixed */
                    $result[$key] = $value;
                }
                continue;
            }

            $fullKeyPath = array_merge($recursiveKeyPath, [$key]);

            if (
                $isRecursiveMerge
                && is_array($value)
                && (
                    !array_key_exists($key, $result)
                    || is_array($result[$key])
                )
            ) {
                /** @var array $array */
                $array = $result[$key] ?? [];

                // TODO deal with modifier "remove key from vendor"
                $result[$key] = $this->merge($array, $value, $fullKeyPath, $isRecursiveMerge, $isReverseMerge);
                continue;
            }

            $existKey = array_key_exists($key, $result);

            if ($existKey && !$isReverseMerge) {
                /** @var string|null $file */
                $file = ArrayHelper::getValue(
                    $this->cacheKeys,
                    $fullKeyPath,
                );

                if ($file !== null) {
                    // TODO $this->throwDuplicateKeyErrorException($context->group(), $fullKeyPath, [$file, $context->file()]);
                }
            }

            if (!$isReverseMerge || !$existKey) {
                $result[$key] = $value;

                /* TODO
                $isSet = $this->setValue($context, $fullKeyPath, $result, $key, $value);

                if ($isSet && !$isReverseMerge && !$context->isVariable()) {
                    ArrayHelper::setValue(
                        $this->cacheKeys,
                        array_merge([$context->layer()], $fullKeyPath),
                        $context->file()
                    );
                }*/
            }
        }

        return $result;
    }

    private function getFileDefinitions(string $file, array $params): array
    {
        $scopeRequire = static function (): array {
            /** @psalm-suppress InvalidArgument, MissingClosureParamType */
            set_error_handler(static function (int $errorNumber, string $errorString, string $errorFile, int $errorLine) {
                throw new ErrorException($errorString, $errorNumber, 0, $errorFile, $errorLine);
            });

            /** @psalm-suppress MixedArgument */
            extract(func_get_arg(1), EXTR_SKIP);

            /**
             * @psalm-suppress UnresolvableInclude
             * @psalm-var array
             */
            $result = require func_get_arg(0);
            restore_error_handler();
            return $result;
        };

        $scope = [
            'config' => $this,
            'params' => $params,
        ];


        /** @psalm-suppress TooManyArguments */
        return $scopeRequire($file, $scope);
    }
}

<?php

namespace Viktorprogger\DDD\Config;

final class PackageConfigurationItem
{
    public function __construct(private string $value, private string $directoryPath)
    {
    }

    public function isOptional(): bool
    {
        return str_starts_with($this->value, '?');
    }

    public function isVariable(): bool
    {
        return str_starts_with($this->value, '$');
    }

    public function hasWildcard(): bool
    {
        return str_contains($this->value, '*');
    }

    public function getName(): string
    {
        return $this->isVariable() || $this->isOptional() ? substr($this->value, 1) : $this->value;
    }

    public function getFilePath(): string
    {
        return "$this->directoryPath/{$this->getName()}";
    }
}

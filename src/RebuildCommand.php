<?php

declare(strict_types=1);

namespace Viktorprogger\DDD\Config;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Viktorprogger\DDD\Config\Composer\MergePlanProcess;

/**
 * RebuildCommand crawls all the configuration files and updates the merge plan file.
 *
 * TODO register in composer, give it a try
 */
final class RebuildCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('yii-ddd-config-rebuild')
            ->setDescription('Crawls all the configuration files and updates the merge plan file.')
            ->setHelp('This command crawls all the configuration files and updates the merge plan file.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @psalm-suppress PossiblyNullArgument */
        new MergePlanProcess($this->getComposer());
        return 0;
    }
}

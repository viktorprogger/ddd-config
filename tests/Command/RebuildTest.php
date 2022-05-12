<?php

namespace Viktorprogger\DDD\Config\Tests\Command;

use Composer\Console\Application;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Viktorprogger\DDD\Config\RebuildCommand;
use Viktorprogger\DDD\Config\Tests\Composer\TestCase;

class RebuildTest extends TestCase
{
    public function testMergePlan(): void
    {
        $command = new RebuildCommand();
        $command->setComposer($this->createComposerMock());
        $command->setApplication($this->createMock(Application::class));
        $command->setIO($this->getMockBuilder(IOInterface::class)->getMockForAbstractClass());
        (new CommandTester($command))->execute([]);

        $this->assertMergePlan();
    }
}

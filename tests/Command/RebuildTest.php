<?php

namespace Viktorprogger\DDD\Config\Tests\Command;

use Composer\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Viktorprogger\DDD\Config\Options;
use Viktorprogger\DDD\Config\RebuildCommand;
use Viktorprogger\DDD\Config\Tests\Composer\TestCase;

class RebuildTest extends TestCase
{
    public function testMergePlan(): void
    {
        $this->executeCommand();
        $this->assertMergePlan();
    }

    public function testRebuildWithMergePlanChanges(): void
    {
        $this->executeCommand(
            [
                'alfa' => [
                    'params' => 'alfa/params.php',
                    'web' => 'alfa/web.php',
                    'main' => [
                        '$web',
                        'alfa/main.php',
                    ],
                ],
                'beta' => [
                    'params' => 'beta/params.php',
                    'web' => 'beta/web.php',
                    'main' => [
                        '$web',
                        'beta/main.php',
                    ],
                ],
            ]
        );

        $this->assertMergePlan(
            [
                'alfa' => [
                    'main' => [
                        'params' => [
                            Options::ROOT_PACKAGE_NAME => [
                                'alfa/params.php',
                            ],
                        ],
                        'web' => [
                            Options::ROOT_PACKAGE_NAME => [
                                'alfa/web.php',
                            ],
                        ],
                        'main' => [
                            Options::ROOT_PACKAGE_NAME => [
                                '$web',
                                'alfa/main.php',
                            ],
                        ],
                    ],
                ],
                'beta' => [
                    'main' => [
                        'params' => [
                            Options::ROOT_PACKAGE_NAME => [
                                'beta/params.php',
                            ],
                        ],
                        'web' => [
                            Options::ROOT_PACKAGE_NAME => [
                                'beta/web.php',
                            ],
                        ],
                        'main' => [
                            Options::ROOT_PACKAGE_NAME => [
                                '$web',
                                'beta/main.php',
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    private function executeCommand(array $extraEnvironments = []): void
    {
        $command = new RebuildCommand();
        $command->setComposer($this->createComposerMock($extraEnvironments));
        $command->setIO($this->createIoMock());
        $command->setApplication($this->createMock(Application::class));
        (new CommandTester($command))->execute([]);
    }
}

<?php

declare(strict_types=1);

namespace Viktorprogger\DDD\Config;

use ErrorException;
use JsonSchema\Exception\InvalidConfigException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Yiisoft\Config\Config;
use Yiisoft\Config\ConfigInterface;
use Yiisoft\Config\ConfigPaths;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Yii\Event\ListenerConfigurationChecker;
use Yiisoft\Yii\Runner\BootstrapRunner;
use Yiisoft\Yii\Runner\ConfigFactory;

final class ApplicationRunner
{
    protected bool $debug;
    protected string $rootPath;
    protected ?string $environment;
    protected ?ConfigInterface $config = null;
    protected ?ContainerInterface $container = null;
    protected ?string $bootstrapGroup = null;
    protected ?string $eventsGroup = null;

    /**
     * @param string $rootPath The absolute path to the project root.
     * @param bool $debug Whether the debug mode is enabled.
     * @param string|null $environment The environment name.
     */
    public function __construct(string $rootPath, bool $debug, ?string $environment)
    {
        $this->rootPath = $rootPath;
        $this->debug = $debug;
        $this->environment = $environment;
    }

    /**
     * {@inheritDoc}
     *
     * @throws CircularReferenceException|ErrorException|Exception|InvalidConfigException
     * @throws ContainerExceptionInterface|NotFoundException|NotFoundExceptionInterface|NotInstantiableException
     */
    public function run(): void
    {
        $config = $this->getConfig();
        $container = $this->getContainer($config, 'console');

        $this->runBootstrap($config, $container);
        $this->checkEvents($config, $container);

        /** @var Application $application */
        $application = $container->get(Application::class);
        $exitCode = ExitCode::UNSPECIFIED_ERROR;

        try {
            $application->start();
            $exitCode = $application->run(null, new ConsoleBufferedOutput());
        } catch (Throwable $throwable) {
            $application->renderThrowable($throwable, new ConsoleBufferedOutput());
        } finally {
            $application->shutdown($exitCode);
            exit($exitCode);
        }
    }

    /**
     * @throws ErrorException|RuntimeException
     */
    protected function runBootstrap(ConfigInterface $config, ContainerInterface $container): void
    {
        if ($this->bootstrapGroup !== null) {
            (new BootstrapRunner($container, $config->get($this->bootstrapGroup)))->run();
        }
    }

    /**
     * @throws ContainerExceptionInterface|ErrorException|NotFoundExceptionInterface
     */
    protected function checkEvents(ConfigInterface $config, ContainerInterface $container): void
    {
        if ($this->debug && $this->eventsGroup !== null) {
            /** @psalm-suppress MixedMethodCall */
            $container->get(ListenerConfigurationChecker::class)->check($config->get($this->eventsGroup));
        }
    }

    /**
     * @throws ErrorException
     */
    protected function getConfig(): ConfigInterface
    {
        return $this->config ??= $this->createDefaultConfig();
    }

    /**
     * @throws ErrorException|InvalidConfigException
     */
    protected function getContainer(ConfigInterface $config, string $definitionEnvironment): ContainerInterface
    {
        $this->container ??= $this->createDefaultContainer($config, $definitionEnvironment);

        if ($this->container instanceof Container) {
            return $this->container->get(ContainerInterface::class);
        }

        return $this->container;
    }

    /**
     * @throws ErrorException
     */
    protected function createDefaultConfig(): Config
    {
        return ConfigFactory::create(new ConfigPaths($this->rootPath, 'config'), $this->environment);
    }

    /**
     * @throws ErrorException|InvalidConfigException
     */
    protected function createDefaultContainer(ConfigInterface $config, string $definitionEnvironment): Container
    {
        $containerConfig = ContainerConfig::create()->withValidate($this->debug);

        if ($config->has($definitionEnvironment)) {
            $containerConfig = $containerConfig->withDefinitions($config->get($definitionEnvironment));
        }

        if ($config->has("providers-$definitionEnvironment")) {
            $containerConfig = $containerConfig->withProviders($config->get("providers-$definitionEnvironment"));
        }

        if ($config->has("delegates-$definitionEnvironment")) {
            $containerConfig = $containerConfig->withDelegates($config->get("delegates-$definitionEnvironment"));
        }

        if ($config->has("tags-$definitionEnvironment")) {
            $containerConfig = $containerConfig->withTags($config->get("tags-$definitionEnvironment"));
        }

        return new Container($containerConfig);
    }

}

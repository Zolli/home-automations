<?php

declare(strict_types=1);

namespace App\Tests;

use Psr\Log\LoggerInterface;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\Scheduler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use UnexpectedValueException;

final readonly class ServiceContainer
{
    private const array SYNTHETIC_IDS = [HaContext::class, Scheduler::class, LoggerInterface::class];

    private ContainerBuilder $container;

    /** @param class-string ...$publicIds */
    public function __construct(string ...$publicIds)
    {
        $this->container = new ContainerBuilder();

        foreach (self::SYNTHETIC_IDS as $id) {
            $this->container->register($id)->setSynthetic(true)->setPublic(true);
        }

        new PhpFileLoader($this->container, new FileLocator(\dirname(__DIR__)))->load('services.php');

        foreach ($publicIds as $id) {
            $this->container->hasAlias($id)
                ? $this->container->getAlias($id)->setPublic(true)
                : $this->container->findDefinition($id)->setPublic(true);
        }

        $this->container->compile();
    }

    public function replaceSynthetic(string $id, object $service): void
    {
        $this->container->set($id, $service);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function getService(string $id): object
    {
        $service = $this->container->get($id);

        return $service instanceof $id ? $service : throw new UnexpectedValueException("Service \"{$id}\" has an unexpected type.");
    }
}

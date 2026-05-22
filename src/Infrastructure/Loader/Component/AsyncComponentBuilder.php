<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Task\TaskFactoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class AsyncComponentBuilder extends AbstractComponentBuilder
{
    public function __construct(private readonly ?ClockInterface $clock = null) {}

    public function supports(string $type): bool
    {
        return $type === 'async';
    }

    public function build(array $config, ContainerInterface $container): Definition
    {
        /** @var TaskFactoryInterface $taskFactory */
        $taskFactory = $container->get($config['task_factory']);

        return AsyncComponent::create(
            $config['name'],
            static fn($states) => $taskFactory->create($states),
            $config['timeout'] ?? null,
            $this->clock,
        );
    }
}

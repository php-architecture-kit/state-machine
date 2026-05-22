<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\AwaitAllComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use Psr\Container\ContainerInterface;

final class AwaitAllComponentBuilder extends AbstractComponentBuilder
{
    public function supports(string $type): bool
    {
        return $type === 'await_all';
    }

    public function build(array $config, ContainerInterface $container): Definition
    {
        return AwaitAllComponent::create($config['name'], $config['branches']);
    }
}

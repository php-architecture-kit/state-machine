<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class AwaitStateComponentBuilder extends AbstractComponentBuilder
{
    public function __construct(private readonly ?ClockInterface $clock = null) {}

    public function supports(string $type): bool
    {
        return $type === 'await_state';
    }

    public function build(array $config, ContainerInterface $container): Definition
    {
        return AwaitStateComponent::create(
            $config['name'],
            $config['state'],
            $config['detail'] ?? null,
            $config['timeout'] ?? null,
            $this->clock,
        );
    }
}

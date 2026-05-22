<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use Psr\Container\ContainerInterface;

interface ComponentBuilderInterface
{
    public function supports(string $type): bool;

    /** @param array<string,mixed> $config */
    public function build(array $config, ContainerInterface $container): Definition;
}

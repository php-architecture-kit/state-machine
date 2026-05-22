<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\AsyncComponentBuilder;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Task\TaskFactoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AsyncComponentBuilderTest extends TestCase
{
    private AsyncComponentBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AsyncComponentBuilder();
    }

    private function makeContainer(): ContainerInterface
    {
        $factory = $this->createStub(TaskFactoryInterface::class);
        $factory->method('create')->willReturn($this->createStub(Task::class));

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($factory);

        return $container;
    }

    #[Test]
    public function supportsAsyncType(): void
    {
        $this->assertTrue($this->builder->supports('async'));
    }

    #[Test]
    public function doesNotSupportOtherTypes(): void
    {
        $this->assertFalse($this->builder->supports('fork'));
        $this->assertFalse($this->builder->supports('race_first'));
    }

    #[Test]
    public function buildReturnsDefinition(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.async.test',
            'task_factory' => TaskFactoryInterface::class,
        ];

        $definition = $this->builder->build($config, $this->makeContainer());

        $this->assertInstanceOf(Definition::class, $definition);
    }

    #[Test]
    public function buildCreatesInputTriggerPort(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.async.trigger',
            'task_factory' => TaskFactoryInterface::class,
        ];

        $definition = $this->builder->build($config, $this->makeContainer());

        $this->assertTrue(isset($definition->input->trigger));
    }

    #[Test]
    public function buildCreatesOutputPorts(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.async.outputs',
            'task_factory' => TaskFactoryInterface::class,
        ];

        $definition = $this->builder->build($config, $this->makeContainer());

        $this->assertTrue(isset($definition->output->success));
        $this->assertTrue(isset($definition->output->fail));
        $this->assertTrue(isset($definition->output->expired));
    }

    #[Test]
    public function buildWithTimeoutDoesNotThrow(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.async.timeout',
            'task_factory' => TaskFactoryInterface::class,
            'timeout' => 300,
        ];

        $definition = $this->builder->build($config, $this->makeContainer());

        $this->assertInstanceOf(Definition::class, $definition);
    }
}

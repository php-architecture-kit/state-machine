<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\ForkComponentBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ForkComponentBuilderTest extends TestCase
{
    private ForkComponentBuilder $builder;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->builder = new ForkComponentBuilder();
        $this->container = $this->createStub(ContainerInterface::class);
        $this->container->method('has')->willReturn(false);
    }

    #[Test]
    public function supportsForkType(): void
    {
        $this->assertTrue($this->builder->supports('fork'));
    }

    #[Test]
    public function doesNotSupportOtherTypes(): void
    {
        $this->assertFalse($this->builder->supports('choice'));
        $this->assertFalse($this->builder->supports('async'));
    }

    #[Test]
    public function buildReturnsDefinition(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.fork.test',
            'branches' => [
                ['port' => 'email'],
                ['port' => 'sms'],
            ],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertInstanceOf(Definition::class, $definition);
    }

    #[Test]
    public function buildCreatesInputTriggerPort(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.fork.trigger',
            'branches' => [['port' => 'a']],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->input->trigger));
    }

    #[Test]
    public function buildCreatesOutputPortsForEachBranch(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.fork.ports',
            'branches' => [
                ['port' => 'email'],
                ['port' => 'sms'],
                ['port' => 'push'],
            ],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->output->email));
        $this->assertTrue(isset($definition->output->sms));
        $this->assertTrue(isset($definition->output->push));
    }

    #[Test]
    public function buildWithConditionDoesNotThrow(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.fork.condition',
            'branches' => [
                ['port' => 'email', 'condition' => ChoiceTestCondition::class],
                ['port' => 'sms'],
            ],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertInstanceOf(Definition::class, $definition);
    }
}

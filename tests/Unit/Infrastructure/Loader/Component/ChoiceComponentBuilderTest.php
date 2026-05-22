<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\ChoiceComponentBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ChoiceComponentBuilderTest extends TestCase
{
    private ChoiceComponentBuilder $builder;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->builder = new ChoiceComponentBuilder();
        $this->container = $this->createStub(ContainerInterface::class);
        $this->container->method('has')->willReturn(false);
    }

    #[Test]
    public function supportsChoiceType(): void
    {
        $this->assertTrue($this->builder->supports('choice'));
    }

    #[Test]
    public function doesNotSupportOtherTypes(): void
    {
        $this->assertFalse($this->builder->supports('fork'));
        $this->assertFalse($this->builder->supports('async'));
        $this->assertFalse($this->builder->supports('await_all'));
    }

    #[Test]
    public function buildReturnsDefinition(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.choice.test',
            'branches' => [
                'approved' => ChoiceTestCondition::class,
                'rejected' => ChoiceTestCondition::class,
            ],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertInstanceOf(Definition::class, $definition);
    }

    #[Test]
    public function buildCreatesInputTriggerPort(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.choice.trigger',
            'branches' => ['yes' => ChoiceTestCondition::class],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->input->trigger));
    }

    #[Test]
    public function buildCreatesOutputPortsForEachBranch(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.choice.ports',
            'branches' => [
                'approved' => ChoiceTestCondition::class,
                'rejected' => ChoiceTestCondition::class,
            ],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->output->approved));
        $this->assertTrue(isset($definition->output->rejected));
    }

    #[Test]
    public function buildResolvesConditionFromContainerWhenAvailable(): void
    {
        $condition = new ChoiceTestCondition();
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($condition);

        $config = [
            'name' => 'state-machine.unit.yaml.choice.container',
            'branches' => ['ok' => ChoiceTestCondition::class],
        ];

        $definition = $this->builder->build($config, $container);

        $this->assertInstanceOf(Definition::class, $definition);
    }
}

class ChoiceTestCondition implements TransitionCondition
{
    public function check(States $states): TransitionConditionDecision
    {
        return TransitionConditionDecision::Accepted;
    }
}

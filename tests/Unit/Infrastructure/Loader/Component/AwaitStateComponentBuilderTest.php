<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\AwaitStateComponentBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AwaitStateComponentBuilderTest extends TestCase
{
    private AwaitStateComponentBuilder $builder;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->builder = new AwaitStateComponentBuilder();
        $this->container = $this->createStub(ContainerInterface::class);
    }

    #[Test]
    public function supportsAwaitStateType(): void
    {
        $this->assertTrue($this->builder->supports('await_state'));
    }

    #[Test]
    public function doesNotSupportOtherTypes(): void
    {
        $this->assertFalse($this->builder->supports('choice'));
        $this->assertFalse($this->builder->supports('fork'));
        $this->assertFalse($this->builder->supports('await_all'));
    }

    #[Test]
    public function buildReturnsDefinition(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.awaitstate.test',
            'state' => 'payment_confirmed',
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertInstanceOf(Definition::class, $definition);
    }

    #[Test]
    public function buildCreatesInputAtPort(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.awaitstate.ports',
            'state' => 'order_paid',
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->input->at));
    }

    #[Test]
    public function buildCreatesOutputRunAndExpiredPorts(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.awaitstate.outputs',
            'state' => 'order_paid',
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->output->run));
        $this->assertTrue(isset($definition->output->expired));
    }

    #[Test]
    public function buildWithOptionalDetailAndTimeout(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.awaitstate.optional',
            'state' => 'payment',
            'detail' => 'amount',
            'timeout' => 3600,
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertInstanceOf(Definition::class, $definition);
    }
}

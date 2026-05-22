<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\AwaitAllComponentBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AwaitAllComponentBuilderTest extends TestCase
{
    private AwaitAllComponentBuilder $builder;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->builder = new AwaitAllComponentBuilder();
        $this->container = $this->createStub(ContainerInterface::class);
    }

    #[Test]
    public function supportsAwaitAllType(): void
    {
        $this->assertTrue($this->builder->supports('await_all'));
    }

    #[Test]
    public function doesNotSupportOtherTypes(): void
    {
        $this->assertFalse($this->builder->supports('choice'));
        $this->assertFalse($this->builder->supports('fork'));
    }

    #[Test]
    public function buildReturnsDefinition(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.awaitall.test',
            'branches' => ['a', 'b'],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertInstanceOf(Definition::class, $definition);
    }

    #[Test]
    public function buildCreatesInputPortsForEachBranch(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.awaitall.ports',
            'branches' => ['email', 'sms'],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->input->email));
        $this->assertTrue(isset($definition->input->sms));
    }

    #[Test]
    public function buildCreatesDoneOutputPort(): void
    {
        $config = [
            'name' => 'state-machine.unit.yaml.awaitall.done',
            'branches' => ['a', 'b'],
        ];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->output->done));
    }
}

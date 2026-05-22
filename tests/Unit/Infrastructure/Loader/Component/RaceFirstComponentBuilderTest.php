<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\RaceFirstComponentBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class RaceFirstComponentBuilderTest extends TestCase
{
    private RaceFirstComponentBuilder $builder;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->builder = new RaceFirstComponentBuilder();
        $this->container = $this->createStub(ContainerInterface::class);
    }

    #[Test]
    public function supportsRaceFirstType(): void
    {
        $this->assertTrue($this->builder->supports('race_first'));
    }

    #[Test]
    public function doesNotSupportOtherTypes(): void
    {
        $this->assertFalse($this->builder->supports('choice'));
        $this->assertFalse($this->builder->supports('async'));
        $this->assertFalse($this->builder->supports('fork'));
    }

    #[Test]
    public function buildReturnsDefinition(): void
    {
        $config = ['name' => 'state-machine.unit.yaml.racefirst.test'];

        $definition = $this->builder->build($config, $this->container);

        $this->assertInstanceOf(Definition::class, $definition);
    }

    #[Test]
    public function buildCreatesInputGatewayPort(): void
    {
        $config = ['name' => 'state-machine.unit.yaml.racefirst.ports'];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->input->gateway));
    }

    #[Test]
    public function buildCreatesOutputWinnerPort(): void
    {
        $config = ['name' => 'state-machine.unit.yaml.racefirst.winner'];

        $definition = $this->builder->build($config, $this->container);

        $this->assertTrue(isset($definition->output->winner));
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Config;

use PhpArchitecture\Graph\Config\GraphConfig;
use PhpArchitecture\StateMachine\Foundation\Config\Exception\InvalidStateMachineConfigException;
use PhpArchitecture\StateMachine\Foundation\Config\StateMachineConfig;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\Default\AllPointersUntilBlockedStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\ForkTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\RejectStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\SingleTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\TerminalNodeStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\WaitAndForkStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\WaitStrategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class StateMachineConfigTest extends TestCase
{
    #[Test]
    public function defaultConfigAllowsCycles(): void
    {
        $config = new StateMachineConfig();

        $this->assertTrue($config->allowCycles);
    }

    #[Test]
    public function defaultConfigAllowsSelfLoops(): void
    {
        $config = new StateMachineConfig();

        $this->assertTrue($config->allowSelfLoops);
    }

    #[Test]
    public function defaultConfigAllowsParallelTransitions(): void
    {
        $config = new StateMachineConfig();

        $this->assertTrue($config->allowParallelTransitions);
    }

    #[Test]
    public function defaultConfigHasFiveTransitionStrategies(): void
    {
        $config = new StateMachineConfig();

        $this->assertCount(6, $config->transitionStrategies);
    }

    #[Test]
    public function defaultTransitionStrategiesContainAllExpectedTypes(): void
    {
        $config = new StateMachineConfig();
        $types = array_map(static fn(object $s): string => $s::class, $config->transitionStrategies);

        $this->assertContains(TerminalNodeStrategy::class, $types);
        $this->assertContains(WaitAndForkStrategy::class, $types);
        $this->assertContains(WaitStrategy::class, $types);
        $this->assertContains(SingleTransitionStrategy::class, $types);
        $this->assertContains(ForkTransitionStrategy::class, $types);
        $this->assertContains(RejectStrategy::class, $types);
    }

    #[Test]
    public function defaultConfigUsesAllPointersUntilBlockedStrategy(): void
    {
        $config = new StateMachineConfig();

        $this->assertInstanceOf(AllPointersUntilBlockedStrategy::class, $config->pointersSelectionStrategy);
    }

    #[Test]
    public function toGraphConfigMapsAllowCyclesFlag(): void
    {
        $config = new StateMachineConfig(allowCycles: false);
        $graphConfig = $config->toGraphConfig();

        $this->assertInstanceOf(GraphConfig::class, $graphConfig);
        $this->assertFalse($graphConfig->allowCyclicEdge);
    }

    #[Test]
    public function toGraphConfigMapsAllowSelfLoopsFlag(): void
    {
        $config = new StateMachineConfig(allowSelfLoops: false);
        $graphConfig = $config->toGraphConfig();

        $this->assertFalse($graphConfig->allowSelfLoop);
    }

    #[Test]
    public function toGraphConfigMapsAllowParallelTransitionsFlag(): void
    {
        $config = new StateMachineConfig(allowParallelTransitions: false);
        $graphConfig = $config->toGraphConfig();

        $this->assertFalse($graphConfig->allowMultiEdge);
    }

    #[Test]
    public function toGraphConfigPreservesTrueFlags(): void
    {
        $config = new StateMachineConfig(allowCycles: true, allowSelfLoops: true, allowParallelTransitions: true);
        $graphConfig = $config->toGraphConfig();

        $this->assertTrue($graphConfig->allowCyclicEdge);
        $this->assertTrue($graphConfig->allowSelfLoop);
        $this->assertTrue($graphConfig->allowMultiEdge);
    }

    #[Test]
    public function constructorThrowsWhenTransitionStrategiesContainsNonStrategyInstance(): void
    {
        $this->expectException(InvalidStateMachineConfigException::class);

        new StateMachineConfig(transitionStrategies: [new stdClass()]);
    }
}

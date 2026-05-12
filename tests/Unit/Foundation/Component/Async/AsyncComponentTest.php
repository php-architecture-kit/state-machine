<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Async;

use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AsyncComponentTest extends TestCase
{
    private function makeTask(): Task
    {
        return new class implements Task {};
    }

    #[Test]
    public function createReturnsSelfInstance(): void
    {
        $component = AsyncComponent::create('test-async', fn($s) => $this->makeTask());

        $this->assertInstanceOf(AsyncComponent::class, $component);
    }

    #[Test]
    public function componentHasTriggerInputPort(): void
    {
        $component = AsyncComponent::create('test-async', fn($s) => $this->makeTask());

        $this->assertInstanceOf(Port::class, $component->input->trigger);
    }

    #[Test]
    public function componentHasSuccessOutputPort(): void
    {
        $component = AsyncComponent::create('test-async', fn($s) => $this->makeTask());

        $this->assertInstanceOf(Port::class, $component->output->success);
    }

    #[Test]
    public function componentHasFailOutputPort(): void
    {
        $component = AsyncComponent::create('test-async', fn($s) => $this->makeTask());

        $this->assertInstanceOf(Port::class, $component->output->fail);
    }

    #[Test]
    public function componentHasExpiredOutputPort(): void
    {
        $component = AsyncComponent::create('test-async', fn($s) => $this->makeTask());

        $this->assertInstanceOf(Port::class, $component->output->expired);
    }
}

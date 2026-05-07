<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\AwaitState\Node;

use PhpArchitecture\StateMachine\Foundation\Component\AwaitState\Node\AwaitStateNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AwaitStateNodeHandlerTest extends TestCase
{
    #[Test]
    public function handleReturnsContinue(): void
    {
        $handler = new AwaitStateNodeHandler();
        $context = $this->createMock(NodeHandlerContext::class);

        $result = $handler->handle($context);

        $this->assertSame(NodeHandlerResult::Continue, $result);
    }
}

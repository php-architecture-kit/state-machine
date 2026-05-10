<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Identity\TransitionId;

interface TransitionInterface extends EdgeInterface 
{
    public function id(): TransitionId;

    public function u(): NodeId;

    public function v(): NodeId;

    public function condition(): ?TransitionCondition;

    /** @return string[] */
    public function tags(): array;

    public function withInput(NodeId $nodeId): self;

    public function withOutput(NodeId $nodeId): self;
}

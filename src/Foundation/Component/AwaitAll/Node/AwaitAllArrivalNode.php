<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

final class AwaitAllArrivalNode extends Node
{
    public function __construct(
        string $uniqueName,
        public readonly string $branchName,
    ) {
        parent::__construct($uniqueName);
    }

    public function handlerClass(): string
    {
        return AwaitAllArrivalNodeHandler::class;
    }

    public function stateName(): string
    {
        return $this->id->toString() . ".{$this->branchName}.arrived";
    }
}

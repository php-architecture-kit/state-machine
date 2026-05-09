<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Identity;

use InvalidArgumentException;
use LogicException;
use PhpArchitecture\Graph\Vertex\Identity\VertexId;
use PhpArchitecture\Uuid\Uuid;

class NodeId extends VertexId
{
    public const NAMESPACE = '82f2b9a0-4b97-11f1-ab07-0242ac120002';

    protected function __construct(string $value)
    {
        parent::__construct($value);

        if (!in_array($this->getVersion(), [0x3, 0x5, 0x8], true)) {
            throw new InvalidArgumentException("Node ID must be a UUID v3, v5 or v8 and can't be changed.");
        }
    }

    public static function create(string $uniqueName): self
    {
        if ($uniqueName === '') {
            throw new InvalidArgumentException('Globally unique name cannot be empty');
        }

        return self::v5(Uuid::fromString(self::NAMESPACE), $uniqueName);
    }

    public static function new(): static
    {
        throw new LogicException("Node ID cannot be generated randomly. Use NodeId::create(\$uniqueName) instead.");
    }
}

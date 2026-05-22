<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Execution\Repository\ExecutionRepositoryInterface;
use PhpArchitecture\StateMachine\Infrastructure\Serialization\ExecutionNormalizerInterface;

class EloquentExecutionRepository implements ExecutionRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ExecutionNormalizerInterface $normalizer,
        private readonly string $table = 'state_machine_executions',
    ) {}

    public function save(Execution $execution): void
    {
        $id = $execution->id->toString();
        $data = json_encode($this->normalizer->normalize($execution), JSON_THROW_ON_ERROR);

        $exists = $this->connection->table($this->table)->where('id', $id)->exists();

        if ($exists) {
            $this->connection->table($this->table)->where('id', $id)->update(['data' => $data]);
        } else {
            $this->connection->table($this->table)->insert(['id' => $id, 'data' => $data]);
        }
    }

    public function find(ExecutionId $id): ?Execution
    {
        $row = $this->connection->table($this->table)
            ->where('id', $id->toString())
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->normalizer->denormalize(
            json_decode($row->data, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}

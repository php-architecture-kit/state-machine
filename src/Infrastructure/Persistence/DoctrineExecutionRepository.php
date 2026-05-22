<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Execution\Repository\ExecutionRepositoryInterface;
use PhpArchitecture\StateMachine\Infrastructure\Serialization\ExecutionNormalizerInterface;

class DoctrineExecutionRepository implements ExecutionRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ExecutionNormalizerInterface $normalizer,
        private readonly string $table = 'state_machine_executions',
    ) {}

    public function save(Execution $execution): void
    {
        $id = $execution->id->toString();
        $data = json_encode($this->normalizer->normalize($execution), JSON_THROW_ON_ERROR);

        $exists = (bool) $this->connection->fetchOne(
            "SELECT 1 FROM {$this->table} WHERE id = ?",
            [$id],
        );

        if ($exists) {
            $this->connection->executeStatement(
                "UPDATE {$this->table} SET data = ? WHERE id = ?",
                [$data, $id],
            );
        } else {
            $this->connection->executeStatement(
                "INSERT INTO {$this->table} (id, data) VALUES (?, ?)",
                [$id, $data],
            );
        }
    }

    public function find(ExecutionId $id): ?Execution
    {
        $row = $this->connection->fetchAssociative(
            "SELECT data FROM {$this->table} WHERE id = ?",
            [$id->toString()],
        );

        if ($row === false) {
            return null;
        }

        return $this->normalizer->denormalize(
            json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR),
        );
    }
}

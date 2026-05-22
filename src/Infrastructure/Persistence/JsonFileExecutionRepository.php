<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Persistence;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Execution\Repository\ExecutionRepositoryInterface;
use PhpArchitecture\StateMachine\Infrastructure\Serialization\ExecutionNormalizerInterface;

class JsonFileExecutionRepository implements ExecutionRepositoryInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly ExecutionNormalizerInterface $normalizer,
    ) {}

    public function save(Execution $execution): void
    {
        file_put_contents(
            $this->path($execution->id),
            json_encode($this->normalizer->normalize($execution), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        );
    }

    public function find(ExecutionId $id): ?Execution
    {
        $path = $this->path($id);
        if (!file_exists($path)) {
            return null;
        }

        return $this->normalizer->denormalize(
            json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function path(ExecutionId $id): string
    {
        return rtrim($this->directory, '/') . '/' . $id->toString() . '.json';
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Persistence;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Execution\Repository\ExecutionRepositoryInterface;
use PhpArchitecture\StateMachine\Infrastructure\Serialization\ExecutionNormalizerInterface;
use Symfony\Component\Yaml\Yaml;

class YamlFileExecutionRepository implements ExecutionRepositoryInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly ExecutionNormalizerInterface $normalizer,
    ) {}

    public function save(Execution $execution): void
    {
        file_put_contents(
            $this->path($execution->id),
            Yaml::dump($this->normalizer->normalize($execution), 4),
        );
    }

    public function find(ExecutionId $id): ?Execution
    {
        $path = $this->path($id);
        if (!file_exists($path)) {
            return null;
        }

        return $this->normalizer->denormalize(Yaml::parseFile($path));
    }

    private function path(ExecutionId $id): string
    {
        return rtrim($this->directory, '/') . '/' . $id->toString() . '.yaml';
    }
}

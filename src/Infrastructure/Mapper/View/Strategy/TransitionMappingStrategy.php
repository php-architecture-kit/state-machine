<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use Closure;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\TransitionConditionView;
use PhpArchitecture\StateMachine\Presentation\View\TransitionView;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;

class TransitionMappingStrategy implements StateMachineViewMappingStrategy
{
    public function supports(object $resource): bool
    {
        return $resource instanceof TransitionInterface;
    }

    public function mapToView(object $resource): TransitionView
    {
        if (!$resource instanceof TransitionInterface) {
            throw new UnsupportedResourceException(
                sprintf('TransitionMappingStrategy only supports %s, got %s.', TransitionInterface::class, get_class($resource)),
            );
        }

        return new TransitionView(
            id: $resource->id()->toString(),
            from: $resource->u()->toString(),
            to: $resource->v()->toString(),
            tags: $resource->tags(),
            condition: $resource->condition() !== null
                ? $this->mapCondition($resource->condition())
                : null,
        );
    }

    private function mapCondition(TransitionCondition $condition): TransitionConditionView
    {
        $conditionClass = get_class($condition);
        $reflection     = new ReflectionClass($condition);
        $otherProperties = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($condition);

            if ($value instanceof Closure) {
                $otherProperties[$property->getName()] = $this->resolveClosureLocation($value);
            }
        }

        return new TransitionConditionView(
            class: $conditionClass,
            __otherProperties: $otherProperties,
        );
    }

    /** @return array{file: string, line: int} */
    private function resolveClosureLocation(Closure $closure): array
    {
        $rf = new ReflectionFunction($closure);

        return [
            'file' => $this->toRelativePath($rf->getFileName() ?: ''),
            'line' => $rf->getStartLine(),
        ];
    }

    private function toRelativePath(string $absolutePath): string
    {
        $cwd = getcwd();
        if ($cwd !== false && str_starts_with($absolutePath, $cwd . DIRECTORY_SEPARATOR)) {
            return substr($absolutePath, strlen($cwd) + 1);
        }

        return $absolutePath;
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader;

use InvalidArgumentException;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\AllValidTransitionsStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\AsyncComponentBuilder;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\AwaitAllComponentBuilder;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\AwaitStateComponentBuilder;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\ChoiceComponentBuilder;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\ComponentBuilderInterface;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\ForkComponentBuilder;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Component\RaceFirstComponentBuilder;
use PhpArchitecture\StateMachine\Infrastructure\Loader\Node\GenericNode;
use PhpArchitecture\StateMachine\Infrastructure\Loader\StateMachine\DynamicStateMachine;
use Psr\Container\ContainerInterface;

class StateMachineLoader
{
    /** @var ComponentBuilderInterface[] */
    private readonly array $componentBuilders;

    /** @param ComponentBuilderInterface[] $componentBuilders */
    public function __construct(array $componentBuilders = [])
    {
        $this->componentBuilders = empty($componentBuilders)
            ? $this->defaultBuilders()
            : $componentBuilders;
    }

    public function load(array $config, ContainerInterface $container): DynamicStateMachine
    {
        $machine = new DynamicStateMachine($container);

        /** @var array<string,GenericNode> $nodesByName */
        $nodesByName = [];
        /** @var array<string,Definition> $componentsByName */
        $componentsByName = [];

        foreach ($config['nodes'] ?? [] as $nodeDef) {
            $node = new GenericNode(
                $nodeDef['name'],
                $nodeDef['handler'],
                $nodeDef['tags'] ?? [],
                $this->resolveStrategy($nodeDef['strategy'] ?? 'all_valid'),
            );
            $nodesByName[$nodeDef['name']] = $node;
            $machine->addNode($node);
        }

        foreach ($config['components'] ?? [] as $componentDef) {
            $type = $componentDef['type'];
            $definition = $this->buildComponent($type, $componentDef, $container);
            $componentsByName[$componentDef['name']] = $definition;
        }

        foreach ($config['transitions'] ?? [] as $transitionDef) {
            $fromRef = $transitionDef['from'];
            $toRef = $transitionDef['to'];
            $conditionClass = $transitionDef['condition'] ?? null;

            $fromResolved = $this->resolveRef($fromRef, $nodesByName, $componentsByName);
            $toResolved = $this->resolveRef($toRef, $nodesByName, $componentsByName);

            $this->wire($fromResolved, $toResolved, $fromRef, $toRef, $conditionClass, $machine, $container);
        }

        foreach ($componentsByName as $definition) {
            $machine->addDefinition($definition);
        }

        return $machine;
    }

    /**
     * @param array<string,GenericNode> $nodesByName
     * @param array<string,Definition> $componentsByName
     * @return NodeId|Port
     */
    private function resolveRef(string $ref, array $nodesByName, array $componentsByName): NodeId|Port
    {
        foreach ($componentsByName as $name => $definition) {
            if (!str_starts_with($ref, $name . '.')) {
                continue;
            }

            $portName = substr($ref, strlen($name) + 1);
            $inputPorts = (array) $definition->input;
            $outputPorts = (array) $definition->output;

            if (isset($inputPorts[$portName])) {
                return $inputPorts[$portName];
            }

            if (isset($outputPorts[$portName])) {
                return $outputPorts[$portName];
            }

            throw new InvalidArgumentException("Component '{$name}' has no port '{$portName}'.");
        }

        if (isset($nodesByName[$ref])) {
            return $nodesByName[$ref]->id();
        }

        throw new InvalidArgumentException("Cannot resolve reference '{$ref}'. No node or component port with that name.");
    }

    private function wire(
        NodeId|Port $from,
        NodeId|Port $to,
        string $fromRef,
        string $toRef,
        ?string $conditionClass,
        DynamicStateMachine $machine,
        ContainerInterface $container,
    ): void {
        if ($from instanceof NodeId && $to instanceof NodeId) {
            $condition = $conditionClass !== null ? $this->resolveCondition($conditionClass, $container) : null;
            $machine->addTransition($from, $to, $condition);
            return;
        }

        if ($from instanceof NodeId && $to instanceof Port) {
            // node → component input port: attach the node to the port
            $to->attach($from);
            return;
        }

        if ($from instanceof Port && $to instanceof NodeId) {
            // component output port → node: attach the node to the port
            $from->attach($to);
            return;
        }

        // port → port: create a bridge PassthroughNode to connect them
        $bridgeName = "state-machine.yaml.bridge.{$fromRef}.{$toRef}";
        $bridgeNode = new PassthroughNode($bridgeName);
        $machine->addNode($bridgeNode);
        $from->attach($bridgeNode->id());
        $to->attach($bridgeNode->id());
    }

    private function buildComponent(string $type, array $config, ContainerInterface $container): Definition
    {
        foreach ($this->componentBuilders as $builder) {
            if ($builder->supports($type)) {
                return $builder->build($config, $container);
            }
        }

        throw new InvalidArgumentException("No component builder found for type '{$type}'.");
    }

    private function resolveCondition(string $conditionClass, ContainerInterface $container): TransitionCondition
    {
        if ($container->has($conditionClass)) {
            /** @var TransitionCondition $condition */
            $condition = $container->get($conditionClass);
            return $condition;
        }

        return new $conditionClass();
    }

    private function resolveStrategy(string $strategy): TransitionSelectionStrategy
    {
        return match ($strategy) {
            'first_valid' => new FirstValidTransitionStrategy(),
            'all_valid' => new AllValidTransitionsStrategy(),
            default => throw new InvalidArgumentException("Unknown transition strategy '{$strategy}'. Use 'first_valid' or 'all_valid'."),
        };
    }

    /** @return ComponentBuilderInterface[] */
    private function defaultBuilders(): array
    {
        return [
            new ChoiceComponentBuilder(),
            new ForkComponentBuilder(),
            new AwaitStateComponentBuilder(),
            new AwaitAllComponentBuilder(),
            new AsyncComponentBuilder(),
            new RaceFirstComponentBuilder(),
        ];
    }
}

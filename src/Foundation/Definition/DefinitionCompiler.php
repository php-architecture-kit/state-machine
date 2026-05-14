<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition;

use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use LogicException;

/**
 * Compiles a Definition into a flat list of nodes and transitions ready to be
 * registered in a StateMachine graph.
 *
 * Compilation responsibility:
 *  - resolve each Port to its terminal NodeId (following the attachedNode chain),
 *  - rewrite every transition incident to a port so that the port endpoint is
 *    replaced with the resolved terminal node (preserving the transition id),
 *  - remove ports from the resulting graph; the underlying Graph cascades the
 *    removal of any remaining incident edges (the ones tied to dead chains).
 *
 * The compiler works on an internal permissive Graph copy and never mutates the
 * input Definition. Graph configuration validation (self-loops, multi-edges,
 * cycles) is the StateMachine's responsibility - this stage stays permissive
 * because stack collapses may legitimately produce self-loops.
 */
final class DefinitionCompiler
{
    /** @var array<string,Port> */
    private array $portMap = [];

    /** @var array<string,?NodeId> port id => terminal NodeId, or null for dead chain */
    private array $portTerminals = [];

    /**
     * @return array{NodeInterface[],TransitionInterface[]}
     */
    public function compile(Definition $definition): array
    {
        $workingGraph = $this->buildWorkingGraph($definition);
        $this->portMap = $this->collectPorts($workingGraph);
        $this->portTerminals = $this->resolveAllPortTerminals();

        $ghostIds = $this->registerExternalTerminalsAsGhosts($workingGraph);

        $this->rewriteEdges($workingGraph);
        $this->removePortsFromGraph($workingGraph);

        /** @var NodeInterface[] $nodes */
        $nodes = array_values(array_filter(
            $workingGraph->vertexStore->getVertices(),
            static fn(VertexInterface $v): bool => !isset($ghostIds[$v->id()->toString()]),
        ));
        /** @var TransitionInterface[] $transitions */
        $transitions = array_values($workingGraph->edgeStore->getEdges());

        return [$nodes, $transitions];
    }

    private function buildWorkingGraph(Definition $definition): Graph
    {
        $graph = new Graph();

        foreach ($definition->vertexStore->getVertices() as $vertex) {
            $graph->vertexStore->addVertex($vertex);
        }
        foreach ($definition->edgeStore->getEdges() as $edge) {
            $graph->edgeStore->addEdge($edge);
        }

        return $graph;
    }

    /**
     * @return array<string,Port>
     */
    private function collectPorts(Graph $graph): array
    {
        $portMap = [];
        foreach ($graph->vertexStore->getVertices() as $vertex) {
            if ($vertex instanceof Port) {
                $portMap[$vertex->id()->toString()] = $vertex;
            }
        }

        return $portMap;
    }

    /**
     * @return array<string,?NodeId>
     */
    private function resolveAllPortTerminals(): array
    {
        $terminals = [];
        foreach ($this->portMap as $portId => $port) {
            $terminals[$portId] = $this->resolveTerminal($port);
        }

        return $terminals;
    }

    /**
     * Walks the attachedNode chain of the given port until it reaches either:
     *  - null (dead chain),
     *  - a NodeId referring to a non-port vertex (the terminal node).
     *
     * Detects circular attachments and dangling port references.
     */
    private function resolveTerminal(Port $port): ?NodeId
    {
        $current = $port->attachedNode;
        $visited = [$port->id()->toString() => true];

        while (true) {
            if ($current === null) {
                return null;
            }

            $currentId = $current instanceof Port
                ? $current->id()->toString()
                : $current->toString();

            if ($current instanceof NodeId && !isset($this->portMap[$currentId])) {
                return $current;
            }

            if (isset($visited[$currentId])) {
                throw new LogicException(sprintf(
                    'Circular port attachment detected involving port "%s".',
                    $currentId,
                ));
            }
            $visited[$currentId] = true;

            if (!isset($this->portMap[$currentId])) {
                throw new LogicException(sprintf(
                    'Port "%s" is attached to port "%s" which is not in the definition.',
                    $port->id()->toString(),
                    $currentId,
                ));
            }

            $current = $this->portMap[$currentId]->attachedNode;
        }
    }

    /**
     * Iterates every edge in the working graph and rewrites those incident to
     * any port. Edges incident to dead ports are left in place: removing the
     * ports later will cascade-remove the edges via Graph::removeVertex.
     *
     * The transition id is preserved by withInput/withOutput, therefore the
     * old edge must be removed before the rewritten one is added.
     */
    private function rewriteEdges(Graph $graph): void
    {
        foreach ($graph->edgeStore->getEdges() as $edge) {
            $rewritten = $this->resolveEdgeEndpoints($edge);

            if ($rewritten === null || $rewritten === $edge) {
                continue;
            }

            $graph->edgeStore->removeEdge($edge->id());
            $graph->edgeStore->addEdge($rewritten);
        }
    }

    /**
     * Returns:
     *  - the original edge if neither endpoint is a port,
     *  - a rewritten edge with port endpoints replaced by their terminal NodeIds,
     *  - null if at least one endpoint is a dead port - the caller must leave
     *    the edge in place so that the upcoming port removal cascades it away.
     */
    private function resolveEdgeEndpoints(TransitionInterface $edge): ?TransitionInterface
    {
        $uStr = $edge->u()->toString();
        $vStr = $edge->v()->toString();

        $uIsPort = array_key_exists($uStr, $this->portTerminals);
        $vIsPort = array_key_exists($vStr, $this->portTerminals);

        if (!$uIsPort && !$vIsPort) {
            return $edge;
        }

        if ($uIsPort && $this->portTerminals[$uStr] === null) {
            return null;
        }
        if ($vIsPort && $this->portTerminals[$vStr] === null) {
            return null;
        }

        $rewritten = $edge;
        if ($uIsPort) {
            /** @var NodeId $terminal */
            $terminal = $this->portTerminals[$uStr];
            $rewritten = $rewritten->withInput($terminal);
        }
        if ($vIsPort) {
            /** @var NodeId $terminal */
            $terminal = $this->portTerminals[$vStr];
            $rewritten = $rewritten->withOutput($terminal);
        }

        return $rewritten;
    }

    private function removePortsFromGraph(Graph $graph): void
    {
        foreach ($this->portMap as $port) {
            $graph->vertexStore->removeVertex($port->id());
        }
    }

    /**
     * Some ports may resolve to a NodeId that is not present in the working
     * graph (external nodes - intentionally allowed: see the
     * "external attached node" test cases). EdgeStore::addEdge would reject
     * a rewritten edge pointing to such a NodeId because the target vertex
     * is unknown to the graph. To keep the rewrite uniform we register a
     * minimal ghost vertex for every such NodeId; ghosts are filtered out
     * of the final node list.
     *
     * @return array<string,true> set of ghost vertex ids
     */
    private function registerExternalTerminalsAsGhosts(Graph $graph): array
    {
        $ghostIds = [];

        foreach ($this->portTerminals as $terminal) {
            if ($terminal === null) {
                continue;
            }

            $idString = $terminal->toString();
            if ($graph->vertexStore->hasVertex($terminal)) {
                continue;
            }
            if (isset($ghostIds[$idString])) {
                continue;
            }

            $graph->vertexStore->addVertex(new class($terminal) implements VertexInterface {
                public function __construct(private readonly NodeId $id) {}

                public function id(): NodeId
                {
                    return $this->id;
                }
            });

            $ghostIds[$idString] = true;
        }

        return $ghostIds;
    }
}

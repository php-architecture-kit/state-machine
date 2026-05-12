<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Retry;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\Graph\Vertex\VertexInterface;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;

class RetryComponent extends Definition
{
    /**
     * Creates a retry component that wraps a Definition and retries execution on failure.
     *
     * The wrapped definition must have:
     *   - an input port (to trigger execution)
     *   - a success output port (when execution succeeds)
     *   - a failure output port (when an attempt fails and may be retried)
     *
     * Flow:
     *   1. Component receives input on 'trigger' port
     *   2. If attempts remain: increments counter and triggers wrapped definition
     *   3. When wrapped definition succeeds (success port): component outputs to 'success'
     *   4. When wrapped definition signals attempt failure (failure port):
     *      - if attempts remain: loops back to retry
     *      - if maxAttempts exceeded: component outputs to 'failed' port
     *
     * IMPORTANT: The wrapped definition's 'failure' port represents an ATTEMPT failure
     * (a retryable error), NOT the final terminal failure. The component decides whether
     * to retry or give up based on the attempt counter.
     *
     * The retry counter is stored in States under the technical state.
     *
     * @param Definition $wrappedDefinition The definition to retry.
     * @param int $maxAttempts Maximum number of attempts before giving up (must be >= 1).
     * @param string $wrappedInputPort Name of the wrapped definition's input port to trigger (default: 'trigger').
     * @param string $wrappedSuccessPort Name of the wrapped definition's success output port (default: 'success').
     * @param string $wrappedFailurePort Name of the wrapped definition's ATTEMPT FAILURE output port
     *                                    (signals a retryable failure, default: 'failure').
     */
    public static function create(
        string $uniqueName,
        Definition $wrappedDefinition,
        int $maxAttempts,
        string $wrappedInputPort = 'trigger',
        string $wrappedSuccessPort = 'success',
        string $wrappedFailurePort = 'failure',
    ): self {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1');
        }

        if (!isset($wrappedDefinition->input->{$wrappedInputPort})) {
            throw new \InvalidArgumentException("Wrapped definition must have input port '{$wrappedInputPort}'");
        }

        if (!isset($wrappedDefinition->output->{$wrappedSuccessPort})) {
            throw new \InvalidArgumentException("Wrapped definition must have success output port '{$wrappedSuccessPort}'");
        }

        if (!isset($wrappedDefinition->output->{$wrappedFailurePort})) {
            throw new \InvalidArgumentException("Wrapped definition must have failure output port '{$wrappedFailurePort}'");
        }

        $instance = self::newInstance(
            "state-machine.retry.{$uniqueName}",
            inputs: ['trigger'],
            outputs: ['success', 'failed'],
        );

        // Node for tracking retry state and entry point
        $retryNode = new PassthroughNode("state-machine.retry.{$uniqueName}.tracker");
        $retryNodeId = $retryNode->id;

        // Node for handling failure and deciding to retry or give up
        $failureHandlerNode = new PassthroughNode("state-machine.retry.{$uniqueName}.failure-handler");

        // Add internal nodes to the instance
        $instance->addNode($retryNode);
        $instance->addNode($failureHandlerNode);

        // Connect wrapped definition's ports directly to our component's nodes/ports
        // This makes the wrapped definition's input/output part of our component's graph
        $wrappedDefinition->input->{$wrappedInputPort}->attach($retryNode->id);
        $wrappedDefinition->output->{$wrappedSuccessPort}->attach($instance->output->success->id);
        $wrappedDefinition->output->{$wrappedFailurePort}->attach($failureHandlerNode->id);

        // Copy all nodes from wrapped definition directly (like embed() does)
        /** @var array<string,NodeInterface> $wrappedNodes */
        $wrappedNodes = $wrappedDefinition->vertexStore->getVertices(
            static fn (VertexInterface $v): bool => $v instanceof NodeInterface
        );
        foreach ($wrappedNodes as $node) {
            $instance->addNode($node);
        }

        // Copy all transitions from wrapped definition directly (like embed() does)
        /** @var array<string,TransitionInterface> $wrappedTransitions */
        $wrappedTransitions = $wrappedDefinition->edgeStore->getEdges(
            static fn (EdgeInterface $e): bool => $e instanceof TransitionInterface
        );
        foreach ($wrappedTransitions as $transition) {
            $instance->edgeStore->addEdge($transition);
        }

        // Input transition: checks attempt count, increments if allowed
        // When accepted: flows to retryNode, then via attached port to wrapped definition's input
        $instance->addTransition(
            $instance->input->trigger,
            $retryNode,
            static function (States $states) use ($retryNodeId, $maxAttempts): TransitionConditionDecision {
                $attemptCount = self::getAttemptCount($states, $retryNodeId);

                if ($attemptCount >= $maxAttempts) {
                    return TransitionConditionDecision::Rejected;
                }

                self::incrementAttemptCount($states, $retryNodeId);

                return TransitionConditionDecision::Accepted;
            },
        );

        // Note: retryNode -> wrapped input, wrapped success -> component success,
        // and wrapped failure -> failureHandler are implicit via the port attachments above

        // From failure handler: retry path (loops back to retry node)
        $instance->addTransition(
            $failureHandlerNode,
            $retryNode,
            static function (States $states) use ($retryNodeId, $maxAttempts): TransitionConditionDecision {
                $attemptCount = self::getAttemptCount($states, $retryNodeId);

                return $attemptCount < $maxAttempts
                    ? TransitionConditionDecision::Accepted
                    : TransitionConditionDecision::Rejected;
            },
        );

        // From failure handler: give up path to failed output
        $instance->addTransition(
            $failureHandlerNode,
            $instance->output->failed,
            static function (States $states) use ($retryNodeId, $maxAttempts): TransitionConditionDecision {
                $attemptCount = self::getAttemptCount($states, $retryNodeId);

                return $attemptCount >= $maxAttempts
                    ? TransitionConditionDecision::Accepted
                    : TransitionConditionDecision::Rejected;
            },
        );

        return $instance;
    }

    private static function getAttemptCount(States $states, NodeId $retryNodeId): int
    {
        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            return 0;
        }

        $count = $state[$retryNodeId->toString() . '.attemptCount'];

        return $count !== null ? (int) $count->value : 0;
    }

    private static function incrementAttemptCount(States $states, NodeId $retryNodeId): void
    {
        $state = $states->getState(State::TECHNICAL);
        if ($state === null) {
            $state = $states->defineState(State::TECHNICAL, []);
        }

        $currentCount = self::getAttemptCount($states, $retryNodeId);
        $states->modifyState($state->id, [$retryNodeId->toString() . '.attemptCount' => $currentCount + 1]);
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Presentation\Controller\CLI;

use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionConditionCallback;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\StateMachineViewMapper;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\EdgeMappingStrategy;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\NodeMappingStrategy;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\StateMachineMappingStrategy;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\TransitionMappingStrategy;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\VertexMappingStrategy;
use PhpArchitecture\StateMachine\Presentation\Controller\CLI\PrintStateMachineCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;
use ReflectionClass;

class PrintStateMachineCommandTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/state-machine-print-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outputDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->outputDir)) {
            rmdir($this->outputDir);
        }
    }

    private function makeMapper(): StateMachineViewMapper
    {
        return new StateMachineViewMapper([
            new StateMachineMappingStrategy(
                new NodeMappingStrategy(),
                new TransitionMappingStrategy(),
                new VertexMappingStrategy(),
                new EdgeMappingStrategy(),
            ),
        ]);
    }

    private function makeCommand(?StateMachine $fixture = null): PrintStateMachineCommand
    {
        if ($fixture !== null) {
            return new class($this->makeMapper(), $fixture) extends PrintStateMachineCommand {
                public function __construct(
                    \PhpArchitecture\StateMachine\Infrastructure\Mapper\View\StateMachineViewMapperInterface $mapper,
                    private readonly StateMachine $fixture,
                ) {
                    parent::__construct($mapper);
                }

                protected function resolveStateMachine(InputInterface $input, SymfonyStyle $io): ?StateMachine
                {
                    return $this->fixture;
                }
            };
        }

        return new PrintStateMachineCommand($this->makeMapper());
    }

    private function makeMachine(): PrintCommandFixtureMachine
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new PrintCommandFixtureHandler());

        $machine = new PrintCommandFixtureMachine($container);
        $machine->addNodePublic(new PrintCommandFixtureNodeA());
        $machine->addNodePublic(new PrintCommandFixtureNodeB());
        $machine->addTransition(
            PrintCommandFixtureNodeA::nodeId(),
            PrintCommandFixtureNodeB::nodeId(),
        );

        return $machine;
    }

    private function makeMachineWithCondition(): PrintCommandFixtureMachine
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new PrintCommandFixtureHandler());

        $machine = new PrintCommandFixtureMachine($container);
        $machine->addNodePublic(new PrintCommandFixtureNodeA());
        $machine->addNodePublic(new PrintCommandFixtureNodeB());
        $machine->addTransition(
            PrintCommandFixtureNodeA::nodeId(),
            PrintCommandFixtureNodeB::nodeId(),
            TransitionConditionCallback::define(
                static fn(States $s): TransitionConditionDecision => TransitionConditionDecision::Accepted,
            ),
        );

        return $machine;
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function commandSucceedsAndWritesYamlFile(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';

        $tester->execute(['--output' => $outputFile]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($outputFile);
    }

    #[Test]
    public function yamlOutputContainsExpectedTopLevelKeys(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $data = Yaml::parseFile($outputFile);

        $this->assertArrayHasKey('class', $data);
        $this->assertArrayHasKey('nodes', $data);
        $this->assertArrayHasKey('transitions', $data);
    }

    #[Test]
    public function yamlOutputClassMatchesStateMachineClass(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $data = Yaml::parseFile($outputFile);

        $this->assertSame(PrintCommandFixtureMachine::class, $data['class']);
    }

    #[Test]
    public function yamlOutputContainsBothNodes(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $data       = Yaml::parseFile($outputFile);
        $nodeNames  = array_column($data['nodes'], 'globallyUniqueName');

        $this->assertContains('state-machine.test.print.node-a', $nodeNames);
        $this->assertContains('state-machine.test.print.node-b', $nodeNames);
    }

    #[Test]
    public function yamlNodeContainsExpectedKeys(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $data = Yaml::parseFile($outputFile);
        $node = $data['nodes'][0];

        $this->assertArrayHasKey('id', $node);
        $this->assertArrayHasKey('class', $node);
        $this->assertArrayHasKey('globallyUniqueName', $node);
        $this->assertArrayHasKey('handlerClass', $node);
        $this->assertArrayHasKey('transitionSelectionStrategy', $node);
        $this->assertArrayHasKey('tags', $node);
    }

    #[Test]
    public function yamlTransitionContainsFromAndToNodeIds(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $data       = Yaml::parseFile($outputFile);
        $transition = $data['transitions'][0];

        $this->assertArrayHasKey('from', $transition);
        $this->assertArrayHasKey('to', $transition);
        $this->assertSame(PrintCommandFixtureNodeA::nodeId()->toString(), $transition['from']);
        $this->assertSame(PrintCommandFixtureNodeB::nodeId()->toString(), $transition['to']);
    }

    #[Test]
    public function yamlTransitionWithNoConditionHasNullCondition(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $data       = Yaml::parseFile($outputFile);
        $transition = $data['transitions'][0];

        $this->assertNull($transition['condition']);
    }

    #[Test]
    public function yamlTransitionWithConditionCallbackHasConditionWithFileAndLine(): void
    {
        $machine = $this->makeMachineWithCondition();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $data      = Yaml::parseFile($outputFile);
        $condition = $data['transitions'][0]['condition'];

        $this->assertNotNull($condition);
        $this->assertSame(TransitionConditionCallback::class, $condition['class']);
        $this->assertArrayHasKey('callback', $condition);
        $this->assertArrayHasKey('file', $condition['callback']);
        $this->assertArrayHasKey('line', $condition['callback']);
        $this->assertIsString($condition['callback']['file']);
        $this->assertIsInt($condition['callback']['line']);
    }

    #[Test]
    public function commandSucceedsAndWritesPumlFile(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.puml';
        $tester->execute(['--output' => $outputFile, '--plantuml' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($outputFile);
    }

    #[Test]
    public function pumlOutputStartsWithStartuml(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.puml';
        $tester->execute(['--output' => $outputFile, '--plantuml' => true]);

        $content = file_get_contents($outputFile);

        $this->assertStringStartsWith('@startuml', $content);
    }

    #[Test]
    public function pumlOutputContainsEnduml(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.puml';
        $tester->execute(['--output' => $outputFile, '--plantuml' => true]);

        $content = file_get_contents($outputFile);

        $this->assertStringContainsString('@enduml', $content);
    }

    #[Test]
    public function pumlOutputContainsPlantumlServerUrl(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.puml';
        $tester->execute(['--output' => $outputFile, '--plantuml' => true]);

        $content = file_get_contents($outputFile);

        $this->assertStringContainsString('https://www.plantuml.com/plantuml/uml/', $content);
    }

    #[Test]
    public function defaultOutputPathUsesShortClassNameAndYamlExtension(): void
    {
        $machine = $this->makeMachine();
        $command = new class($this->makeMapper(), $machine, $this->outputDir) extends PrintStateMachineCommand {
            public function __construct(
                \PhpArchitecture\StateMachine\Infrastructure\Mapper\View\StateMachineViewMapperInterface $mapper,
                private readonly StateMachine $fixture,
                private readonly string $testOutputDir,
            ) {
                parent::__construct($mapper);
            }

            protected function resolveStateMachine(InputInterface $input, SymfonyStyle $io): ?StateMachine
            {
                return $this->fixture;
            }

            public const DEFAULT_OUTPUT_DIR = '';

            protected function resolveOutputPath(
                InputInterface $input,
                StateMachine $stateMachine,
                string $extension,
            ): string {
                $customPath = $input->getOption('output');
                if ($customPath !== null) {
                    return $customPath;
                }

                $shortName = (new ReflectionClass($stateMachine))->getShortName();

                return sprintf('%s/%s.%s', $this->testOutputDir, $shortName, $extension);
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        $expected = $this->outputDir . '/PrintCommandFixtureMachine.yaml';
        $this->assertFileExists($expected);
    }

    #[Test]
    public function defaultOutputPathUsesShortClassNameAndPumlExtensionWhenPlantumlFlag(): void
    {
        $machine = $this->makeMachine();
        $command = new class($this->makeMapper(), $machine, $this->outputDir) extends PrintStateMachineCommand {
            public function __construct(
                \PhpArchitecture\StateMachine\Infrastructure\Mapper\View\StateMachineViewMapperInterface $mapper,
                private readonly StateMachine $fixture,
                private readonly string $testOutputDir,
            ) {
                parent::__construct($mapper);
            }

            protected function resolveStateMachine(InputInterface $input, SymfonyStyle $io): ?StateMachine
            {
                return $this->fixture;
            }

            public const DEFAULT_OUTPUT_DIR = '';

            protected function resolveOutputPath(
                InputInterface $input,
                StateMachine $stateMachine,
                string $extension,
            ): string {
                $customPath = $input->getOption('output');
                if ($customPath !== null) {
                    return $customPath;
                }

                $shortName = (new ReflectionClass($stateMachine))->getShortName();

                return sprintf('%s/%s.%s', $this->testOutputDir, $shortName, $extension);
            }
        };

        $tester = new CommandTester($command);
        $tester->execute(['--plantuml' => true]);

        $expected = $this->outputDir . '/PrintCommandFixtureMachine.puml';
        $this->assertFileExists($expected);
    }

    #[Test]
    public function missingFactoryCallbackWithoutOverrideReturnsFailure(): void
    {
        $command = new PrintStateMachineCommand($this->makeMapper());
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    #[Test]
    public function invalidFactoryCallbackReturnsFailure(): void
    {
        $command = new PrintStateMachineCommand($this->makeMapper());
        $tester  = new CommandTester($command);

        $tester->execute(['factoryCallback' => 'return new stdClass();']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    #[Test]
    public function syntaxErrorInFactoryCallbackReturnsFailure(): void
    {
        $command = new PrintStateMachineCommand($this->makeMapper());
        $tester  = new CommandTester($command);

        $tester->execute(['factoryCallback' => 'this is not valid php']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    #[Test]
    public function successMessageContainsOutputPath(): void
    {
        $machine = $this->makeMachine();
        $command = $this->makeCommand($machine);
        $tester  = new CommandTester($command);

        $outputFile = $this->outputDir . '/output.yaml';
        $tester->execute(['--output' => $outputFile]);

        $this->assertStringContainsString($outputFile, $tester->getDisplay());
    }
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class PrintCommandFixtureMachine extends StateMachine
{
    public function addNodePublic(NodeInterface $node): static
    {
        return $this->addNode($node);
    }
}

class PrintCommandFixtureNodeA extends Node
{
    public function __construct()
    {
        parent::__construct('state-machine.test.print.node-a');
    }

    public static function nodeId(): \PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId
    {
        return \PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId::create('state-machine.test.print.node-a');
    }

    public function handlerClass(): string
    {
        return PrintCommandFixtureHandler::class;
    }
}

class PrintCommandFixtureNodeB extends Node
{
    public function __construct()
    {
        parent::__construct('state-machine.test.print.node-b');
    }

    public static function nodeId(): \PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId
    {
        return \PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId::create('state-machine.test.print.node-b');
    }

    public function handlerClass(): string
    {
        return PrintCommandFixtureHandler::class;
    }
}

class PrintCommandFixtureHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Continue;
    }
}

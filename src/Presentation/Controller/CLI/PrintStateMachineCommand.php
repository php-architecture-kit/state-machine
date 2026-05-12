<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\Controller\CLI;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\DefinitionViewMapperInterface;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\StateMachineViewMapperInterface;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

use function Jawira\PlantUml\encodep;
use Throwable;

class PrintStateMachineCommand extends Command
{
    public const DEFAULT_OUTPUT_DIR = 'var/state-machine/print';

    public function __construct(
        private readonly StateMachineViewMapperInterface $stateMachineMapper,
        private readonly DefinitionViewMapperInterface $definitionMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('state-machine:print')
            ->setDescription('Print a State Machine definition as YAML or PlantUML')
            ->addArgument(
                'factoryCallback',
                InputArgument::OPTIONAL,
                'PHP expression (passed to eval()) that returns a StateMachine or Definition instance',
            )
            ->addOption(
                'plantuml',
                null,
                InputOption::VALUE_NONE,
                'Output as PlantUML (.puml) instead of YAML',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                sprintf('Output file path (default: %s/<ShortClassName>.<ext>)', self::DEFAULT_OUTPUT_DIR),
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->resolveResource($input, $io);
        if ($result === null) {
            return Command::FAILURE;
        }

        $isStateMachine = $result instanceof StateMachine;
        $plantuml  = (bool) $input->getOption('plantuml');
        $extension = $plantuml ? 'puml' : 'yaml';

        $outputPath = $this->resolveOutputPath($input, $result, $extension);

        if ($isStateMachine) {
            $view = $this->stateMachineMapper->map($result);
            $content = $plantuml
                ? $this->renderPlantuml($view->toArray(), $result)
                : $this->renderYaml($view->toArray());
        } else {
            $view = $this->definitionMapper->map($result);
            $content = $plantuml
                ? $this->renderDefinitionPlantuml($view->toArray(), $result)
                : $this->renderYaml($view->toArray());
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $io->error(sprintf('Could not create output directory: %s', $directory));
            return Command::FAILURE;
        }

        file_put_contents($outputPath, $content);
        $io->success(sprintf('State Machine printed to: %s', $outputPath));

        return Command::SUCCESS;
    }

    protected function resolveResource(InputInterface $input, SymfonyStyle $io): StateMachine|Definition|null
    {
        $factoryCallback = $input->getArgument('factoryCallback');

        if ($factoryCallback === null) {
            $io->error(
                'No factoryCallback provided. Pass a PHP expression that returns a StateMachine or Definition instance.',
            );
            return null;
        }

        $result = null;

        try {
            $result = eval($factoryCallback);
        } catch (Throwable $e) {
            $io->error(sprintf('Error evaluating factoryCallback: %s', $e->getMessage()));
            return null;
        }

        if (!$result instanceof StateMachine && !$result instanceof Definition) {
            $io->error(sprintf(
                'factoryCallback must return an instance of %s or %s, got %s.',
                StateMachine::class,
                Definition::class,
                get_debug_type($result),
            ));
            return null;
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    protected function renderYaml(array $data): string
    {
        return Yaml::dump($data, inline: 6, indent: 2, flags: Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /** @param array<string, mixed> $data */
    protected function renderPlantuml(array $data, StateMachine $stateMachine): string
    {
        $diagram = $this->buildPlantumlDiagram($data, 'State Machine: ' . get_class($stateMachine));
        $encoded = encodep($diagram);

        return $diagram . "\n\n' PlantUML server URL:\n' https://www.plantuml.com/plantuml/uml/{$encoded}\n";
    }

    /** @param array<string, mixed> $data */
    protected function renderDefinitionPlantuml(array $data, Definition $definition): string
    {
        $diagram = $this->buildPlantumlDiagram($data, 'Definition: ' . get_class($definition));
        $encoded = encodep($diagram);

        return $diagram . "\n\n' PlantUML server URL:\n' https://www.plantuml.com/plantuml/uml/{$encoded}\n";
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildPlantumlDiagram(array $data, string $title): string
    {
        $lines = ['@startuml', "' {$title}", ''];

        // Add nodes as components
        $lines[] = "' Nodes";
        foreach ($data['nodes'] ?? [] as $node) {
            $id = $this->sanitizeId($node['id'] ?? 'unknown');
            $class = $this->extractShortClass($node['class'] ?? 'Unknown');
            $name = $node['globallyUniqueName'] ?? $id;
            $lines[] = "component \"{$name}\\n({$class})\" as {$id}";
        }

        $lines[] = '';

        // Add transitions
        $lines[] = "' Transitions";
        foreach ($data['transitions'] ?? [] as $transition) {
            $from = $this->sanitizeId($transition['from'] ?? 'unknown');
            $to = $this->sanitizeId($transition['to'] ?? 'unknown');
            $label = $this->buildTransitionLabel($transition);
            $lines[] = "{$from} --> {$to}{$label}";
        }

        $lines[] = '';
        $lines[] = '@enduml';

        return implode("\n", $lines);
    }

    private function sanitizeId(string $id): string
    {
        // PlantUML IDs can't contain dashes and some other chars
        return 'id_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $id);
    }

    private function extractShortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    private function extractShortName(string $name): string
    {
        // Extract last segment from dotted name like "state-machine.retry.test.input.trigger"
        $parts = explode('.', $name);
        return end($parts) ?: $name;
    }

    /**
     * @param array<string, mixed> $transition
     */
    private function buildTransitionLabel(array $transition): string
    {
        // Use tags as transition label (e.g., "success", "failure", "trigger")
        $tags = $transition['tags'] ?? [];
        if (!empty($tags)) {
            return ' : ' . implode(', ', $tags);
        }

        return '';
    }

    protected function resolveOutputPath(InputInterface $input, StateMachine|Definition $resource, string $extension): string
    {
        $customPath = $input->getOption('output');
        if ($customPath !== null) {
            return $customPath;
        }

        $shortName = (new ReflectionClass($resource))->getShortName();

        return sprintf('%s/%s.%s', self::DEFAULT_OUTPUT_DIR, $shortName, $extension);
    }

}

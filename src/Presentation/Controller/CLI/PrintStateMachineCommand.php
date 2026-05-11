<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\Controller\CLI;

use PhpArchitecture\StateMachine\Foundation\StateMachine;
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
        private readonly StateMachineViewMapperInterface $mapper,
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
                'PHP expression (passed to eval()) that returns a StateMachine instance',
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

        $stateMachine = $this->resolveStateMachine($input, $io);
        if ($stateMachine === null) {
            return Command::FAILURE;
        }

        $view      = $this->mapper->map($stateMachine);
        $plantuml  = (bool) $input->getOption('plantuml');
        $extension = $plantuml ? 'puml' : 'yaml';

        $outputPath = $this->resolveOutputPath($input, $stateMachine, $extension);

        $content = $plantuml
            ? $this->renderPlantuml($view->toArray(), $stateMachine)
            : $this->renderYaml($view->toArray());

        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $io->error(sprintf('Could not create output directory: %s', $directory));
            return Command::FAILURE;
        }

        file_put_contents($outputPath, $content);
        $io->success(sprintf('State Machine printed to: %s', $outputPath));

        return Command::SUCCESS;
    }

    protected function resolveStateMachine(InputInterface $input, SymfonyStyle $io): ?StateMachine
    {
        $factoryCallback = $input->getArgument('factoryCallback');

        if ($factoryCallback === null) {
            $io->error(
                'No factoryCallback provided. Pass a PHP expression that returns a StateMachine instance.',
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

        if (!$result instanceof StateMachine) {
            $io->error(sprintf(
                'factoryCallback must return an instance of %s, got %s.',
                StateMachine::class,
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
        $yaml    = $this->renderYaml($data);
        $diagram = "@startuml\n" . "' State Machine: " . get_class($stateMachine) . "\n" . $yaml . "\n@enduml";
        $encoded = encodep($diagram);

        return $diagram . "\n\n' PlantUML server URL:\n' https://www.plantuml.com/plantuml/uml/{$encoded}\n";
    }

    protected function resolveOutputPath(InputInterface $input, StateMachine $stateMachine, string $extension): string
    {
        $customPath = $input->getOption('output');
        if ($customPath !== null) {
            return $customPath;
        }

        $shortName = (new ReflectionClass($stateMachine))->getShortName();

        return sprintf('%s/%s.%s', self::DEFAULT_OUTPUT_DIR, $shortName, $extension);
    }
}

<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TestQualityAnalyzer\AnalysisResult;
use TestQualityAnalyzer\Analyzer;
use TestQualityAnalyzer\Baseline;
use TestQualityAnalyzer\Configuration;
use TestQualityAnalyzer\Visitor\AssertionCountVisitor;
use TestQualityAnalyzer\Visitor\AssertionRouletteVisitor;
use TestQualityAnalyzer\Visitor\ConditionalTestLogicVisitor;
use TestQualityAnalyzer\Visitor\ConstructorInitializationVisitor;
use TestQualityAnalyzer\Visitor\EmptyTestVisitor;
use TestQualityAnalyzer\Visitor\ExceptionHandlingVisitor;
use TestQualityAnalyzer\Visitor\InterfaceTestingVisitor;
use TestQualityAnalyzer\Visitor\LongTestVisitor;
use TestQualityAnalyzer\Visitor\MagicNumberTestVisitor;
use TestQualityAnalyzer\Visitor\MysteryGuestVisitor;
use TestQualityAnalyzer\Visitor\RedundantAssertionVisitor;
use TestQualityAnalyzer\Visitor\RedundantPrintVisitor;
use TestQualityAnalyzer\Visitor\RottenGreenTestVisitor;
use TestQualityAnalyzer\Visitor\SleepyTestVisitor;

final class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('analyze')
            ->setDescription('Analyse PHPUnit test files for quality issues')
            ->addArgument('directory', InputArgument::REQUIRED, 'Path to test directory')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Baseline file path(s)')
            ->addOption('generate-baseline', null, InputOption::VALUE_NONE, 'Generate a new baseline file')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow overwriting existing baseline')
            ->addOption('add-to-baseline', null, InputOption::VALUE_OPTIONAL, 'Add issues to baseline file', false)
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma-separated detector types to run')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Reason for baseline entries')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to .tq.yaml config file')
            ->addOption('no-config', null, InputOption::VALUE_NONE, 'Skip config file auto-detection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $directory = $input->getArgument('directory');

        if (!is_dir($directory)) {
            $stderr->writeln(sprintf("Error: Directory '%s' not found", $directory));
            return Command::FAILURE;
        }

        // Load configuration
        $config = $this->loadConfig($input, $directory);

        // Create all visitors with config-driven thresholds
        $allVisitors = [
            new AssertionCountVisitor(),
            new AssertionRouletteVisitor(),
            new ConstructorInitializationVisitor(),
            new EmptyTestVisitor(),
            new SleepyTestVisitor(),
            new RedundantPrintVisitor(),
            new ExceptionHandlingVisitor(),
            new InterfaceTestingVisitor(),
            new ConditionalTestLogicVisitor(),
            new MagicNumberTestVisitor(trivialValues: $config->getMagicNumberAllowlist()),
            new RedundantAssertionVisitor(),
            new RottenGreenTestVisitor(),
            new MysteryGuestVisitor(),
            new LongTestVisitor(lineThreshold: $config->getLongTestThreshold()),
        ];

        // Filter visitors
        $onlyOption = $input->getOption('only');
        if ($onlyOption !== null) {
            $onlyTypes = array_map('trim', explode(',', $onlyOption));
            $visitors = array_filter(
                $allVisitors,
                fn($v) => in_array($v->getType(), $onlyTypes, true),
            );
        } else {
            $visitors = $allVisitors;
            $enabledDetectors = $config->getEnabledDetectors();
            if ($enabledDetectors !== null) {
                $visitors = array_filter(
                    $visitors,
                    fn($v) => in_array($v->getType(), $enabledDetectors, true),
                );
            }
            $disabledDetectors = $config->getDisabledDetectors();
            if ($disabledDetectors !== []) {
                $visitors = array_filter(
                    $visitors,
                    fn($v) => !in_array($v->getType(), $disabledDetectors, true),
                );
            }
        }

        // Create analyzer and add filtered visitors
        $analyzer = new Analyzer();
        foreach ($visitors as $visitor) {
            $analyzer->addVisitor($visitor);
        }

        // Run analysis
        $result = $analyzer->analyzeDirectory($directory);

        // Handle baseline workflows
        $generateBaseline = $input->getOption('generate-baseline');
        $addToBaseline = $input->getOption('add-to-baseline');
        $baselinePaths = $input->getOption('baseline');
        $reason = $input->getOption('reason') ?? '';
        $force = $input->getOption('force');

        // Generate baseline
        if ($generateBaseline) {
            return $this->handleGenerateBaseline(
                $result, $baselinePaths, $reason, $force, $directory, $output, $stderr,
            );
        }

        // Add to baseline
        if ($addToBaseline !== false) {
            return $this->handleAddToBaseline(
                $result, $addToBaseline, $reason, $config, $directory, $stderr,
            );
        }

        // Filter by baseline
        $baselinedCount = 0;
        if ($baselinePaths !== []) {
            $filterResult = $this->filterByBaseline($result, $baselinePaths, $directory, $stderr);
            if ($filterResult === null) {
                return Command::FAILURE;
            }
            [$result, $baselinedCount] = $filterResult;
        }

        // Output results
        $format = $input->getOption('format');
        $text = match ($format) {
            'json' => $result->toJson(),
            default => $result->toText(),
        };

        if ($baselinedCount > 0) {
            $text .= sprintf("Baselined issues ignored: %d\n", $baselinedCount);
        }

        $output->write($text);

        return $result->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    private function loadConfig(InputInterface $input, string $directory): Configuration
    {
        if ($input->getOption('no-config')) {
            return Configuration::defaults();
        }

        $configPath = $input->getOption('config');
        if ($configPath !== null) {
            return Configuration::defaults()->merge(Configuration::fromFile($configPath));
        }

        // Auto-detect .tq.yaml in scan directory
        $autoPath = rtrim($directory, '/') . '/.tq.yaml';
        if (file_exists($autoPath)) {
            return Configuration::defaults()->merge(Configuration::fromFile($autoPath));
        }

        return Configuration::defaults();
    }

    private function resolveBaselinePath(string $path, string $directory): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        return rtrim($directory, '/') . '/' . $path;
    }

    private function handleGenerateBaseline(
        AnalysisResult $result,
        array $baselinePaths,
        string $reason,
        bool $force,
        string $directory,
        OutputInterface $output,
        OutputInterface $stderr,
    ): int {
        if ($reason === '') {
            $stderr->writeln('Error: --reason is required when generating a baseline.');
            $stderr->writeln('Provide a reason explaining why these issues are acceptable.');
            $stderr->writeln('');
            $stderr->writeln('Example:');
            $stderr->writeln('  --reason="PHPUnit mock expectations count as assertions at runtime"');
            return Command::FAILURE;
        }

        if ($baselinePaths !== []) {
            $outputPath = $this->resolveBaselinePath($baselinePaths[0], $directory);

            if (file_exists($outputPath) && !$force) {
                $stderr->writeln(sprintf("Error: Baseline file '%s' already exists.", $outputPath));
                $stderr->writeln('');
                $stderr->writeln('To add new issues to the existing baseline, use:');
                $stderr->writeln('  --add-to-baseline');
                $stderr->writeln('');
                $stderr->writeln('To completely regenerate (WARNING: loses existing entries), use:');
                $stderr->writeln('  --generate-baseline --force');
                return Command::FAILURE;
            }

            $baselineContent = Baseline::generate($result->issues, null, $reason);
            $bytesWritten = file_put_contents($outputPath, $baselineContent);
            if ($bytesWritten === false) {
                $stderr->writeln(sprintf("Error: Failed to write baseline to '%s'", $outputPath));
                return Command::FAILURE;
            }

            $stderr->writeln(sprintf(
                'Generated baseline with %d issue(s) at %s',
                count($result->issues),
                $outputPath,
            ));
            return Command::SUCCESS;
        }

        // No --baseline specified, output to stdout
        $baselineContent = Baseline::generate($result->issues, null, $reason);
        $output->write($baselineContent);
        return Command::SUCCESS;
    }

    private function handleAddToBaseline(
        AnalysisResult $result,
        ?string $addToBaselinePath,
        string $reason,
        Configuration $config,
        string $directory,
        OutputInterface $stderr,
    ): int {
        if ($reason === '') {
            $stderr->writeln('Error: --reason is required when adding issues to a baseline.');
            $stderr->writeln('Provide a reason explaining why these issues are acceptable.');
            $stderr->writeln('');
            $stderr->writeln('Example:');
            $stderr->writeln('  --reason="Integration tests access database fixtures directly"');
            return Command::FAILURE;
        }

        // Resolve path: null means bare --add-to-baseline, use config's baseline path
        $path = $addToBaselinePath ?? $config->getBaselinePath();
        $resolvedPath = $this->resolveBaselinePath($path, $directory);

        $issuesToAdd = $result->issues;

        // Validate parent directory exists
        $dir = dirname($resolvedPath);
        if ($dir !== '.' && !is_dir($dir)) {
            $stderr->writeln(sprintf("Error: Directory '%s' does not exist", $dir));
            return Command::FAILURE;
        }

        // Load existing baseline or create new one
        if (file_exists($resolvedPath)) {
            $baseline = Baseline::fromFile($resolvedPath);
        } else {
            $baseline = new Baseline([]);
        }

        // Merge new issues
        $merged = $baseline->merge($issuesToAdd, $reason);

        $bytesWritten = file_put_contents($resolvedPath, $merged->toJson());
        if ($bytesWritten === false) {
            $stderr->writeln(sprintf("Error: Failed to write baseline file to '%s'", $resolvedPath));
            return Command::FAILURE;
        }

        $stderr->writeln(sprintf(
            'Added %d issue(s) to baseline at %s',
            count($issuesToAdd),
            $resolvedPath,
        ));
        return Command::SUCCESS;
    }

    /**
     * @return array{AnalysisResult, int}|null  null on error
     */
    private function filterByBaseline(
        AnalysisResult $result,
        array $baselinePaths,
        string $directory,
        OutputInterface $stderr,
    ): ?array {
        $baselines = [];
        foreach ($baselinePaths as $path) {
            $resolvedPath = $this->resolveBaselinePath($path, $directory);
            if (!file_exists($resolvedPath)) {
                $stderr->writeln(sprintf("Error: Baseline file '%s' not found", $resolvedPath));
                return null;
            }
            $baselines[] = Baseline::fromFile($resolvedPath);
        }

        $mergedBaseline = $baselines[0];
        if (count($baselines) > 1) {
            $mergedBaseline = $mergedBaseline->mergeBaselines(array_slice($baselines, 1));
        }

        $filteredIssues = $mergedBaseline->filter($result->issues);
        $baselinedCount = count($result->issues) - count($filteredIssues);

        $filtered = new AnalysisResult(
            filesScanned: $result->filesScanned,
            testsFound: $result->testsFound,
            issues: $filteredIssues,
            checksRun: $result->checksRun,
        );

        return [$filtered, $baselinedCount];
    }
}

<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TestQualityAnalyzer\Visitor\AssertionCountVisitor;
use TestQualityAnalyzer\Visitor\AssertionRouletteVisitor;
use TestQualityAnalyzer\Visitor\ConditionalTestLogicVisitor;
use TestQualityAnalyzer\Visitor\ConstructorInitializationVisitor;
use TestQualityAnalyzer\Visitor\EmptyTestVisitor;
use TestQualityAnalyzer\Visitor\ExceptionHandlingVisitor;
use TestQualityAnalyzer\Visitor\ExistenceCheckVisitor;
use TestQualityAnalyzer\Visitor\InterfaceTestingVisitor;
use TestQualityAnalyzer\Visitor\LongTestVisitor;
use TestQualityAnalyzer\Visitor\MagicNumberTestVisitor;
use TestQualityAnalyzer\Visitor\MysteryGuestVisitor;
use TestQualityAnalyzer\Visitor\RedundantAssertionVisitor;
use TestQualityAnalyzer\Visitor\RedundantPrintVisitor;
use TestQualityAnalyzer\Visitor\RottenGreenTestVisitor;
use TestQualityAnalyzer\Visitor\SleepyTestVisitor;

final class ListTypesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('list-types')
            ->setDescription('List available test smell detector types');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $visitors = [
            new AssertionCountVisitor(),
            new AssertionRouletteVisitor(),
            new ConstructorInitializationVisitor(),
            new EmptyTestVisitor(),
            new SleepyTestVisitor(),
            new RedundantPrintVisitor(),
            new ExceptionHandlingVisitor(),
            new InterfaceTestingVisitor(),
            new ExistenceCheckVisitor(),
            new ConditionalTestLogicVisitor(),
            new MagicNumberTestVisitor(),
            new RedundantAssertionVisitor(),
            new RottenGreenTestVisitor(),
            new MysteryGuestVisitor(),
            new LongTestVisitor(),
        ];

        $output->writeln('Available smell types:');
        $output->writeln('');
        foreach ($visitors as $visitor) {
            $output->writeln(sprintf('  %-25s %s', $visitor->getType(), $visitor->getName()));
        }
        $output->writeln('');
        $output->writeln('Use with --only=<type> to run only specific detectors.');

        return Command::SUCCESS;
    }
}

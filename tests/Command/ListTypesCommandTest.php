<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TestQualityAnalyzer\Command\ListTypesCommand;

final class ListTypesCommandTest extends TestCase
{
    public function testListsAllDetectorTypes(): void
    {
        $application = new Application();
        $application->add(new ListTypesCommand());
        $command = $application->find('list-types');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertSame(0, $tester->getStatusCode());

        $expectedTypes = [
            'no_assertions', 'assertion_roulette', 'constructor_initialization',
            'empty_test', 'sleepy_test', 'redundant_print', 'exception_handling',
            'interface_testing', 'conditional_test_logic', 'magic_number_test',
            'redundant_assertion', 'rotten_green_test', 'mystery_guest', 'long_test',
        ];

        foreach ($expectedTypes as $type) {
            self::assertStringContainsString($type, $output, "Output should contain detector type: {$type}");
        }
    }
}

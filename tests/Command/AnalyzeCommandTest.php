<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TestQualityAnalyzer\Command\AnalyzeCommand;

final class AnalyzeCommandTest extends TestCase
{
    private function createTester(): CommandTester
    {
        $application = new Application();
        $application->add(new AnalyzeCommand());
        $command = $application->find('analyze');
        return new CommandTester($command);
    }

    public function testAnalyzesDirectoryAndReportsIssues(): void
    {
        $tester = $this->createTester();
        $tester->execute([
            'directory' => __DIR__ . '/../../var/test-examples',
            '--format' => 'json',
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('no_assertions', $tester->getDisplay());
    }

    public function testCleanDirectoryExitsZero(): void
    {
        $tempDir = sys_get_temp_dir() . '/tq-clean-' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $tester = $this->createTester();
            $tester->execute(['directory' => $tempDir]);

            self::assertSame(0, $tester->getStatusCode());
        } finally {
            rmdir($tempDir);
        }
    }

    public function testJsonFormatOutput(): void
    {
        $tester = $this->createTester();
        $tester->execute([
            'directory' => __DIR__ . '/../../var/test-examples',
            '--format' => 'json',
        ]);

        $output = $tester->getDisplay();
        $decoded = json_decode($output, true);
        self::assertIsArray($decoded, 'Output should be valid JSON');
        self::assertArrayHasKey('issues', $decoded);
    }

    public function testOnlyFilterRunsSpecificDetectors(): void
    {
        $tester = $this->createTester();
        $tester->execute([
            'directory' => __DIR__ . '/../../var/test-examples',
            '--only' => 'no_assertions',
            '--format' => 'json',
        ]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('no_assertions', $output);
        self::assertStringNotContainsString('assertion_roulette', $output);
    }

    public function testGenerateBaselineCreatesFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/tq-baseline-gen-' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create a fixture test file with an empty test method
        file_put_contents($tempDir . '/FixtureTest.php', <<<'PHP'
<?php
namespace Test;
use PHPUnit\Framework\TestCase;
class FixtureTest extends TestCase {
    public function testEmpty() {
        // no assertions
    }
}
PHP);

        try {
            $tester = $this->createTester();
            $tester->execute([
                'directory' => $tempDir,
                '--generate-baseline' => true,
                '--baseline' => ['.tq-baseline.json'],
                '--reason' => 'test',
            ]);

            $baselinePath = $tempDir . '/.tq-baseline.json';
            self::assertFileExists($baselinePath);

            $content = json_decode(file_get_contents($baselinePath), true);
            self::assertIsArray($content);
            self::assertArrayHasKey('issues', $content);
        } finally {
            @unlink($tempDir . '/FixtureTest.php');
            @unlink($tempDir . '/.tq-baseline.json');
            @rmdir($tempDir);
        }
    }

    public function testGenerateBaselineRequiresReason(): void
    {
        $tester = $this->createTester();
        $tester->execute([
            'directory' => __DIR__ . '/../../var/test-examples',
            '--generate-baseline' => true,
            '--baseline' => ['.tq-baseline.json'],
        ]);

        self::assertSame(1, $tester->getStatusCode());
    }

    public function testBaselineFilteringRemovesKnownIssues(): void
    {
        $tempDir = sys_get_temp_dir() . '/tq-baseline-filter-' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create a fixture test file
        file_put_contents($tempDir . '/FixtureTest.php', <<<'PHP'
<?php
namespace Test;
use PHPUnit\Framework\TestCase;
class FixtureTest extends TestCase {
    public function testEmpty() {
        // no assertions
    }
}
PHP);

        // Create a matching baseline file
        $baseline = json_encode([
            'generated' => date('c'),
            'issues' => [
                [
                    'file' => 'FixtureTest.php',
                    'test' => 'testEmpty',
                    'type' => 'no_assertions',
                    'reason' => 'test',
                    'added_at' => date('c'),
                ],
                [
                    'file' => 'FixtureTest.php',
                    'test' => 'testEmpty',
                    'type' => 'empty_test',
                    'reason' => 'test',
                    'added_at' => date('c'),
                ],
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($tempDir . '/.tq-baseline.json', $baseline);

        try {
            $tester = $this->createTester();
            $tester->execute([
                'directory' => $tempDir,
                '--baseline' => ['.tq-baseline.json'],
            ]);

            // Baselined issues should be filtered out, so exit code should be 0
            self::assertSame(0, $tester->getStatusCode());
        } finally {
            @unlink($tempDir . '/FixtureTest.php');
            @unlink($tempDir . '/.tq-baseline.json');
            @rmdir($tempDir);
        }
    }

    public function testAddToBaselineAppendsIssues(): void
    {
        $tempDir = sys_get_temp_dir() . '/tq-add-baseline-' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create a fixture test file
        file_put_contents($tempDir . '/FixtureTest.php', <<<'PHP'
<?php
namespace Test;
use PHPUnit\Framework\TestCase;
class FixtureTest extends TestCase {
    public function testEmpty() {
        // no assertions
    }
}
PHP);

        // Create an existing baseline with one entry
        $existingBaseline = json_encode([
            'generated' => date('c'),
            'issues' => [
                [
                    'file' => 'OtherTest.php',
                    'test' => 'testOther',
                    'type' => 'no_assertions',
                    'reason' => 'existing',
                    'added_at' => date('c'),
                ],
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($tempDir . '/.tq-baseline.json', $existingBaseline);

        try {
            $tester = $this->createTester();
            $tester->execute([
                'directory' => $tempDir,
                '--add-to-baseline' => null,
                '--reason' => 'added',
            ]);

            $baselinePath = $tempDir . '/.tq-baseline.json';
            $content = json_decode(file_get_contents($baselinePath), true);
            self::assertIsArray($content);

            // Should have the original entry plus new ones
            self::assertGreaterThan(1, count($content['issues']));

            // Verify original entry is still there
            $files = array_column($content['issues'], 'file');
            self::assertContains('OtherTest.php', $files);
        } finally {
            @unlink($tempDir . '/FixtureTest.php');
            @unlink($tempDir . '/.tq-baseline.json');
            @rmdir($tempDir);
        }
    }

    public function testConfigFileLoadedFromScanDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/tq-config-' . uniqid();
        mkdir($tempDir, 0777, true);

        // Write a config that sets a very low long_test threshold
        file_put_contents($tempDir . '/.tq.yaml', "thresholds:\n  long_test: 5\n");

        // Create a test file with more than 5 lines in the method body
        file_put_contents($tempDir . '/FixtureTest.php', <<<'PHP'
<?php
namespace Test;
use PHPUnit\Framework\TestCase;
class FixtureTest extends TestCase {
    public function testLong() {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $this->assertTrue(true);
    }
}
PHP);

        try {
            $tester = $this->createTester();
            $tester->execute(['directory' => $tempDir]);

            $output = $tester->getDisplay();
            self::assertStringContainsString('Long test', $output);
        } finally {
            @unlink($tempDir . '/FixtureTest.php');
            @unlink($tempDir . '/.tq.yaml');
            @rmdir($tempDir);
        }
    }

    public function testNoConfigFlagSkipsConfigFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/tq-noconfig-' . uniqid();
        mkdir($tempDir, 0777, true);

        // Write a config that sets a very low long_test threshold
        file_put_contents($tempDir . '/.tq.yaml', "thresholds:\n  long_test: 5\n");

        // Create the same test file
        file_put_contents($tempDir . '/FixtureTest.php', <<<'PHP'
<?php
namespace Test;
use PHPUnit\Framework\TestCase;
class FixtureTest extends TestCase {
    public function testLong() {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $this->assertTrue(true);
    }
}
PHP);

        try {
            $tester = $this->createTester();
            $tester->execute([
                'directory' => $tempDir,
                '--no-config' => true,
            ]);

            $output = $tester->getDisplay();
            // With default threshold of 40 lines, this short method should NOT be flagged
            self::assertStringNotContainsString('Long test', $output);
        } finally {
            @unlink($tempDir . '/FixtureTest.php');
            @unlink($tempDir . '/.tq.yaml');
            @rmdir($tempDir);
        }
    }

    public function testInvalidDirectoryReturnsError(): void
    {
        $tester = $this->createTester();
        $tester->execute(['directory' => '/nonexistent/path/to/tests']);

        self::assertNotSame(0, $tester->getStatusCode());
    }
}

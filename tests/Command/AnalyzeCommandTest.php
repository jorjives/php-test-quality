<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TestQualityAnalyzer\Command\AnalyzeCommand;

final class AnalyzeCommandTest extends TestCase
{
    private const EXAMPLES_DIR = __DIR__ . '/../../var/test-examples';

    private const EMPTY_TEST_FIXTURE = <<<'PHP'
<?php
namespace Test;
use PHPUnit\Framework\TestCase;
class FixtureTest extends TestCase {
    public function testEmpty() {
        // no assertions
    }
}
PHP;

    // Method body is longer than 5 lines but well under the default 40
    private const MEDIUM_LENGTH_TEST_FIXTURE = <<<'PHP'
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
PHP;

    private const LOW_LONG_TEST_THRESHOLD_CONFIG = "thresholds:\n  long_test: 5\n";

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tq-analyze-command-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->tempDir);
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function writeFile(string $relativePath, string $content): void
    {
        $path = $this->tempDir . '/' . $relativePath;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * @param array<array<string, string>> $issues
     */
    private function writeBaseline(array $issues): void
    {
        $this->writeFile('.tq-baseline.json', json_encode([
            'generated' => date('c'),
            'issues' => $issues,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, string>
     */
    private function baselineEntry(string $file, string $test, string $type, string $reason = 'test'): array
    {
        return [
            'file' => $file,
            'test' => $test,
            'type' => $type,
            'reason' => $reason,
            'added_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input): CommandTester
    {
        $application = new Application();
        $application->add(new AnalyzeCommand());
        $tester = new CommandTester($application->find('analyze'));
        $tester->execute($input);

        return $tester;
    }

    public function testAnalyzesDirectoryAndReportsIssues(): void
    {
        $tester = $this->runCommand(['directory' => self::EXAMPLES_DIR, '--format' => 'json']);

        self::assertSame(1, $tester->getStatusCode(), 'Should exit non-zero when issues are found');
        self::assertStringContainsString('no_assertions', $tester->getDisplay(), 'Should report no_assertions issues');
    }

    public function testCleanDirectoryExitsZero(): void
    {
        $tester = $this->runCommand(['directory' => $this->tempDir]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testJsonFormatOutput(): void
    {
        $tester = $this->runCommand(['directory' => self::EXAMPLES_DIR, '--format' => 'json']);

        $decoded = json_decode($tester->getDisplay(), true);
        self::assertIsArray($decoded, 'Output should be valid JSON');
        self::assertArrayHasKey('issues', $decoded, 'JSON output should have an issues key');
    }

    public function testOnlyFilterRunsSpecificDetectors(): void
    {
        $tester = $this->runCommand([
            'directory' => self::EXAMPLES_DIR,
            '--only' => 'no_assertions',
            '--format' => 'json',
        ]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('no_assertions', $output, 'Selected detector should run');
        self::assertStringNotContainsString('assertion_roulette', $output, 'Unselected detectors should not run');
    }

    public function testGenerateBaselineCreatesFile(): void
    {
        $this->writeFile('FixtureTest.php', self::EMPTY_TEST_FIXTURE);

        $this->runCommand([
            'directory' => $this->tempDir,
            '--generate-baseline' => true,
            '--baseline' => ['.tq-baseline.json'],
            '--reason' => 'test',
        ]);

        $baselinePath = $this->tempDir . '/.tq-baseline.json';
        self::assertFileExists($baselinePath, 'Baseline file should be written');

        $content = json_decode(file_get_contents($baselinePath), true);
        self::assertIsArray($content, 'Baseline file should contain valid JSON');
        self::assertArrayHasKey('issues', $content, 'Baseline should have an issues key');
    }

    public function testGenerateBaselineRequiresReason(): void
    {
        $tester = $this->runCommand([
            'directory' => self::EXAMPLES_DIR,
            '--generate-baseline' => true,
            '--baseline' => ['.tq-baseline.json'],
        ]);

        self::assertSame(1, $tester->getStatusCode());
    }

    public function testBaselineFilteringRemovesKnownIssues(): void
    {
        $this->writeFile('FixtureTest.php', self::EMPTY_TEST_FIXTURE);
        $this->writeBaseline([
            $this->baselineEntry('FixtureTest.php', 'testEmpty', 'no_assertions'),
            $this->baselineEntry('FixtureTest.php', 'testEmpty', 'empty_test'),
        ]);

        $tester = $this->runCommand([
            'directory' => $this->tempDir,
            '--baseline' => ['.tq-baseline.json'],
        ]);

        // Baselined issues should be filtered out, so exit code should be 0
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testAddToBaselineAppendsIssues(): void
    {
        $this->writeFile('FixtureTest.php', self::EMPTY_TEST_FIXTURE);
        $this->writeBaseline([
            $this->baselineEntry('OtherTest.php', 'testOther', 'no_assertions', 'existing'),
        ]);

        $this->runCommand([
            'directory' => $this->tempDir,
            '--add-to-baseline' => null,
            '--reason' => 'added',
        ]);

        $content = json_decode(file_get_contents($this->tempDir . '/.tq-baseline.json'), true);
        self::assertIsArray($content, 'Baseline file should contain valid JSON');
        self::assertGreaterThan(1, count($content['issues']), 'Should have the original entry plus new ones');
        self::assertContains(
            'OtherTest.php',
            array_column($content['issues'], 'file'),
            'Original entry should still be in the baseline'
        );
    }

    public function testConfigFileLoadedFromScanDirectory(): void
    {
        $this->writeFile('.tq.yaml', self::LOW_LONG_TEST_THRESHOLD_CONFIG);
        $this->writeFile('FixtureTest.php', self::MEDIUM_LENGTH_TEST_FIXTURE);

        $tester = $this->runCommand(['directory' => $this->tempDir]);

        self::assertStringContainsString('Long test', $tester->getDisplay());
    }

    public function testNoConfigFlagSkipsConfigFile(): void
    {
        $this->writeFile('.tq.yaml', self::LOW_LONG_TEST_THRESHOLD_CONFIG);
        $this->writeFile('FixtureTest.php', self::MEDIUM_LENGTH_TEST_FIXTURE);

        $tester = $this->runCommand(['directory' => $this->tempDir, '--no-config' => true]);

        // With default threshold of 40 lines, this short method should NOT be flagged
        self::assertStringNotContainsString('Long test', $tester->getDisplay());
    }

    public function testFilesArgumentRestrictsScanAndBaselineStillMatches(): void
    {
        $emptyTest = <<<'PHP'
<?php
namespace Test;
use PHPUnit\Framework\TestCase;
class %sTest extends TestCase {
    public function testEmpty() {
    }
}
PHP;
        $this->writeFile('Unit/BaselinedTest.php', sprintf($emptyTest, 'Baselined'));
        $this->writeFile('Unit/UnlistedTest.php', sprintf($emptyTest, 'Unlisted'));
        $this->writeBaseline([
            $this->baselineEntry('Unit/BaselinedTest.php', 'testEmpty', 'no_assertions'),
            $this->baselineEntry('Unit/BaselinedTest.php', 'testEmpty', 'empty_test'),
        ]);

        $tester = $this->runCommand([
            'directory' => $this->tempDir,
            'files' => [$this->tempDir . '/Unit/BaselinedTest.php'],
            '--baseline' => ['.tq-baseline.json'],
        ]);

        // UnlistedTest.php would fail if scanned; the listed file's issues are all baselined
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Files scanned: 1', $tester->getDisplay(), 'Only the listed file should be scanned');
    }

    public function testMissingFilesAreSkipped(): void
    {
        // File lists from git diffs can include deleted files
        $tester = $this->runCommand([
            'directory' => self::EXAMPLES_DIR,
            'files' => [self::EXAMPLES_DIR . '/DeletedTest.php'],
        ]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Files scanned: 0', $tester->getDisplay(), 'Missing files should not be scanned');
    }

    public function testFilesOutsideDirectoryAreSkipped(): void
    {
        // Hooks pass every edited file; anything outside the test directory is not ours to judge
        $tester = $this->runCommand([
            'directory' => self::EXAMPLES_DIR,
            'files' => [__FILE__],
        ]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Files scanned: 0', $tester->getDisplay(), 'Files outside the directory should not be scanned');
    }

    public function testInvalidDirectoryReturnsError(): void
    {
        $tester = $this->runCommand(['directory' => '/nonexistent/path/to/tests']);

        self::assertNotSame(0, $tester->getStatusCode());
    }
}

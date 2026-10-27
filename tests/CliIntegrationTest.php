<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests;

use PHPUnit\Framework\TestCase;

final class CliIntegrationTest extends TestCase
{
    private const NO_ASSERTIONS_TEST = <<<'PHP'
<?php
use PHPUnit\Framework\TestCase;
class %s extends TestCase {
    public function %s(): void {
        // Test with no assertions
    }
}
PHP;

    private string $tempDir;
    private string $analyzeScript;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tq-cli-test-' . uniqid();
        mkdir($this->tempDir);
        $this->analyzeScript = dirname(__DIR__) . '/bin/tq';
    }

    protected function tearDown(): void
    {
        // Cleanup temp directory
        $this->recursiveDelete($this->tempDir);
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Run tq against the temp directory with the given extra arguments.
     *
     * @return array{int, string} Exit code and combined stdout/stderr
     */
    private function runTq(string $arguments): array
    {
        $output = [];
        $returnCode = 0;

        exec(
            sprintf(
                'php %s %s %s 2>&1',
                escapeshellarg($this->analyzeScript),
                escapeshellarg($this->tempDir),
                $arguments
            ),
            $output,
            $returnCode
        );

        return [$returnCode, implode("\n", $output)];
    }

    /**
     * @param array<array<string, string>> $issues
     */
    private function writeBaseline(array $issues, string $generated = '2020-01-01T00:00:00+00:00'): string
    {
        $baselinePath = $this->tempDir . '/.tq-baseline.json';
        file_put_contents($baselinePath, json_encode(['generated' => $generated, 'issues' => $issues]));

        return $baselinePath;
    }

    private function writeTestWithNoAssertions(string $className, string $testName): void
    {
        file_put_contents(
            $this->tempDir . "/{$className}.php",
            sprintf(self::NO_ASSERTIONS_TEST, $className, $testName)
        );
    }

    public function testGenerateBaselineRequiresReason(): void
    {
        [$returnCode, $output] = $this->runTq('--generate-baseline --baseline=.tq-baseline.json');

        self::assertSame(1, $returnCode, 'Expected non-zero exit code');
        self::assertStringContainsString('--reason is required', $output, 'Should explain that --reason is required');
    }

    public function testAddToBaselineRequiresReason(): void
    {
        [$returnCode, $output] = $this->runTq('--add-to-baseline');

        self::assertSame(1, $returnCode, 'Expected non-zero exit code');
        self::assertStringContainsString('--reason is required', $output, 'Should explain that --reason is required');
    }

    public function testGenerateBaselineRefusesIfFileExists(): void
    {
        $this->writeBaseline([], date('c'));

        [$returnCode, $output] = $this->runTq(
            '--generate-baseline --baseline=.tq-baseline.json --reason="Test reason"'
        );

        self::assertSame(1, $returnCode, 'Expected non-zero exit code');
        self::assertStringContainsString('already exists', $output, 'Should say the baseline already exists');
        self::assertStringContainsString('--add-to-baseline', $output, 'Should suggest --add-to-baseline');
        self::assertStringContainsString('--force', $output, 'Should suggest --force');
    }

    public function testGenerateBaselineWithForceOverwritesExisting(): void
    {
        $baselinePath = $this->writeBaseline([
            ['file' => 'old.php', 'test' => 'testOld', 'type' => 'no_assertions', 'reason' => 'old'],
        ]);

        [$returnCode] = $this->runTq(
            '--generate-baseline --baseline=.tq-baseline.json --force --reason="New reason"'
        );

        // Should succeed (exit 0) because no issues found in empty directory
        self::assertSame(0, $returnCode, 'Expected zero exit code');

        $newContent = json_decode(file_get_contents($baselinePath), true);
        self::assertArrayHasKey('generated', $newContent, 'Overwritten baseline should have a generated timestamp');
        self::assertNotContains(
            'old.php',
            array_column($newContent['issues'] ?? [], 'file'),
            'Old baseline entries should be gone after --force'
        );
    }

    public function testGeneratedBaselineEntriesHaveTimestamps(): void
    {
        $this->writeTestWithNoAssertions('ExampleTest', 'testEmpty');

        // Generate baseline to stdout (not to file)
        [, $output] = $this->runTq('--generate-baseline --reason="Test timestamps"');
        $decoded = json_decode($output, true);

        self::assertNotNull($decoded, 'Expected valid JSON output');
        self::assertArrayHasKey('issues', $decoded, 'Baseline JSON should have an issues key');
        self::assertNotEmpty($decoded['issues'], 'Expected at least one issue');

        foreach ($decoded['issues'] as $issue) {
            self::assertArrayHasKey('added_at', $issue, 'Each issue should have added_at timestamp');
            self::assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $issue['added_at'],
                'Timestamp should be ISO 8601 format'
            );
        }
    }

    public function testAddToBaselineAddsTimestamps(): void
    {
        // Existing baseline without timestamps (simulating legacy baseline)
        $baselinePath = $this->writeBaseline([
            ['file' => 'OldTest.php', 'test' => 'testOld', 'type' => 'no_assertions', 'reason' => 'Legacy'],
        ]);
        $this->writeTestWithNoAssertions('NewTest', 'testNew');

        [$returnCode] = $this->runTq('--add-to-baseline --reason="Newly added"');

        self::assertSame(0, $returnCode, 'Expected zero exit code');

        $newContent = json_decode(file_get_contents($baselinePath), true);
        $entriesByFile = array_column($newContent['issues'], null, 'file');

        self::assertArrayHasKey('NewTest.php', $entriesByFile, 'New entry should be in baseline');
        self::assertArrayHasKey('added_at', $entriesByFile['NewTest.php'], 'New entry should have added_at timestamp');
        self::assertSame('Newly added', $entriesByFile['NewTest.php']['reason'], 'New entry should carry the given reason');
    }
}

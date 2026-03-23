<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests;

use PHPUnit\Framework\TestCase;

final class CliIntegrationTest extends TestCase
{
    private string $tempDir;
    private string $analyzeScript;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tq-cli-test-' . uniqid();
        mkdir($this->tempDir);
        $this->analyzeScript = dirname(__DIR__) . '/bin/analyze';
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

    public function testGenerateBaselineRequiresReason(): void
    {
        $output = [];
        $returnCode = 0;

        exec(
            sprintf('php %s %s --generate-baseline --baseline 2>&1', escapeshellarg($this->analyzeScript), escapeshellarg($this->tempDir)),
            $output,
            $returnCode
        );

        self::assertSame(1, $returnCode, 'Expected non-zero exit code');
        self::assertStringContainsString('--reason is required', implode("\n", $output));
    }

    public function testAddToBaselineRequiresReason(): void
    {
        $output = [];
        $returnCode = 0;

        exec(
            sprintf('php %s %s --add-to-baseline 2>&1', escapeshellarg($this->analyzeScript), escapeshellarg($this->tempDir)),
            $output,
            $returnCode
        );

        self::assertSame(1, $returnCode, 'Expected non-zero exit code');
        self::assertStringContainsString('--reason is required', implode("\n", $output));
    }

    public function testGenerateBaselineRefusesIfFileExists(): void
    {
        // Create existing baseline file
        $baselinePath = $this->tempDir . '/.tq-baseline.json';
        file_put_contents($baselinePath, json_encode(['generated' => date('c'), 'issues' => []]));

        $output = [];
        $returnCode = 0;

        exec(
            sprintf(
                'php %s %s --generate-baseline --baseline --reason="Test reason" 2>&1',
                escapeshellarg($this->analyzeScript),
                escapeshellarg($this->tempDir)
            ),
            $output,
            $returnCode
        );

        self::assertSame(1, $returnCode, 'Expected non-zero exit code');
        $outputStr = implode("\n", $output);
        self::assertStringContainsString('already exists', $outputStr);
        self::assertStringContainsString('--add-to-baseline', $outputStr);
        self::assertStringContainsString('--force', $outputStr);
    }

    public function testGenerateBaselineWithForceOverwritesExisting(): void
    {
        // Create existing baseline file with old content
        $baselinePath = $this->tempDir . '/.tq-baseline.json';
        file_put_contents($baselinePath, json_encode([
            'generated' => '2020-01-01T00:00:00+00:00',
            'issues' => [['file' => 'old.php', 'test' => 'testOld', 'type' => 'no_assertions', 'reason' => 'old']],
        ]));

        $output = [];
        $returnCode = 0;

        exec(
            sprintf(
                'php %s %s --generate-baseline --baseline --force --reason="New reason" 2>&1',
                escapeshellarg($this->analyzeScript),
                escapeshellarg($this->tempDir)
            ),
            $output,
            $returnCode
        );

        // Should succeed (exit 0) because no issues found in empty directory
        self::assertSame(0, $returnCode, 'Expected zero exit code');

        // Verify file was overwritten (won't have old entry)
        $newContent = json_decode(file_get_contents($baselinePath), true);
        self::assertArrayHasKey('generated', $newContent);
        // Old entry should be gone
        self::assertNotContains('old.php', array_column($newContent['issues'] ?? [], 'file'));
    }

    public function testGeneratedBaselineEntriesHaveTimestamps(): void
    {
        // Create a simple test file with a smell
        $testFilePath = $this->tempDir . '/ExampleTest.php';
        file_put_contents($testFilePath, <<<'PHP'
<?php
use PHPUnit\Framework\TestCase;
class ExampleTest extends TestCase {
    public function testEmpty(): void {
        // Test with no assertions
    }
}
PHP
        );

        $output = [];
        $returnCode = 0;

        // Generate baseline to stdout (not to file)
        exec(
            sprintf(
                'php %s %s --generate-baseline --reason="Test timestamps" 2>&1',
                escapeshellarg($this->analyzeScript),
                escapeshellarg($this->tempDir)
            ),
            $output,
            $returnCode
        );

        $outputStr = implode("\n", $output);
        $decoded = json_decode($outputStr, true);

        self::assertNotNull($decoded, 'Expected valid JSON output');
        self::assertArrayHasKey('issues', $decoded);
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
        // Create existing baseline without timestamps (simulating legacy baseline)
        $baselinePath = $this->tempDir . '/.tq-baseline.json';
        file_put_contents($baselinePath, json_encode([
            'generated' => '2020-01-01T00:00:00+00:00',
            'issues' => [
                ['file' => 'OldTest.php', 'test' => 'testOld', 'type' => 'no_assertions', 'reason' => 'Legacy'],
            ],
        ]));

        // Create a new test file with a smell
        $testFilePath = $this->tempDir . '/NewTest.php';
        file_put_contents($testFilePath, <<<'PHP'
<?php
use PHPUnit\Framework\TestCase;
class NewTest extends TestCase {
    public function testNew(): void {
        // Test with no assertions
    }
}
PHP
        );

        $output = [];
        $returnCode = 0;

        exec(
            sprintf(
                'php %s %s --add-to-baseline --reason="Newly added" 2>&1',
                escapeshellarg($this->analyzeScript),
                escapeshellarg($this->tempDir)
            ),
            $output,
            $returnCode
        );

        self::assertSame(0, $returnCode, 'Expected zero exit code');

        $newContent = json_decode(file_get_contents($baselinePath), true);

        // Find the new entry
        $newEntry = null;
        foreach ($newContent['issues'] as $issue) {
            if ($issue['file'] === 'NewTest.php') {
                $newEntry = $issue;
                break;
            }
        }

        self::assertNotNull($newEntry, 'New entry should be in baseline');
        self::assertArrayHasKey('added_at', $newEntry, 'New entry should have added_at timestamp');
        self::assertSame('Newly added', $newEntry['reason']);
    }
}

<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests;

use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Baseline;
use TestQualityAnalyzer\Issue;

final class BaselineTest extends TestCase
{
    public function testEmptyBaselineMatchesNothing(): void
    {
        $baseline = new Baseline([]);
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'msg');

        self::assertFalse($baseline->isBaselined($issue));
    }

    public function testMatchesExactIssue(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions'],
        ]);
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'msg');

        self::assertTrue($baseline->isBaselined($issue));
    }

    public function testDoesNotMatchDifferentFile(): void
    {
        $baseline = new Baseline([
            ['file' => 'other.php', 'test' => 'testX', 'type' => 'no_assertions'],
        ]);
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'msg');

        self::assertFalse($baseline->isBaselined($issue));
    }

    public function testDoesNotMatchDifferentTest(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testY', 'type' => 'no_assertions'],
        ]);
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'msg');

        self::assertFalse($baseline->isBaselined($issue));
    }

    public function testDoesNotMatchDifferentType(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'other_type'],
        ]);
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'msg');

        self::assertFalse($baseline->isBaselined($issue));
    }

    public function testLoadsFromJsonFile(): void
    {
        $tempFile = sys_get_temp_dir() . '/baseline-' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode([
            'generated' => '2025-01-26T10:00:00Z',
            'issues' => [
                ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Known issue'],
            ],
        ]));

        try {
            $baseline = Baseline::fromFile($tempFile);
            $issue = new Issue('test.php', 'testX', 'no_assertions', 'msg');

            self::assertTrue($baseline->isBaselined($issue));
        } finally {
            unlink($tempFile);
        }
    }

    public function testGeneratesBaselineJson(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
            new Issue('other.php', 'testY', 'no_assertions', 'No assertions'),
        ];

        $reason = 'Test reason for baseline';
        $json = Baseline::generate($issues, null, $reason);
        $decoded = json_decode($json, true);

        self::assertArrayHasKey('generated', $decoded);
        self::assertCount(2, $decoded['issues']);
        self::assertSame('test.php', $decoded['issues'][0]['file']);
        self::assertSame('testX', $decoded['issues'][0]['test']);
        // Verify added_at timestamp is present
        self::assertArrayHasKey('added_at', $decoded['issues'][0]);
        self::assertArrayHasKey('added_at', $decoded['issues'][1]);
        // Verify timestamps are valid ISO 8601 format
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $decoded['issues'][0]['added_at']);
    }

    public function testFilterRemovesBaselinedIssues(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions'],
        ]);

        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'msg'),
            new Issue('other.php', 'testY', 'no_assertions', 'msg'),
        ];

        $filtered = $baseline->filter($issues);

        self::assertCount(1, $filtered);
        self::assertSame('other.php', $filtered[0]->file);
    }

    public function testGenerateWithSingleTypeFilter(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Magic number'),
            new Issue('third.php', 'testZ', 'no_assertions', 'No assertions'),
        ];

        $json = Baseline::generate($issues, ['no_assertions']);
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded['issues']);
        self::assertSame('test.php', $decoded['issues'][0]['file']);
        self::assertSame('third.php', $decoded['issues'][1]['file']);
        self::assertSame('no_assertions', $decoded['issues'][0]['type']);
        self::assertSame('no_assertions', $decoded['issues'][1]['type']);
    }

    public function testGenerateWithMultipleTypeFilters(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Magic number'),
            new Issue('third.php', 'testZ', 'rotten_green_test', 'Unreachable'),
        ];

        $json = Baseline::generate($issues, ['no_assertions', 'magic_number_test']);
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded['issues']);
        self::assertContains('no_assertions', array_map(fn($i) => $i['type'], $decoded['issues']));
        self::assertContains('magic_number_test', array_map(fn($i) => $i['type'], $decoded['issues']));
    }

    public function testGenerateWithEmptyTypeFilterReturnsAll(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Magic number'),
        ];

        $json = Baseline::generate($issues, []);
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded['issues']);
    }

    public function testGenerateWithNullTypeFilterReturnsAll(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Magic number'),
        ];

        $json = Baseline::generate($issues, null);
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded['issues']);
    }

    public function testGenerateWithReasonPopulatesAllEntries(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Magic number'),
        ];

        $reason = 'PHPUnit mock expectations count as assertions at runtime';
        $json = Baseline::generate($issues, null, $reason);
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded['issues']);
        self::assertSame($reason, $decoded['issues'][0]['reason']);
        self::assertSame($reason, $decoded['issues'][1]['reason']);
    }

    public function testGenerateWithReasonAndTypeFilter(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Magic number'),
            new Issue('third.php', 'testZ', 'no_assertions', 'No assertions'),
        ];

        $reason = 'Filtered reason';
        $json = Baseline::generate($issues, ['no_assertions'], $reason);
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded['issues']);
        self::assertSame($reason, $decoded['issues'][0]['reason']);
        self::assertSame($reason, $decoded['issues'][1]['reason']);
    }

    public function testGenerateWithEmptyReasonString(): void
    {
        $issues = [
            new Issue('test.php', 'testX', 'no_assertions', 'No assertions'),
        ];

        $json = Baseline::generate($issues, null, '');
        $decoded = json_decode($json, true);

        self::assertSame('', $decoded['issues'][0]['reason']);
    }

    public function testToJsonSerializesBaseline(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Known issue'],
            ['file' => 'other.php', 'test' => 'testY', 'type' => 'magic_number_test', 'reason' => ''],
        ]);

        $json = $baseline->toJson();
        $decoded = json_decode($json, true);

        self::assertArrayHasKey('generated', $decoded);
        self::assertArrayHasKey('issues', $decoded);
        self::assertCount(2, $decoded['issues']);
        self::assertSame('test.php', $decoded['issues'][0]['file']);
        self::assertSame('testX', $decoded['issues'][0]['test']);
        self::assertSame('no_assertions', $decoded['issues'][0]['type']);
        self::assertSame('Known issue', $decoded['issues'][0]['reason']);
    }

    public function testMergeAddsNewIssues(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Original'],
        ]);

        $newIssues = [
            new Issue('other.php', 'testY', 'magic_number_test', 'Magic number'),
        ];

        $merged = $baseline->merge($newIssues);

        self::assertCount(2, $merged->toArray());
    }

    public function testMergeDeduplicatesByFileTestType(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Original'],
        ]);

        $newIssues = [
            new Issue('test.php', 'testX', 'no_assertions', 'Duplicate'),
            new Issue('other.php', 'testY', 'magic_number_test', 'New issue'),
        ];

        $merged = $baseline->merge($newIssues);
        $entries = $merged->toArray();

        // Should have 2 entries (original + new issue, duplicate removed)
        self::assertCount(2, $entries);

        // Check that the entries are what we expect
        $files = array_column($entries, 'file');
        self::assertContains('test.php', $files);
        self::assertContains('other.php', $files);
    }

    public function testMergePreservesReason(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Original reason'],
        ]);

        $newIssues = [
            new Issue('other.php', 'testY', 'magic_number_test', 'New issue'),
        ];

        $merged = $baseline->merge($newIssues);
        $entries = $merged->toArray();

        // Find the original entry
        $original = array_filter($entries, fn($e) => $e['file'] === 'test.php')[0] ?? null;
        self::assertNotNull($original);
        self::assertSame('Original reason', $original['reason']);
    }

    public function testMergeWithEmptyBaseline(): void
    {
        $baseline = new Baseline([]);

        $newIssues = [
            new Issue('test.php', 'testX', 'no_assertions', 'Issue 1'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Issue 2'),
        ];

        $merged = $baseline->merge($newIssues);
        $entries = $merged->toArray();

        self::assertCount(2, $entries);
    }

    public function testMergeWithReasonPopulatesNewEntries(): void
    {
        $baseline = new Baseline([
            ['file' => 'existing.php', 'test' => 'testExisting', 'type' => 'no_assertions', 'reason' => 'Original reason'],
        ]);

        $newIssues = [
            new Issue('test.php', 'testX', 'no_assertions', 'Issue 1'),
            new Issue('other.php', 'testY', 'magic_number_test', 'Issue 2'),
        ];

        $merged = $baseline->merge($newIssues, 'Added via automation');
        $entries = $merged->toArray();

        self::assertCount(3, $entries);

        // Original entry keeps its reason
        $existing = array_values(array_filter($entries, fn($e) => $e['file'] === 'existing.php'))[0];
        self::assertSame('Original reason', $existing['reason']);

        // New entries get the provided reason
        $new1 = array_values(array_filter($entries, fn($e) => $e['file'] === 'test.php'))[0];
        self::assertSame('Added via automation', $new1['reason']);

        $new2 = array_values(array_filter($entries, fn($e) => $e['file'] === 'other.php'))[0];
        self::assertSame('Added via automation', $new2['reason']);
    }

    public function testMergeAddsTimestampToNewEntries(): void
    {
        $baseline = new Baseline([
            ['file' => 'existing.php', 'test' => 'testExisting', 'type' => 'no_assertions', 'reason' => 'Original', 'added_at' => '2024-01-01T00:00:00+00:00'],
        ]);

        $newIssues = [
            new Issue('new.php', 'testNew', 'magic_number_test', 'New issue'),
        ];

        $merged = $baseline->merge($newIssues, 'Newly added');
        $entries = $merged->toArray();

        // Original entry preserves its timestamp
        $existing = array_values(array_filter($entries, fn($e) => $e['file'] === 'existing.php'))[0];
        self::assertSame('2024-01-01T00:00:00+00:00', $existing['added_at']);

        // New entry has a timestamp
        $new = array_values(array_filter($entries, fn($e) => $e['file'] === 'new.php'))[0];
        self::assertArrayHasKey('added_at', $new);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $new['added_at']);
    }

    public function testToArrayReturnsEntries(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Known issue'],
        ]);

        $entries = $baseline->toArray();

        self::assertCount(1, $entries);
        self::assertSame('test.php', $entries[0]['file']);
    }

    public function testMergeMultipleBaselines(): void
    {
        $baseline1 = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'First baseline'],
        ]);

        $baseline2 = new Baseline([
            ['file' => 'other.php', 'test' => 'testY', 'type' => 'magic_number_test', 'reason' => 'Second baseline'],
        ]);

        $merged = $baseline1->mergeBaselines([$baseline2]);
        $entries = $merged->toArray();

        self::assertCount(2, $entries);
        self::assertSame('test.php', $entries[0]['file']);
        self::assertSame('other.php', $entries[1]['file']);
    }

    public function testMergeMultipleBaselinesDeduplicates(): void
    {
        $baseline1 = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'First baseline'],
        ]);

        $baseline2 = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Duplicate'],
            ['file' => 'other.php', 'test' => 'testY', 'type' => 'magic_number_test', 'reason' => 'New entry'],
        ]);

        $merged = $baseline1->mergeBaselines([$baseline2]);
        $entries = $merged->toArray();

        // Should have 2 entries (one deduplicated)
        self::assertCount(2, $entries);
    }

    public function testMergeMultipleBaselinesWithThreeBaselines(): void
    {
        $baseline1 = new Baseline([
            ['file' => 'test1.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'First'],
        ]);

        $baseline2 = new Baseline([
            ['file' => 'test2.php', 'test' => 'testY', 'type' => 'magic_number_test', 'reason' => 'Second'],
        ]);

        $baseline3 = new Baseline([
            ['file' => 'test3.php', 'test' => 'testZ', 'type' => 'rotten_green_test', 'reason' => 'Third'],
        ]);

        $merged = $baseline1->mergeBaselines([$baseline2, $baseline3]);
        $entries = $merged->toArray();

        self::assertCount(3, $entries);
        self::assertSame('test1.php', $entries[0]['file']);
        self::assertSame('test2.php', $entries[1]['file']);
        self::assertSame('test3.php', $entries[2]['file']);
    }

    public function testMergeMultipleBaselinesWithEmptyArray(): void
    {
        $baseline = new Baseline([
            ['file' => 'test.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'Original'],
        ]);

        $merged = $baseline->mergeBaselines([]);
        $entries = $merged->toArray();

        self::assertCount(1, $entries);
        self::assertSame('test.php', $entries[0]['file']);
    }

    public function testMergeMultipleBaselinesPreservesOrderAndDeduplicates(): void
    {
        $baseline1 = new Baseline([
            ['file' => 'test1.php', 'test' => 'testX', 'type' => 'no_assertions', 'reason' => 'First'],
            ['file' => 'test2.php', 'test' => 'testY', 'type' => 'magic_number_test', 'reason' => 'Shared'],
        ]);

        $baseline2 = new Baseline([
            ['file' => 'test2.php', 'test' => 'testY', 'type' => 'magic_number_test', 'reason' => 'Duplicate'],
            ['file' => 'test3.php', 'test' => 'testZ', 'type' => 'rotten_green_test', 'reason' => 'Third'],
        ]);

        $merged = $baseline1->mergeBaselines([$baseline2]);
        $entries = $merged->toArray();

        // Should have 3 entries (1 deduplicated)
        self::assertCount(3, $entries);
        $files = array_column($entries, 'file');
        self::assertContains('test1.php', $files);
        self::assertContains('test2.php', $files);
        self::assertContains('test3.php', $files);
    }
}

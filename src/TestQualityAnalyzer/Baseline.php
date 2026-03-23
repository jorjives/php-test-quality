<?php

declare(strict_types=1);

namespace TestQualityAnalyzer;

use RuntimeException;

final class Baseline
{
    /**
     * @param array<array{file: string, test: string, type: string, reason?: string, added_at?: string}> $entries
     */
    public function __construct(
        private readonly array $entries,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Baseline file not found: $path");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Could not read baseline file: $path");
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return new self($data['issues'] ?? []);
    }

    /**
     * @param Issue[] $issues
     * @param array<string>|null $typeFilter Optional: only include issues with these types
     */
    public static function generate(array $issues, array|null $typeFilter = null, string $reason = ''): string
    {
        $filtered = $issues;

        // Apply type filter if provided and not empty
        if (is_array($typeFilter) && count($typeFilter) > 0) {
            $filtered = array_values(array_filter(
                $issues,
                fn(Issue $i) => in_array($i->type, $typeFilter, true)
            ));
        }

        $timestamp = date('c');
        $entries = array_map(fn(Issue $i) => [
            'file' => $i->file,
            'test' => $i->testName,
            'type' => $i->type,
            'reason' => $reason,
            'added_at' => $timestamp,
        ], $filtered);

        return json_encode([
            'generated' => $timestamp,
            'issues' => $entries,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function isBaselined(Issue $issue): bool
    {
        foreach ($this->entries as $entry) {
            if (
                $entry['file'] === $issue->file &&
                $entry['test'] === $issue->testName &&
                $entry['type'] === $issue->type
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Issue[] $issues
     * @return Issue[]
     */
    public function filter(array $issues): array
    {
        return array_values(array_filter(
            $issues,
            fn(Issue $i) => !$this->isBaselined($i)
        ));
    }

    /**
     * Serialize baseline to JSON string
     */
    public function toJson(): string
    {
        return json_encode([
            'generated' => date('c'),
            'issues' => $this->entries,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the underlying entries array
     * @return array<array{file: string, test: string, type: string, reason?: string, added_at?: string}>
     */
    public function toArray(): array
    {
        return $this->entries;
    }

    /**
     * Merge new issues into the baseline, deduplicating by file+test+type
     * @param Issue[] $newIssues
     * @param string $reason Optional reason to apply to new entries
     */
    public function merge(array $newIssues, string $reason = ''): self
    {
        $merged = $this->entries;
        $timestamp = date('c');

        foreach ($newIssues as $newIssue) {
            // Check if this issue already exists in the baseline
            $exists = false;
            foreach ($merged as $entry) {
                if (
                    $entry['file'] === $newIssue->file &&
                    $entry['test'] === $newIssue->testName &&
                    $entry['type'] === $newIssue->type
                ) {
                    $exists = true;
                    break;
                }
            }

            // Add the new issue if it doesn't exist
            if (!$exists) {
                $merged[] = [
                    'file' => $newIssue->file,
                    'test' => $newIssue->testName,
                    'type' => $newIssue->type,
                    'reason' => $reason,
                    'added_at' => $timestamp,
                ];
            }
        }

        return new self($merged);
    }

    /**
     * Merge multiple baselines into this baseline, deduplicating by file+test+type
     * @param Baseline[] $baselines
     */
    public function mergeBaselines(array $baselines): self
    {
        $merged = $this->entries;

        foreach ($baselines as $baseline) {
            foreach ($baseline->entries as $entry) {
                // Check if this entry already exists in the merged baseline
                $exists = false;
                foreach ($merged as $existingEntry) {
                    if (
                        $existingEntry['file'] === $entry['file'] &&
                        $existingEntry['test'] === $entry['test'] &&
                        $existingEntry['type'] === $entry['type']
                    ) {
                        $exists = true;
                        break;
                    }
                }

                // Add the entry if it doesn't exist
                if (!$exists) {
                    $merged[] = $entry;
                }
            }
        }

        return new self($merged);
    }
}

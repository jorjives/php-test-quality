<?php

declare(strict_types=1);

namespace TestQualityAnalyzer;

final readonly class AnalysisResult
{
    /**
     * @param Issue[] $issues
     * @param string[] $checksRun
     */
    public function __construct(
        public int $filesScanned,
        public int $testsFound,
        public array $issues,
        public array $checksRun = [],
    ) {}

    public function hasIssues(): bool
    {
        return count($this->issues) > 0;
    }

    /**
     * @return array<string, int>
     */
    public function getIssueCounts(): array
    {
        $counts = [];
        foreach ($this->issues as $issue) {
            $counts[$issue->type] = ($counts[$issue->type] ?? 0) + 1;
        }
        return $counts;
    }

    public function toJson(): string
    {
        $details = array_map(fn(Issue $i) => $i->toArray(), $this->issues);

        return json_encode([
            'summary' => [
                'files_scanned' => $this->filesScanned,
                'tests_found' => $this->testsFound,
            ],
            'issues' => $this->getIssueCounts(),
            'details' => $details,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function toText(): string
    {
        $output = "\n";
        $output .= "TEST QUALITY ANALYSIS REPORT\n";
        $output .= str_repeat("─", 50) . "\n";
        $output .= sprintf("Files scanned: %d\n", $this->filesScanned);
        $output .= sprintf("Tests found: %d\n", $this->testsFound);
        $output .= "\n";

        // Show which checks were run
        if (!empty($this->checksRun)) {
            $output .= "CHECKS RUN\n";
            $output .= str_repeat("─", 50) . "\n";
            foreach ($this->checksRun as $checkName) {
                $output .= sprintf("  ✓ %s\n", $checkName);
            }
            $output .= "\n";
        }

        $counts = $this->getIssueCounts();
        if (empty($counts)) {
            $output .= "✓ No issues detected!\n";
            return $output;
        }

        $output .= "ISSUES DETECTED\n";
        $output .= str_repeat("─", 50) . "\n";
        foreach ($counts as $type => $count) {
            $label = ucfirst(str_replace('_', ' ', $type));
            $output .= sprintf("  %s: %d\n", $label, $count);
        }
        $output .= sprintf("\n  TOTAL: %d\n\n", count($this->issues));

        $output .= "DETAILED FINDINGS\n";
        $output .= str_repeat("─", 50) . "\n";

        $byFile = [];
        foreach ($this->issues as $issue) {
            $byFile[$issue->file][] = $issue;
        }

        foreach ($byFile as $file => $fileIssues) {
            $output .= sprintf("📁 %s\n", $file);
            foreach ($fileIssues as $issue) {
                $icon = match ($issue->type) {
                    'no_assertions' => '❌',
                    default => '•',
                };
                $output .= sprintf("  %s %s\n", $icon, $issue->testName);
                $output .= sprintf("     └─ %s\n", $issue->message);
            }
            $output .= "\n";
        }

        return $output;
    }
}

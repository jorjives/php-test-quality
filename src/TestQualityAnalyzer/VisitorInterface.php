<?php

declare(strict_types=1);

namespace TestQualityAnalyzer;

use PhpParser\NodeVisitor;

interface VisitorInterface extends NodeVisitor
{
    /**
     * Get issues found during traversal
     *
     * @return Issue[]
     */
    public function getIssues(): array;

    /**
     * Reset state between files
     */
    public function reset(): void;

    /**
     * Get the issue type this visitor detects (e.g., 'no_assertions')
     */
    public function getType(): string;

    /**
     * Get human-readable name for this check (e.g., 'Assertion Count')
     */
    public function getName(): string;

    /**
     * Set the current file being analyzed
     */
    public function setCurrentFile(string $file): void;
}

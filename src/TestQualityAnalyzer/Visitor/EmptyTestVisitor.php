<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use TestQualityAnalyzer\Issue;

class EmptyTestVisitor extends AbstractTestVisitor
{
    /** @var Issue[] */
    private array $issues = [];

    public function enterNode(Node $node): ?int
    {
        if (!$node instanceof ClassMethod) {
            return null;
        }

        if (!$this->isTestMethod($node)) {
            return null;
        }

        if ($this->isEmptyTest($node)) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $node->name->name,
                type: 'empty_test',
                message: 'Test method has no executable statements - add assertions or mark with @doesNotPerformAssertions',
            );
        }

        return null;
    }

    private function isEmptyTest(ClassMethod $node): bool
    {
        // No statements at all
        if ($node->stmts === null || count($node->stmts) === 0) {
            return true;
        }

        // Filter out Nop statements (comment-only blocks)
        $meaningfulStmts = array_filter(
            $node->stmts,
            static fn ($stmt) => !$stmt instanceof Nop
        );

        // If no meaningful statements remain, it's empty
        return empty($meaningfulStmts);
    }

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function reset(): void
    {
        $this->currentFile = null;
        $this->issues = [];
    }

    public function getType(): string
    {
        return 'empty_test';
    }

    public function getName(): string
    {
        return 'Empty Test';
    }
}

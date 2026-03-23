<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\Issue;
use TestQualityAnalyzer\VisitorInterface;

final class EmptyTestVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    private ?string $currentFile = null;

    /** @var Issue[] */
    private array $issues = [];

    public function setCurrentFile(string $file): void
    {
        $this->currentFile = $file;
    }

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

    private function isTestMethod(ClassMethod $node): bool
    {
        // Check method name starts with 'test'
        if (str_starts_with($node->name->name, 'test')) {
            return true;
        }

        // Check for #[Test] attribute
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                if ($attrName === 'Test' || str_ends_with($attrName, '\Test')) {
                    return true;
                }
            }
        }

        return false;
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

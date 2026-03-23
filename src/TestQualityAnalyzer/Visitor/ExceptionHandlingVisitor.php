<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\Issue;
use TestQualityAnalyzer\VisitorInterface;

final class ExceptionHandlingVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    private ?string $currentFile = null;
    private ?string $currentTestMethod = null;

    /** @var Issue[] */
    private array $issues = [];

    public function setCurrentFile(string $file): void
    {
        $this->currentFile = $file;
    }

    public function enterNode(Node $node): ?int
    {
        // Track test method entry
        if ($node instanceof ClassMethod) {
            if ($this->isTestMethod($node)) {
                $this->currentTestMethod = $node->name->name;
            }
            return null;
        }

        // Only check for try-catch blocks inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Detect try-catch blocks
        if ($node instanceof TryCatch) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod,
                type: 'exception_handling',
                message: 'Test uses try-catch block - use expectException() instead',
            );
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // When leaving a test method, reset the current test method
        if ($node instanceof ClassMethod && $this->currentTestMethod !== null) {
            if ($node->name->name === $this->currentTestMethod) {
                $this->currentTestMethod = null;
            }
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

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function reset(): void
    {
        $this->currentTestMethod = null;
        $this->issues = [];
    }

    public function getType(): string
    {
        return 'exception_handling';
    }

    public function getName(): string
    {
        return 'Exception Handling';
    }
}

<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\Issue;
use TestQualityAnalyzer\VisitorInterface;

final class LongTestVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    private const LINE_THRESHOLD = 40;

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

        $lineCount = $node->getEndLine() - $node->getStartLine() + 1;

        if ($lineCount > self::LINE_THRESHOLD) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $node->name->name,
                type: 'long_test',
                message: sprintf(
                    'Test is %d lines (threshold: %d). Consider splitting into focused tests.',
                    $lineCount,
                    self::LINE_THRESHOLD
                ),
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
        return 'long_test';
    }

    public function getName(): string
    {
        return 'Long Test';
    }
}

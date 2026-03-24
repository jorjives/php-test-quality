<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use TestQualityAnalyzer\Issue;

class LongTestVisitor extends AbstractTestVisitor
{
    /** @var Issue[] */
    private array $issues = [];

    public function __construct(private readonly int $lineThreshold = 40) {}

    public function enterNode(Node $node): ?int
    {
        if (!$node instanceof ClassMethod) {
            return null;
        }

        if (!$this->isTestMethod($node)) {
            return null;
        }

        $lineCount = $node->getEndLine() - $node->getStartLine() + 1;

        if ($lineCount > $this->lineThreshold) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $node->name->name,
                type: 'long_test',
                message: sprintf(
                    'Test is %d lines (threshold: %d). Consider splitting into focused tests.',
                    $lineCount,
                    $this->lineThreshold
                ),
            );
        }

        return null;
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
        return 'long_test';
    }

    public function getName(): string
    {
        return 'Long Test';
    }
}

<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\Issue;
use TestQualityAnalyzer\VisitorInterface;

final class RedundantPrintVisitor extends NodeVisitorAbstract implements VisitorInterface
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

        // Only check for print statements inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Detect function calls: var_dump, print_r, var_export, error_log, dump, dd
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $funcName = strtolower($node->name->toString());
            if (in_array($funcName, ['var_dump', 'print_r', 'var_export', 'error_log', 'dump', 'dd'], true)) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_print',
                    message: "Test uses {$funcName}() for output",
                );
            }
        }

        // Detect echo statements
        if ($node instanceof Echo_) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod,
                type: 'redundant_print',
                message: 'Test uses echo statement for output',
            );
        }

        // Detect print expressions
        if ($node instanceof Print_) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod,
                type: 'redundant_print',
                message: 'Test uses print statement for output',
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
        return 'redundant_print';
    }

    public function getName(): string
    {
        return 'Redundant Print';
    }
}

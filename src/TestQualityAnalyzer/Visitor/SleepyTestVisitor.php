<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use TestQualityAnalyzer\Issue;

class SleepyTestVisitor extends AbstractTestVisitor
{
    private ?string $currentTestMethod = null;

    /** @var Issue[] */
    private array $issues = [];

    public function enterNode(Node $node): ?int
    {
        // Track test method entry
        if ($node instanceof ClassMethod) {
            if ($this->isTestMethod($node)) {
                $this->currentTestMethod = $node->name->name;
            }
            return null;
        }

        // Only check for sleep calls inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Detect sleep function calls
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $funcName = $node->name->toString();
            if (in_array($funcName, ['sleep', 'usleep', 'time_nanosleep'], true)) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'sleepy_test',
                    message: "Test uses {$funcName}() which causes slow/flaky tests",
                );
            }
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

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function reset(): void
    {
        $this->currentFile = null;
        $this->currentTestMethod = null;
        $this->issues = [];
    }

    public function getType(): string
    {
        return 'sleepy_test';
    }

    public function getName(): string
    {
        return 'Sleepy Test';
    }
}

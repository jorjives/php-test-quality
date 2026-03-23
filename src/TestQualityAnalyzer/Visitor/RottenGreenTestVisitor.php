<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use TestQualityAnalyzer\Issue;

class RottenGreenTestVisitor extends AbstractTestVisitor
{
    private ?string $currentTestMethod = null;
    private int $conditionalDepth = 0;
    private bool $isUnreachable = false;
    /** @var array<array{conditionalDepth: int, isUnreachable: bool}> */
    private array $scopeStack = [];

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

        // Only check inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Track conditional depth
        if ($node instanceof If_ || $node instanceof Switch_) {
            $this->conditionalDepth++;
            return null;
        }

        if ($node instanceof Ternary) {
            $this->conditionalDepth++;
            return null;
        }

        // Save state when entering closure/arrow function (new scope)
        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $this->scopeStack[] = [
                'conditionalDepth' => $this->conditionalDepth,
                'isUnreachable' => $this->isUnreachable,
            ];
            // Reset state for the new scope
            $this->conditionalDepth = 0;
            $this->isUnreachable = false;
            return null;
        }

        // Track unreachable code markers
        if ($node instanceof Return_ || $node instanceof Throw_) {
            $this->isUnreachable = true;
            return null;
        }

        // Check for assertions
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->name;
            if ($this->isAssertionMethod($methodName)) {
                $this->checkAssertion($methodName);
            }
        }

        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $methodName = $node->name->name;
            if ($this->isAssertionMethod($methodName)) {
                $this->checkAssertion($methodName);
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod && $this->currentTestMethod !== null) {
            if ($node->name->name === $this->currentTestMethod) {
                $this->currentTestMethod = null;
                $this->conditionalDepth = 0;
                $this->isUnreachable = false;
            }
        }

        // Decrease conditional depth when leaving conditional statements
        if ($node instanceof If_ || $node instanceof Switch_ || $node instanceof Ternary) {
            $this->conditionalDepth = max(0, $this->conditionalDepth - 1);
        }

        // Restore state when leaving closure/arrow function
        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $savedState = array_pop($this->scopeStack);
            if ($savedState !== null) {
                $this->conditionalDepth = $savedState['conditionalDepth'];
                $this->isUnreachable = $savedState['isUnreachable'];
            }
        }

        return null;
    }

    private function checkAssertion(string $methodName): void
    {
        if ($this->conditionalDepth > 0) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod ?? 'unknown',
                type: 'rotten_green_test',
                message: "Assertion '{$methodName}' inside conditional block may not execute",
            );
        } elseif ($this->isUnreachable) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod ?? 'unknown',
                type: 'rotten_green_test',
                message: "Assertion '{$methodName}' is unreachable (after return/throw)",
            );
        }
    }

    private function isAssertionMethod(string $methodName): bool
    {
        return str_starts_with($methodName, 'assert');
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
        $this->conditionalDepth = 0;
        $this->isUnreachable = false;
        $this->scopeStack = [];
        $this->issues = [];
    }

    public function getType(): string
    {
        return 'rotten_green_test';
    }

    public function getName(): string
    {
        return 'Rotten Green Test';
    }
}

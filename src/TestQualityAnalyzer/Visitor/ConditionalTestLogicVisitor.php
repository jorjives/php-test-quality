<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use TestQualityAnalyzer\Issue;

class ConditionalTestLogicVisitor extends AbstractTestVisitor
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

        // Only check inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Detect if statements
        if ($node instanceof If_) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod,
                type: 'conditional_test_logic',
                message: 'Test contains if statement - conditional logic may prevent some assertions from executing',
            );
        }

        // Detect switch statements
        if ($node instanceof Switch_) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod,
                type: 'conditional_test_logic',
                message: 'Test contains switch statement - multiple execution paths reduce test reliability',
            );
        }

        // Detect ternary operators
        if ($node instanceof Ternary) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod,
                type: 'conditional_test_logic',
                message: 'Test contains ternary operator - consider explicit assertions instead',
            );
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
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
        return 'conditional_test_logic';
    }

    public function getName(): string
    {
        return 'Conditional Test Logic';
    }
}

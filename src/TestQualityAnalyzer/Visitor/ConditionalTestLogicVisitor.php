<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\Issue;
use TestQualityAnalyzer\VisitorInterface;

final class ConditionalTestLogicVisitor extends NodeVisitorAbstract implements VisitorInterface
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

    private function isTestMethod(ClassMethod $node): bool
    {
        if (str_starts_with($node->name->name, 'test')) {
            return true;
        }

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
        return 'conditional_test_logic';
    }

    public function getName(): string
    {
        return 'Conditional Test Logic';
    }
}

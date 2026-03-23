<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use TestQualityAnalyzer\Issue;

class RedundantAssertionVisitor extends AbstractTestVisitor
{
    private const COMPARISON_ASSERTIONS = [
        'assertEquals',
        'assertSame',
        'assertNotEquals',
        'assertNotSame',
    ];

    private const BOOLEAN_ASSERTIONS = ['assertTrue', 'assertFalse'];

    private const NULL_ASSERTIONS = ['assertNull', 'assertNotNull'];

    private const EMPTY_ASSERTIONS = ['assertEmpty', 'assertNotEmpty'];

    private ?string $currentTestMethod = null;

    /** @var Issue[] */
    private array $issues = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod) {
            if ($this->isTestMethod($node)) {
                $this->currentTestMethod = $node->name->name;
            }
            return null;
        }

        if ($this->currentTestMethod === null) {
            return null;
        }

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->name;

            if (in_array($methodName, self::COMPARISON_ASSERTIONS, true)) {
                $this->checkComparisonAssertion($node, $methodName);
            }

            if (in_array($methodName, self::BOOLEAN_ASSERTIONS, true)) {
                $this->checkBooleanAssertion($node, $methodName);
            }

            if (in_array($methodName, self::NULL_ASSERTIONS, true)) {
                $this->checkNullAssertion($node, $methodName);
            }

            if (in_array($methodName, self::EMPTY_ASSERTIONS, true)) {
                $this->checkEmptyAssertion($node, $methodName);
            }
        }

        return null;
    }

    private function checkComparisonAssertion(MethodCall $node, string $methodName): void
    {
        if (count($node->args) < 2) {
            return;
        }

        $expected = $node->args[0]->value;
        $actual = $node->args[1]->value;

        // Check identical integer literals
        if ($expected instanceof Int_ && $actual instanceof Int_) {
            if ($expected->value === $actual->value) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: "Redundant assertion: {$methodName}({$expected->value}, {$actual->value}) always passes",
                );
            }
        }

        // Check identical string literals
        if ($expected instanceof String_ && $actual instanceof String_) {
            if ($expected->value === $actual->value) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: "Redundant assertion: {$methodName}('{$expected->value}', '{$actual->value}') always passes",
                );
            }
        }

        // Check identical float literals
        if ($expected instanceof Float_ && $actual instanceof Float_) {
            if ($expected->value === $actual->value) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: "Redundant assertion: {$methodName}({$expected->value}, {$actual->value}) always passes",
                );
            }
        }

        // Check same variable
        if ($expected instanceof Variable && $actual instanceof Variable) {
            if ($expected->name === $actual->name) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: "Redundant assertion: {$methodName}(\${$expected->name}, \${$actual->name}) compares variable to itself",
                );
            }
        }
    }

    private function checkBooleanAssertion(MethodCall $node, string $methodName): void
    {
        if (count($node->args) < 1) {
            return;
        }

        $arg = $node->args[0]->value;

        if ($arg instanceof ConstFetch) {
            $constName = strtolower($arg->name->toString());

            if ($methodName === 'assertTrue' && $constName === 'true') {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: 'Redundant assertion: assertTrue(true) always passes',
                );
            }

            if ($methodName === 'assertFalse' && $constName === 'false') {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: 'Redundant assertion: assertFalse(false) always passes',
                );
            }
        }
    }

    private function checkNullAssertion(MethodCall $node, string $methodName): void
    {
        if (count($node->args) < 1) {
            return;
        }

        $arg = $node->args[0]->value;

        if ($arg instanceof ConstFetch) {
            $constName = strtolower($arg->name->toString());

            if ($methodName === 'assertNull' && $constName === 'null') {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: 'Redundant assertion: assertNull(null) always passes',
                );
            }
        }
    }

    private function checkEmptyAssertion(MethodCall $node, string $methodName): void
    {
        if (count($node->args) < 1) {
            return;
        }

        $arg = $node->args[0]->value;

        if ($methodName === 'assertEmpty') {
            // Check empty array
            if ($arg instanceof Array_ && count($arg->items) === 0) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: 'Redundant assertion: assertEmpty([]) always passes',
                );
            }

            // Check empty string
            if ($arg instanceof String_ && $arg->value === '') {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'redundant_assertion',
                    message: "Redundant assertion: assertEmpty('') always passes",
                );
            }
        }
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
        return 'redundant_assertion';
    }

    public function getName(): string
    {
        return 'Redundant Assertion';
    }
}

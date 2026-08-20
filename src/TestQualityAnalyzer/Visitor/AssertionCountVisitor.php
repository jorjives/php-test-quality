<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use TestQualityAnalyzer\Issue;

class AssertionCountVisitor extends AbstractTestVisitor
{
    private ?string $currentTestMethod = null;
    private int $assertionCount = 0;
    private bool $hasAssertionExemption = false;

    /** @var Issue[] */
    private array $issues = [];

    public function enterNode(Node $node): ?int
    {
        // Track test method entry
        if ($node instanceof ClassMethod) {
            if ($this->isTestMethod($node)) {
                $this->currentTestMethod = $node->name->name;
                $this->assertionCount = 0;
                $this->hasAssertionExemption = false;
            }
            return null;
        }

        // Only count assertions inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Count $this->assert*() calls
        if ($node instanceof MethodCall) {
            if ($node->name instanceof Identifier) {
                $methodName = $node->name->name;

                if (str_starts_with($methodName, 'assert')) {
                    $this->assertionCount++;
                }

                if (str_starts_with($methodName, 'expectException')) {
                    $this->hasAssertionExemption = true;
                }

                // PHPUnit verifies mock expectations (->expects(...)) automatically
                // during test teardown, so this counts as a real assertion.
                if ($methodName === 'expects') {
                    $this->assertionCount++;
                }

                // expectNotToPerformAssertions() explicitly declares that no
                // assertions are expected, so the test is intentionally assertion-free.
                if ($methodName === 'expectNotToPerformAssertions') {
                    $this->hasAssertionExemption = true;
                }
            }
        }

        // Count self::assert*() and static::assert*() calls
        if ($node instanceof StaticCall) {
            if ($node->class instanceof Name && $node->name instanceof Identifier) {
                $className = $node->class->toString();
                $methodName = $node->name->name;

                if (in_array($className, ['self', 'static'], true)) {
                    if (str_starts_with($methodName, 'assert')) {
                        $this->assertionCount++;
                    }

                    if (str_starts_with($methodName, 'expectException')) {
                        $this->hasAssertionExemption = true;
                    }

                    if ($methodName === 'expectNotToPerformAssertions') {
                        $this->hasAssertionExemption = true;
                    }
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // When leaving a test method, check if it had assertions
        if ($node instanceof ClassMethod && $this->currentTestMethod !== null) {
            if ($node->name->name === $this->currentTestMethod) {
                if ($this->assertionCount === 0 && !$this->hasAssertionExemption) {
                    $this->issues[] = new Issue(
                        file: $this->currentFile ?? 'unknown',
                        testName: $this->currentTestMethod,
                        type: 'no_assertions',
                        message: 'Test has no assertions',
                    );
                }
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
        $this->assertionCount = 0;
        $this->hasAssertionExemption = false;
        $this->issues = [];
    }

    public function getType(): string
    {
        return 'no_assertions';
    }

    public function getName(): string
    {
        return 'Assertion Count';
    }
}

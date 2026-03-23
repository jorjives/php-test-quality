<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use TestQualityAnalyzer\Issue;

class AssertionRouletteVisitor extends AbstractTestVisitor
{
    private const ASSERTION_SIGNATURES = [
        // methodName => [minRequiredArgs, messageArgIndex]
        'assertTrue' => [1, 1],
        'assertFalse' => [1, 1],
        'assertEquals' => [2, 2],
        'assertNotEquals' => [2, 2],
        'assertSame' => [2, 2],
        'assertNotSame' => [2, 2],
        'assertNull' => [1, 1],
        'assertNotNull' => [1, 1],
        'assertEmpty' => [1, 1],
        'assertNotEmpty' => [1, 1],
        'assertInstanceOf' => [2, 2],
        'assertIsArray' => [1, 1],
        'assertIsString' => [1, 1],
        'assertIsInt' => [1, 1],
        'assertIsBool' => [1, 1],
        'assertIsFloat' => [1, 1],
        'assertIsObject' => [1, 1],
        'assertStringContainsString' => [2, 2],
        'assertArrayHasKey' => [2, 2],
        'assertContains' => [2, 2],
        'assertCount' => [2, 2],
        'assertFileExists' => [1, 1],
        'assertGreaterThan' => [2, 2],
        'assertGreaterThanOrEqual' => [2, 2],
        'assertLessThan' => [2, 2],
        'assertLessThanOrEqual' => [2, 2],
        'assertJson' => [1, 1],
        'assertMatchesRegularExpression' => [2, 2],
    ];

    private ?string $currentTestMethod = null;
    /** @var array{method: string, hasMessage: bool}[] */
    private array $currentAssertions = [];

    /** @var Issue[] */
    private array $issues = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod) {
            if ($this->isTestMethod($node)) {
                $this->currentTestMethod = $node->name->name;
                $this->currentAssertions = [];
            }
            return null;
        }

        if ($this->currentTestMethod === null) {
            return null;
        }

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->name;
            if ($this->isAssertionMethod($methodName)) {
                $hasMessage = $this->assertionHasMessage($methodName, $node->args);
                $this->currentAssertions[] = [
                    'method' => $methodName,
                    'hasMessage' => $hasMessage,
                ];
            }
        }

        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $methodName = $node->name->name;
            if ($this->isAssertionMethod($methodName)) {
                $hasMessage = $this->assertionHasMessage($methodName, $node->args);
                $this->currentAssertions[] = [
                    'method' => $methodName,
                    'hasMessage' => $hasMessage,
                ];
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod && $this->currentTestMethod !== null) {
            if ($node->name->name === $this->currentTestMethod) {
                $this->checkForAssertionRoulette();
                $this->currentTestMethod = null;
                $this->currentAssertions = [];
            }
        }

        return null;
    }

    private function checkForAssertionRoulette(): void
    {
        // Must have at least 2 assertions
        if (count($this->currentAssertions) < 2) {
            return;
        }

        // Check if any assertion lacks a message
        $hasAnyWithoutMessage = false;
        foreach ($this->currentAssertions as $assertion) {
            if (!$assertion['hasMessage']) {
                $hasAnyWithoutMessage = true;
                break;
            }
        }

        if ($hasAnyWithoutMessage) {
            $count = count($this->currentAssertions);
            $withoutMessages = count(array_filter(
                $this->currentAssertions,
                static fn(array $a) => !$a['hasMessage']
            ));

            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod ?? 'unknown',
                type: 'assertion_roulette',
                message: "Assertion Roulette: Test has {$count} assertions with {$withoutMessages} without messages",
            );
        }
    }

    /**
     * @param string $methodName
     * @return bool
     */
    private function isAssertionMethod(string $methodName): bool
    {
        // Check if method name looks like an assertion
        return str_starts_with($methodName, 'assert');
    }

    /**
     * @param string $methodName
     * @param array<int, \PhpParser\Node\Arg|\PhpParser\Node\VariadicPlaceholder> $args
     * @return bool
     */
    private function assertionHasMessage(string $methodName, array $args): bool
    {
        if (!isset(self::ASSERTION_SIGNATURES[$methodName])) {
            // Use heuristic for unknown assertions: check if last arg is string literal
            return $this->hasLastArgAsStringLiteral($args);
        }

        [$minRequiredArgs, $messageArgIndex] = self::ASSERTION_SIGNATURES[$methodName];

        // If message arg index exists in the args, assertion has a message
        return isset($args[$messageArgIndex]);
    }

    /**
     * @param array<int, \PhpParser\Node\Arg|\PhpParser\Node\VariadicPlaceholder> $args
     * @return bool
     */
    private function hasLastArgAsStringLiteral(array $args): bool
    {
        if (count($args) === 0) {
            return false;
        }

        $lastArg = $args[count($args) - 1];
        if (!$lastArg instanceof \PhpParser\Node\Arg) {
            return false;
        }

        return $lastArg->value instanceof String_;
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
        $this->currentAssertions = [];
        $this->issues = [];
    }

    public function getType(): string
    {
        return 'assertion_roulette';
    }

    public function getName(): string
    {
        return 'Assertion Roulette';
    }
}

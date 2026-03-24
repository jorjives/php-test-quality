<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Stmt\ClassMethod;
use TestQualityAnalyzer\Issue;

class MagicNumberTestVisitor extends AbstractTestVisitor
{
    private const ASSERTION_METHODS = [
        'assertEquals',
        'assertSame',
        'assertGreaterThan',
        'assertGreaterThanOrEqual',
        'assertLessThan',
        'assertLessThanOrEqual',
        'assertCount',
    ];

    /**
     * Trivial values that are commonly used and self-documenting.
     * Includes: 0, 1, -1, common small integers, HTTP status codes, and pagination defaults.
     */
    public const DEFAULT_TRIVIAL_VALUES = [
        // Basic values (small integers often used as counts, indices)
        0, 1, -1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
        0.0, 1.0, -1.0,
        // HTTP status codes (most common)
        200, 201, 204,           // Success
        301, 302, 304,           // Redirection
        400, 401, 403, 404, 405, // Client errors
        409, 415, 422, 429,      // Client errors
        500, 502, 503, 504,      // Server errors
        // Common time values
        60, 3600, 86400,         // Seconds: minute, hour, day
        // Common boundary values used in testing
        255,                     // Max byte/varchar length
        // Common pagination values
        20, 25, 50, 100,         // Typical per-page defaults
    ];

    public function __construct(private readonly array $trivialValues = self::DEFAULT_TRIVIAL_VALUES) {}

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

        // Only check for magic numbers inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Detect magic numbers in assertion method calls
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->name;
            if (in_array($methodName, self::ASSERTION_METHODS, true)) {
                $this->checkAssertionForMagicNumbers($node, $methodName);
            }
        }

        return null;
    }

    private function checkAssertionForMagicNumbers(MethodCall $node, string $methodName): void
    {
        foreach ($node->args as $arg) {
            $value = $arg->value;

            if ($value instanceof Int_) {
                if (!in_array($value->value, $this->trivialValues, true)) {
                    $this->issues[] = new Issue(
                        file: $this->currentFile ?? 'unknown',
                        testName: $this->currentTestMethod,
                        type: 'magic_number_test',
                        message: "Magic number {$value->value} in {$methodName}() - consider using a named constant",
                    );
                }
            } elseif ($value instanceof Float_) {
                if (!in_array($value->value, $this->trivialValues, true)) {
                    $this->issues[] = new Issue(
                        file: $this->currentFile ?? 'unknown',
                        testName: $this->currentTestMethod,
                        type: 'magic_number_test',
                        message: "Magic number {$value->value} in {$methodName}() - consider using a named constant",
                    );
                }
            } elseif ($value instanceof UnaryMinus) {
                // Handle negative numbers: -100, -50, etc.
                if ($value->expr instanceof Int_) {
                    $negativeValue = -$value->expr->value;
                    if (!in_array($negativeValue, $this->trivialValues, true)) {
                        $this->issues[] = new Issue(
                            file: $this->currentFile ?? 'unknown',
                            testName: $this->currentTestMethod,
                            type: 'magic_number_test',
                            message: "Magic number {$negativeValue} in {$methodName}() - consider using a named constant",
                        );
                    }
                } elseif ($value->expr instanceof Float_) {
                    $negativeValue = -$value->expr->value;
                    if (!in_array($negativeValue, $this->trivialValues, true)) {
                        $this->issues[] = new Issue(
                            file: $this->currentFile ?? 'unknown',
                            testName: $this->currentTestMethod,
                            type: 'magic_number_test',
                            message: "Magic number {$negativeValue} in {$methodName}() - consider using a named constant",
                        );
                    }
                }
            }
        }
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
        return 'magic_number_test';
    }

    public function getName(): string
    {
        return 'Magic Number Test';
    }
}

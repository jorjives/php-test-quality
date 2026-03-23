<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\Issue;
use TestQualityAnalyzer\VisitorInterface;

final class InterfaceTestingVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    private ?string $currentFile = null;
    private ?string $currentTestMethod = null;

    // Maps variable names to their mocked types
    /** @var array<string, string> */
    private array $mockVariables = [];

    // Tracks variables that have willReturn configured
    /** @var array<string, bool> */
    private array $configuredReturns = [];

    // Tracks whether we've made assertions on mock return values
    private bool $hasAssertionsOnMockReturns = false;

    // Tracks assertInstanceOf calls for interfaces
    /** @var array<array{type: string, line: int}> */
    private array $instanceOfAssertions = [];

    // Tracks all assertions made in the current test
    private int $totalAssertions = 0;

    // Tracks if we've tested real logic (not just mocks)
    private bool $hasTestedRealLogic = false;

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
                $this->resetMethodState();
            }
            return null;
        }

        // Only check inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Detect createMock calls
        if ($node instanceof Assign && $this->isCreateMockCall($node->expr)) {
            $this->handleCreateMockAssignment($node);
        }

        // Detect willReturn calls on mocks
        if ($this->isWillReturnCall($node)) {
            $this->handleWillReturnCall($node);
        }

        // Count all assertions
        if ($this->isAssertion($node)) {
            $this->totalAssertions++;

            // Check for assertInstanceOf on interfaces
            if ($this->isAssertInstanceOfCall($node)) {
                $this->handleAssertInstanceOf($node);
            } elseif ($this->isAssertionOnMockReturn($node)) {
                $this->hasAssertionsOnMockReturns = true;
            } else {
                // Any other assertion means we're testing real logic
                $this->hasTestedRealLogic = true;
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // When leaving a test method, evaluate patterns
        if ($node instanceof ClassMethod && $this->currentTestMethod !== null) {
            if ($node->name->name === $this->currentTestMethod) {
                $this->evaluatePatterns();
                $this->currentTestMethod = null;
            }
        }

        return null;
    }

    private function resetMethodState(): void
    {
        $this->mockVariables = [];
        $this->configuredReturns = [];
        $this->hasAssertionsOnMockReturns = false;
        $this->instanceOfAssertions = [];
        $this->totalAssertions = 0;
        $this->hasTestedRealLogic = false;
    }

    private function handleCreateMockAssignment(Assign $node): void
    {
        // Extract variable name being assigned to
        if (!$node->var instanceof Variable || !is_string($node->var->name)) {
            return;
        }

        $varName = $node->var->name;
        $mockedType = $this->extractMockedType($node->expr);

        if ($mockedType && $this->looksLikeInterface($mockedType)) {
            $this->mockVariables[$varName] = $mockedType;
        }
    }

    private function handleWillReturnCall(Node $node): void
    {
        // Get the root variable being called
        $rootVar = $this->getRootVariable($node);
        if ($rootVar && isset($this->mockVariables[$rootVar])) {
            $this->configuredReturns[$rootVar] = true;
        }
    }

    private function handleAssertInstanceOf(Node $node): void
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return;
        }

        $expectedType = $this->extractExpectedTypeFromAssertion($node);
        if ($expectedType && $this->looksLikeInterface($expectedType)) {
            $this->instanceOfAssertions[] = [
                'type' => $expectedType,
                'line' => $node->getLine(),
            ];
        }
    }

    private function evaluatePatterns(): void
    {
        // Check for mock-only interface testing
        if ($this->hasMockOnlyInterfaceTesting()) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod ?? 'unknown',
                type: 'interface_mock_testing',
                message: 'Test mocks an interface and only asserts on configured mock returns - this tests the mock framework, not real code',
            );
        }

        // Check for implementation-only testing
        if ($this->onlyAssertsInterfaceImplementation()) {
            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod ?? 'unknown',
                type: 'interface_implementation_test',
                message: 'Test only asserts that class implements interface - use PHPStan/Psalm for static type checking instead',
            );
        }
    }

    private function hasMockOnlyInterfaceTesting(): bool
    {
        // Must have mocked an interface
        if (empty($this->mockVariables)) {
            return false;
        }

        // Must have made assertions on mock returns
        if (!$this->hasAssertionsOnMockReturns) {
            return false;
        }

        // Must NOT have tested real logic (beyond the mock)
        if ($this->hasTestedRealLogic) {
            return false;
        }

        // Must have configured returns for the mock
        if (empty($this->configuredReturns)) {
            return false;
        }

        return true;
    }

    private function onlyAssertsInterfaceImplementation(): bool
    {
        // Must have made assertInstanceOf calls for interfaces
        if (empty($this->instanceOfAssertions)) {
            return false;
        }

        // Only flag if this is the ONLY assertion made
        // If there are other assertions, we're testing behavior too
        $instanceOfCount = count($this->instanceOfAssertions);

        return $instanceOfCount === $this->totalAssertions;
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

    private function isCreateMockCall(Node $node): bool
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return false;
        }

        if ($node->name instanceof Identifier) {
            $methodName = $node->name->name;
            return $methodName === 'createMock';
        }

        return false;
    }

    private function extractMockedType(Node $node): ?string
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return null;
        }

        // Must have at least one argument
        if (count($node->args) < 1) {
            return null;
        }

        $firstArg = $node->args[0];
        if (!isset($firstArg->value)) {
            return null;
        }

        if (!$firstArg->value instanceof ClassConstFetch) {
            return null;
        }

        $constFetch = $firstArg->value;
        if (!$constFetch->class instanceof Name) {
            return null;
        }

        return $constFetch->class->toString();
    }

    private function isWillReturnCall(Node $node): bool
    {
        if (!$node instanceof MethodCall) {
            return false;
        }

        if ($node->name instanceof Identifier) {
            return $node->name->name === 'willReturn';
        }

        return false;
    }

    private function getRootVariable(Node $node): ?string
    {
        // For a chain like $mock->method('x')->willReturn('y')
        // We need to find the root variable 'mock'

        if ($node instanceof MethodCall) {
            // Traverse up the call chain
            $current = $node->var;

            while ($current instanceof MethodCall) {
                $current = $current->var;
            }

            if ($current instanceof Variable && is_string($current->name)) {
                return $current->name;
            }
        }

        return null;
    }

    private function isAssertion(Node $node): bool
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return false;
        }

        if ($node->name instanceof Identifier) {
            $methodName = $node->name->name;
            return str_starts_with($methodName, 'assert') || str_starts_with($methodName, 'expectException');
        }

        return false;
    }

    private function isAssertInstanceOfCall(Node $node): bool
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return false;
        }

        if (!$node->name instanceof Identifier) {
            return false;
        }

        return $node->name->name === 'assertInstanceOf';
    }

    private function isAssertionOnMockReturn(Node $node): bool
    {
        // Check if this is an assertion directly on a mock variable's return value
        // e.g., $this->assertEquals('value', $sut->doSomething())

        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return false;
        }

        if (!$node->name instanceof Identifier) {
            return false;
        }

        $methodName = $node->name->name;

        // Only interested in comparison assertions
        if (!in_array($methodName, ['assertEquals', 'assertSame', 'assertNotEquals', 'assertNotSame'], true)) {
            return false;
        }

        // Check if any argument is a method call on a mock variable
        foreach ($node->args as $arg) {
            // Skip variadic placeholders, check only regular arguments
            if (isset($arg->value) && $this->isMethodCallOnMock($arg->value)) {
                return true;
            }
        }

        return false;
    }

    private function isMethodCallOnMock(Node $node): bool
    {
        if (!$node instanceof MethodCall) {
            return false;
        }

        // Get the root variable
        $root = $node->var;
        while ($root instanceof MethodCall) {
            $root = $root->var;
        }

        if ($root instanceof Variable && is_string($root->name)) {
            return isset($this->mockVariables[$root->name]);
        }

        return false;
    }

    private function extractExpectedTypeFromAssertion(MethodCall|StaticCall $node): ?string
    {
        // assertInstanceOf(Class::class, $var)
        // The first argument is the expected class
        if (count($node->args) < 1) {
            return null;
        }

        $firstArg = $node->args[0];
        if (!isset($firstArg->value)) {
            return null;
        }

        if (!$firstArg->value instanceof ClassConstFetch) {
            return null;
        }

        $constFetch = $firstArg->value;
        if (!$constFetch->class instanceof Name) {
            return null;
        }

        return $constFetch->class->toString();
    }

    private function looksLikeInterface(string $className): bool
    {
        // Extract just the class name from fully qualified names
        $shortName = basename(str_replace('\\', '/', $className));

        // Primary: Interface suffix (most reliable)
        if (str_ends_with($shortName, 'Interface')) {
            return true;
        }

        // PSR interfaces (standardized)
        if (str_starts_with($className, 'Psr\\')) {
            return true;
        }

        // Secondary: Other common patterns (less certain, but reasonable heuristics)
        // These should only match if they're not concrete implementations
        $interfacePatterns = [
            '/^I[A-Z]/',        // IFoo pattern (less common in PHP but still interface-like)
            '/able$/',           // Comparable, Iterable, Serializable pattern
        ];

        foreach ($interfacePatterns as $pattern) {
            if (preg_match($pattern, $shortName)) {
                return true;
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
        $this->resetMethodState();
        $this->issues = [];
    }

    public function getType(): string
    {
        // This visitor detects multiple interface testing anti-patterns
        return 'interface_testing';
    }

    public function getName(): string
    {
        return 'Interface Testing Anti-Patterns';
    }
}

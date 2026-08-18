<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use TestQualityAnalyzer\Issue;

class ExistenceCheckVisitor extends AbstractTestVisitor
{
    private const BOOLEAN_ASSERTIONS = ['assertTrue', 'assertFalse'];

    private const EXISTENCE_FUNCTIONS = ['class_exists', 'interface_exists', 'method_exists', 'function_exists'];

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

        if (($node instanceof MethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier) {
            $methodName = $node->name->name;

            if (in_array($methodName, self::BOOLEAN_ASSERTIONS, true)) {
                $this->checkBooleanAssertion($node, $methodName);
            }
        }

        return null;
    }

    private function checkBooleanAssertion(MethodCall|StaticCall $node, string $methodName): void
    {
        if (count($node->args) < 1) {
            return;
        }

        $arg = $node->args[0]->value;

        if (!$this->isExistenceCheckCall($arg)) {
            return;
        }

        $function = $this->functionName($arg);

        $this->issues[] = new Issue(
            file: $this->currentFile ?? 'unknown',
            testName: $this->currentTestMethod ?? 'unknown',
            type: 'existence_check_assertion',
            message: "Test only asserts {$function}() - this tests the autoloader/reflection, not real code behaviour",
        );
    }

    private function isExistenceCheckCall(Node $node): bool
    {
        return $this->functionName($node) !== null;
    }

    private function functionName(Node $node): ?string
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return null;
        }

        $name = $node->name->toString();

        return in_array($name, self::EXISTENCE_FUNCTIONS, true) ? $name : null;
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
        return 'existence_check_assertion';
    }

    public function getName(): string
    {
        return 'Existence Check Assertion';
    }
}

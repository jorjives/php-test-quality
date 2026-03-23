<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use TestQualityAnalyzer\Issue;

class ConstructorInitializationVisitor extends AbstractTestVisitor
{
    private const TEST_BASE_CLASSES = [
        'TestCase',
        'PHPUnit\\Framework\\TestCase',
        'PHPUnit_Framework_TestCase',
    ];

    private ?string $currentTestClass = null;
    private bool $isCurrentClassTestClass = false;

    /** @var Issue[] */
    private array $issues = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Class_) {
            $this->currentTestClass = $node->name?->name;
            $this->isCurrentClassTestClass = $this->isTestClass($node);
            return null;
        }

        if (!$this->isCurrentClassTestClass || $this->currentTestClass === null) {
            return null;
        }

        if ($node instanceof ClassMethod && $node->name->name === '__construct') {
            if ($this->hasInitializationLogic($node)) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestClass,
                    type: 'constructor_initialization',
                    message: 'Test class uses constructor for initialization - use setUp() instead',
                );
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Class_ && $this->currentTestClass !== null) {
            if ($node->name?->name === $this->currentTestClass) {
                $this->currentTestClass = null;
                $this->isCurrentClassTestClass = false;
            }
        }

        return null;
    }

    private function isTestClass(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parentName = $this->getClassName($node->extends);

        foreach (self::TEST_BASE_CLASSES as $baseClass) {
            if ($this->classNameMatches($parentName, $baseClass)) {
                return true;
            }
        }

        return false;
    }

    private function hasInitializationLogic(ClassMethod $node): bool
    {
        // Empty constructor is OK
        if (empty($node->stmts)) {
            return false;
        }

        // Filter out Nop statements (comment-only blocks)
        $meaningfulStmts = array_filter(
            $node->stmts,
            static fn ($stmt) => !$stmt instanceof Nop
        );

        // If only comments remain, that's OK
        if (empty($meaningfulStmts)) {
            return false;
        }

        // If there's only one meaningful statement and it's a call to parent::__construct(), that's OK
        if (count($meaningfulStmts) === 1) {
            $stmt = reset($meaningfulStmts);
            if ($stmt instanceof Expression && $stmt->expr instanceof StaticCall) {
                $staticCall = $stmt->expr;
                if ($this->isParentConstructorCall($staticCall)) {
                    return false;
                }
            }
        }

        // Any other content (either multiple statements or non-parent-constructor logic) is a problem
        return true;
    }

    private function isParentConstructorCall(StaticCall $call): bool
    {
        // Check if it's parent::__construct()
        if (!$call->class instanceof Name) {
            return false;
        }

        $className = $call->class->toString();
        if ($className !== 'parent') {
            return false;
        }

        if (!$call->name instanceof Identifier) {
            return false;
        }

        return $call->name->name === '__construct';
    }

    private function getClassName(Node $node): string
    {
        if ($node instanceof Name) {
            return $node->toString();
        }

        return 'unknown';
    }

    private function classNameMatches(string $fullName, string $baseClass): bool
    {
        // Exact match (fully qualified)
        if ($fullName === $baseClass) {
            return true;
        }

        // Simple name match (last part of fully qualified name)
        $parts = explode('\\', $fullName);
        $simpleName = end($parts);

        $baseParts = explode('\\', $baseClass);
        $baseSimpleName = end($baseParts);

        return $simpleName === $baseSimpleName;
    }

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function reset(): void
    {
        $this->currentFile = null;
        $this->currentTestClass = null;
        $this->isCurrentClassTestClass = false;
        $this->issues = [];
    }

    public function getType(): string
    {
        return 'constructor_initialization';
    }

    public function getName(): string
    {
        return 'Constructor Initialization';
    }
}

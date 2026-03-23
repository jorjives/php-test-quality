<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\Issue;
use TestQualityAnalyzer\VisitorInterface;

final class MysteryGuestVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    private const FILE_FUNCTIONS = [
        'file_get_contents',
        'file',
        'fopen',
        'fread',
        'fgets',
        'readfile',
        'parse_ini_file',
        'simplexml_load_file',
    ];

    private const DATABASE_METHODS = [
        'query',
        'execute',
        'fetch',
        'fetchAll',
        'find',
        'findBy',
        'findOneBy',
        'findAll',
        'createQueryBuilder',
    ];

    private const EXCLUDED_METHODS = [
        'setUp',
        'tearDown',
        'setUpBeforeClass',
        'tearDownAfterClass',
    ];

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
            $methodName = $node->name->name;

            // Skip setup/teardown methods
            if (in_array($methodName, self::EXCLUDED_METHODS, true)) {
                return null;
            }

            if ($this->isTestMethod($node)) {
                $this->currentTestMethod = $methodName;
            }
            return null;
        }

        // Only check inside test methods
        if ($this->currentTestMethod === null) {
            return null;
        }

        // Detect file function calls
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $funcName = $node->name->toString();
            if (in_array($funcName, self::FILE_FUNCTIONS, true)) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'mystery_guest',
                    message: "Test uses {$funcName}() to access external resource - data should be explicit in test",
                );
            }
        }

        // Detect include/require statements
        if ($node instanceof Node\Expr\Include_) {
            $includeType = match ($node->type) {
                Node\Expr\Include_::TYPE_INCLUDE => 'include',
                Node\Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
                Node\Expr\Include_::TYPE_REQUIRE => 'require',
                Node\Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
                default => 'include',
            };

            $this->issues[] = new Issue(
                file: $this->currentFile ?? 'unknown',
                testName: $this->currentTestMethod,
                type: 'mystery_guest',
                message: "Test uses {$includeType} to load external file - data should be explicit in test",
            );
        }

        // Detect database method calls
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->name;
            if (in_array($methodName, self::DATABASE_METHODS, true)) {
                $this->issues[] = new Issue(
                    file: $this->currentFile ?? 'unknown',
                    testName: $this->currentTestMethod,
                    type: 'mystery_guest',
                    message: "Test uses {$methodName}() to access database - external state makes tests fragile",
                );
            }
        }

        return null;
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
        return 'mystery_guest';
    }

    public function getName(): string
    {
        return 'Mystery Guest';
    }
}

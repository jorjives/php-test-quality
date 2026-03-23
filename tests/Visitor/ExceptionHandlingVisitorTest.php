<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\ExceptionHandlingVisitor;

final class ExceptionHandlingVisitorTest extends TestCase
{
    private function analyzeCode(string $code): ExceptionHandlingVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new ExceptionHandlingVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsTryCatchInTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        try {
            throwSomething();
        } catch (RuntimeException $e) {
            $this->assertEquals('error', $e->getMessage());
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('exception_handling', $issues[0]->type);
    }

    public function testDetectsTryCatchWithMultipleCatchBlocks(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        try {
            throwSomething();
        } catch (RuntimeException $e) {
            $this->assertEquals('error', $e->getMessage());
        } catch (LogicException $e) {
            $this->assertEquals('logic', $e->getMessage());
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('exception_handling', $issues[0]->type);
    }

    public function testIgnoresTryCatchOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        try {
            throwSomething();
        } catch (RuntimeException $e) {
            // Handle error
        }
    }

    public function testSomething(): void
    {
        $this->helperMethod();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresTryCatchInSetUp(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    protected function setUp(): void
    {
        try {
            setupSomething();
        } catch (RuntimeException $e) {
            // Handle setup error
        }
    }

    public function testSomething(): void
    {
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresTryCatchInTearDown(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    protected function tearDown(): void
    {
        try {
            cleanupSomething();
        } catch (RuntimeException $e) {
            // Handle cleanup error
        }
    }

    public function testSomething(): void
    {
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testDetectsMultipleTryCatchBlocks(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        try {
            firstOperation();
        } catch (RuntimeException $e) {
            $this->assertEquals('error1', $e->getMessage());
        }

        try {
            secondOperation();
        } catch (LogicException $e) {
            $this->assertEquals('error2', $e->getMessage());
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('exception_handling', $issues[0]->type);
        self::assertSame('testSomething', $issues[1]->testName);
        self::assertSame('exception_handling', $issues[1]->type);
    }

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithTryCatch(): void
    {
        try {
            throwSomething();
        } catch (RuntimeException $e) {
            $this->assertEquals('error', $e->getMessage());
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsExceptionHandling(): void
    {
        $visitor = new ExceptionHandlingVisitor();

        self::assertSame('exception_handling', $visitor->getType());
    }

    public function testGetNameReturnsExceptionHandling(): void
    {
        $visitor = new ExceptionHandlingVisitor();

        self::assertSame('Exception Handling', $visitor->getName());
    }

    public function testDetectsTestMethodByTestAttribute(): void
    {
        $code = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test;

class SomeTest extends TestCase {
    #[Test]
    public function itDoesSomething(): void
    {
        try {
            throwSomething();
        } catch (RuntimeException $e) {
            $this->assertEquals('error', $e->getMessage());
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('itDoesSomething', $issues[0]->testName);
        self::assertSame('exception_handling', $issues[0]->type);
    }

    public function testDetectsNestedTryCatch(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        try {
            try {
                innerOperation();
            } catch (RuntimeException $e) {
                $this->assertEquals('inner', $e->getMessage());
            }
        } catch (LogicException $e) {
            $this->assertEquals('outer', $e->getMessage());
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('exception_handling', $issues[0]->type);
        self::assertSame('testSomething', $issues[1]->testName);
        self::assertSame('exception_handling', $issues[1]->type);
    }
}

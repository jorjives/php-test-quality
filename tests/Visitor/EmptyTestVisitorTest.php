<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\EmptyTestVisitor;

final class EmptyTestVisitorTest extends TestCase
{
    private function analyzeCode(string $code): EmptyTestVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new EmptyTestVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsEmptyTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testEmpty(): void
    {
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect one empty test');
        self::assertSame('testEmpty', $issues[0]->testName);
        self::assertSame('empty_test', $issues[0]->type);
        self::assertStringContainsString('no executable statements', $issues[0]->message);
    }

    public function testDetectsEmptyTestMethodWithOnlyComments(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testEmptyWithComments(): void
    {
        // This test does nothing
        // Just comments here
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect test with only comments as empty');
        self::assertSame('testEmptyWithComments', $issues[0]->testName);
        self::assertSame('empty_test', $issues[0]->type);
    }

    public function testIgnoresTestMethodWithStatements(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithAssertions(): void
    {
        $result = 1 + 1;
        self::assertEquals(2, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag test methods with statements');
    }

    public function testIgnoresNonTestMethods(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function helperMethod(): void
    {
    }

    public function testSomething(): void
    {
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag non-test methods');
    }

    public function testDetectsEmptyMethodWithTestAttribute(): void
    {
        $code = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test;

class SomeTest extends TestCase {
    #[Test]
    public function someTestWithAttribute(): void
    {
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect empty test with #[Test] attribute');
        self::assertSame('someTestWithAttribute', $issues[0]->testName);
        self::assertSame('empty_test', $issues[0]->type);
    }

    public function testIgnoresTestWithSingleAssertion(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSingleAssertion(): void
    {
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag test with at least one assertion');
    }

    public function testIgnoresTestWithMultipleStatements(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testMultipleStatements(): void
    {
        $a = 1;
        $b = 2;
        $c = $a + $b;
        self::assertEquals(3, $c);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag test with multiple statements');
    }

    public function testDetectsMultipleEmptyTests(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testEmptyOne(): void
    {
    }

    public function testWithAssertion(): void
    {
        self::assertTrue(true);
    }

    public function testEmptyTwo(): void
    {
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues, 'Should detect multiple empty tests');
        self::assertSame('testEmptyOne', $issues[0]->testName);
        self::assertSame('testEmptyTwo', $issues[1]->testName);
    }

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testEmpty(): void
    {
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues(), 'Should detect one empty test before reset');

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues(), 'reset() should clear all issues');
    }

    public function testGetTypeReturnsEmptyTest(): void
    {
        $visitor = new EmptyTestVisitor();

        self::assertSame('empty_test', $visitor->getType(), 'getType() should return empty_test');
    }

    public function testGetNameReturnsEmptyTest(): void
    {
        $visitor = new EmptyTestVisitor();

        self::assertSame('Empty Test', $visitor->getName(), 'getName() should return Empty Test');
    }

    public function testSetCurrentFileStoresFileName(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testEmpty(): void
    {
    }
}
PHP;

        $visitor = new EmptyTestVisitor();
        $visitor->setCurrentFile('TestFile.php');

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $issues = $visitor->getIssues();
        self::assertCount(1, $issues, 'Should detect one empty test');
        self::assertSame('TestFile.php', $issues[0]->file, 'File name should be stored correctly');
    }
}

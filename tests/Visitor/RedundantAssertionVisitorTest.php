<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\RedundantAssertionVisitor;

final class RedundantAssertionVisitorTest extends TestCase
{
    private function analyzeCode(string $code): RedundantAssertionVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new RedundantAssertionVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testGetTypeReturnsRedundantAssertion(): void
    {
        $visitor = new RedundantAssertionVisitor();

        self::assertSame('redundant_assertion', $visitor->getType());
    }

    public function testGetNameReturnsRedundantAssertion(): void
    {
        $visitor = new RedundantAssertionVisitor();

        self::assertSame('Redundant Assertion', $visitor->getName());
    }

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 5);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        // Will have issues once detection is implemented
        $visitor->reset();

        self::assertCount(0, $visitor->getIssues());
    }

    public function testDetectsIdenticalIntegerLiterals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 5);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString('assertEquals', $issues[0]->message);
    }

    public function testDetectsIdenticalStringLiterals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertSame('foo', 'foo');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString('assertSame', $issues[0]->message);
    }

    public function testDetectsIdenticalFloatLiterals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(3.14, 3.14);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
    }

    public function testDetectsSameVariable(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $value = 'test';
        $this->assertSame($value, $value);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString('$value', $issues[0]->message);
    }

    public function testDetectsAssertTrueWithTrue(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString('assertTrue(true)', $issues[0]->message);
    }

    public function testDetectsAssertFalseWithFalse(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertFalse(false);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString('assertFalse(false)', $issues[0]->message);
    }

    public function testDetectsAssertNullWithNull(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertNull(null);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString('assertNull(null)', $issues[0]->message);
    }

    public function testDetectsAssertEmptyWithEmptyArray(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEmpty([]);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString('assertEmpty([])', $issues[0]->message);
    }

    public function testDetectsAssertEmptyWithEmptyString(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEmpty('');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('redundant_assertion', $issues[0]->type);
        self::assertStringContainsString("assertEmpty('')", $issues[0]->message);
    }

    public function testIgnoresDifferentLiterals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 10);
        $this->assertSame('foo', 'bar');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresDifferentVariables(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $a = 'foo';
        $b = 'bar';
        $this->assertSame($a, $b);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresAssertTrueWithVariable(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = doSomething();
        $this->assertTrue($result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresNonTestMethods(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        $this->assertEquals(5, 5);
    }

    public function testSomething(): void
    {
        $this->assertTrue(true === true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        // Only checks test methods, helper method ignored
        // The test method has a comparison expression, not a literal
        self::assertCount(0, $visitor->getIssues());
    }

    public function testDetectsTestMethodByAttribute(): void
    {
        $code = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test;

class SomeTest extends TestCase {
    #[Test]
    public function itDoesSomething(): void
    {
        $this->assertEquals(5, 5);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('itDoesSomething', $issues[0]->testName);
    }
}

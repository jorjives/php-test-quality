<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\AssertionRouletteVisitor;

final class AssertionRouletteVisitorTest extends TestCase
{
    private function analyzeCode(string $code): AssertionRouletteVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new AssertionRouletteVisitor();
        $visitor->setCurrentFile('test.php');
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testGetTypeReturnsAssertionRoulette(): void
    {
        $visitor = new AssertionRouletteVisitor();

        self::assertSame('assertion_roulette', $visitor->getType());
    }

    public function testGetNameReturnsAssertionRoulette(): void
    {
        $visitor = new AssertionRouletteVisitor();

        self::assertSame('Assertion Roulette', $visitor->getName());
    }

    public function testResetClearsState(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 10);
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $visitor->reset();

        self::assertCount(0, $visitor->getIssues());
    }

    public function testDetectsMultipleAssertionsWithoutMessages(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 10);
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('assertion_roulette', $issues[0]->type);
        self::assertStringContainsString('Assertion Roulette', $issues[0]->message);
    }

    public function testIgnoresWhenAllAssertionsHaveMessages(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 10, 'Should be equal');
        $this->assertTrue(true, 'Should be true');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testIgnoresSingleAssertionWithoutMessage(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 10);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testDetectsMixedAssertionsWithAndWithoutMessages(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 10, 'First assertion');
        $this->assertTrue(true);
        $this->assertNull(null, 'Third assertion');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('assertion_roulette', $issues[0]->type);
        self::assertStringContainsString('without messages', $issues[0]->message);
    }

    public function testHandlesStaticAssertCalls(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        self::assertEquals(5, 10);
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('assertion_roulette', $issues[0]->type);
    }

    public function testHandlesUnknownAssertionsWithHeuristic(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertCustom($value);
        $this->assertAnotherCustom($result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Both unknown assertions without string message, should flag
        self::assertCount(1, $issues);
        self::assertSame('assertion_roulette', $issues[0]->type);
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
        $this->assertEquals(5, 10);
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('itDoesSomething', $issues[0]->testName);
    }

    public function testIgnoresNonTestMethods(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        $this->assertEquals(5, 10);
        $this->assertTrue(true);
    }

    public function testSomething(): void
    {
        $this->assertEquals(1, 1);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Only test methods analyzed, helper method ignored
        // testSomething has 1 assertion, so no roulette issue
        self::assertCount(0, $issues);
    }

    public function testAllAssertionTypesWithMessages(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertTrue(true, 'Should be true');
        $this->assertFalse(false, 'Should be false');
        $this->assertEquals(1, 2, 'Should equal');
        $this->assertSame('a', 'b', 'Should be same');
        $this->assertNull(null, 'Should be null');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // All assertions have messages
        self::assertCount(0, $issues);
    }

    public function testMultipleTestMethods(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testFirst(): void
    {
        $this->assertEquals(1, 2);
        $this->assertTrue(true);
    }

    public function testSecond(): void
    {
        $this->assertEquals(3, 4);
        $this->assertFalse(false);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Both test methods have multiple assertions without messages
        self::assertCount(2, $issues);
        self::assertSame('testFirst', $issues[0]->testName);
        self::assertSame('testSecond', $issues[1]->testName);
    }

    public function testCountAssertionsCountsCorrectly(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertTrue(true);
        $this->assertTrue(false);
        $this->assertFalse(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // 3 assertions without messages, all in one method
        self::assertCount(1, $issues);
    }

    public function testHandlesComplexAssertionChains(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = $this->doSomething();
        $this->assertNotNull($result);
        $this->assertNotEmpty($result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Two assertions without messages
        self::assertCount(1, $issues);
    }

    public function testDetectsAssertWithStringLiteralAsMessage(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(5, 10, 'Has message');
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Second assertion has no message, but first does
        self::assertCount(1, $issues);
    }
}

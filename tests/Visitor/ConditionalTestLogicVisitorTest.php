<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\ConditionalTestLogicVisitor;

final class ConditionalTestLogicVisitorTest extends TestCase
{
    private function analyzeCode(string $code): ConditionalTestLogicVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new ConditionalTestLogicVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsIfStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = getValue();
        if ($result > 0) {
            $this->assertTrue(true);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the if statement');
        self::assertSame(
            'testSomething',
            $issues[0]->testName,
            'Issue should name the test containing the if statement'
        );
        self::assertSame('conditional_test_logic', $issues[0]->type, 'Issue type should be conditional_test_logic');
    }

    public function testDetectsSwitchStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $value = getValue();
        switch ($value) {
            case 1:
                $this->assertTrue(true);
                break;
            default:
                $this->assertFalse(false);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the switch statement');
        self::assertSame('testSomething', $issues[0]->testName, 'Issue should name the test containing the switch');
        self::assertSame('conditional_test_logic', $issues[0]->type, 'Issue type should be conditional_test_logic');
        self::assertStringContainsString('switch', $issues[0]->message, 'Issue message should mention the switch');
    }

    public function testDetectsTernaryOperator(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $value = getValue();
        $expected = $value > 0 ? 'positive' : 'non-positive';
        $this->assertSame($expected, categorize($value));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the ternary operator');
        self::assertSame('testSomething', $issues[0]->testName, 'Issue should name the test containing the ternary');
        self::assertSame('conditional_test_logic', $issues[0]->type, 'Issue type should be conditional_test_logic');
        self::assertStringContainsString('ternary', $issues[0]->message, 'Issue message should mention the ternary');
    }

    public function testDetectsNestedConditionals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $value = getValue();
        if ($value > 0) {
            if ($value > 10) {
                $this->assertTrue(true);
            }
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues, 'Should flag both the outer and inner if statements');
        self::assertSame('testSomething', $issues[0]->testName, 'Outer issue should name the test');
        self::assertSame(
            'conditional_test_logic',
            $issues[0]->type,
            'Outer issue type should be conditional_test_logic'
        );
        self::assertSame('testSomething', $issues[1]->testName, 'Inner issue should name the test');
        self::assertSame(
            'conditional_test_logic',
            $issues[1]->type,
            'Inner issue type should be conditional_test_logic'
        );
    }

    public function testDetectsMultipleConditionals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        if ($a) { $this->assertTrue(true); }
        switch ($b) { case 1: break; }
        $c = $d ? 1 : 2;
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(3, $issues);
    }

    public function testIgnoresConditionalOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): string
    {
        if (true) {
            return 'yes';
        }
        return 'no';
    }

    public function testSomething(): void
    {
        self::assertSame('yes', $this->helperMethod());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresConditionalInSetUp(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    protected function setUp(): void
    {
        if (extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug interferes');
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

    public function testIgnoresConditionalInTearDown(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
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

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithIf(): void
    {
        if (true) { $this->assertTrue(true); }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues(), 'Should flag the if statement before reset');

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues(), 'reset() should clear all issues');
    }

    public function testGetTypeReturnsConditionalTestLogic(): void
    {
        $visitor = new ConditionalTestLogicVisitor();

        self::assertSame('conditional_test_logic', $visitor->getType());
    }

    public function testGetNameReturnsConditionalTestLogic(): void
    {
        $visitor = new ConditionalTestLogicVisitor();

        self::assertSame('Conditional Test Logic', $visitor->getName());
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
        if (true) {
            $this->assertTrue(true);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the if statement in the #[Test] method');
        self::assertSame('itDoesSomething', $issues[0]->testName, 'Issue should name the #[Test] method');
        self::assertSame('conditional_test_logic', $issues[0]->type, 'Issue type should be conditional_test_logic');
    }
}

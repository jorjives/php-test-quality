<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\MagicNumberTestVisitor;

final class MagicNumberTestVisitorTest extends TestCase
{
    private function analyzeCode(string $code): MagicNumberTestVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new MagicNumberTestVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsMagicNumberInAssertEquals(): void
    {
        // Use a number not in TRIVIAL_VALUES (86400 is trivial as it's seconds in a day)
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(12345, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('magic_number_test', $issues[0]->type);
        self::assertStringContainsString('12345', $issues[0]->message);
    }

    public function testDetectsMagicFloatInAssertEquals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(3.14159, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('magic_number_test', $issues[0]->type);
        self::assertStringContainsString('3.14159', $issues[0]->message);
    }

    public function testIgnoresTrivialValues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(0, $result);
        $this->assertEquals(1, $count);
        $this->assertEquals(-1, $index);
        $this->assertCount(0, $items);
        $this->assertCount(1, $items);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testDetectsMagicNumberInAssertSame(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertSame(42, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertStringContainsString('assertSame', $issues[0]->message);
    }

    public function testDetectsMagicNumberInComparisonAssertions(): void
    {
        // Use numbers not in TRIVIAL_VALUES (100 is trivial as a common pagination value)
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertGreaterThan(999, $result);
        $this->assertLessThan(5000, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
        self::assertStringContainsString('999', $issues[0]->message);
        self::assertStringContainsString('5000', $issues[1]->message);
    }

    public function testDetectsMagicNumberInAssertCount(): void
    {
        // Use a number not in TRIVIAL_VALUES (10 is trivial as a common small integer)
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertCount(42, $items);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertStringContainsString('42', $issues[0]->message);
        self::assertStringContainsString('assertCount', $issues[0]->message);
    }

    public function testIgnoresMagicNumbersOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        $this->assertEquals(86400, $result);
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

    public function testDetectsMagicNumberInTestWithAttribute(): void
    {
        $code = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test;

class SomeTest extends TestCase {
    #[Test]
    public function itCalculatesCorrectly(): void
    {
        $this->assertEquals(365, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('itCalculatesCorrectly', $issues[0]->testName);
    }

    public function testResetClearsIssues(): void
    {
        // Use a number not in TRIVIAL_VALUES
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(12345, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsMagicNumberTest(): void
    {
        $visitor = new MagicNumberTestVisitor();

        self::assertSame('magic_number_test', $visitor->getType());
    }

    public function testGetNameReturnsMagicNumberTest(): void
    {
        $visitor = new MagicNumberTestVisitor();

        self::assertSame('Magic Number Test', $visitor->getName());
    }

    public function testDetectsMultipleMagicNumbersInSameTest(): void
    {
        // Use numbers not in TRIVIAL_VALUES (86400, 3600 are trivial as common time values)
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(12345, $seconds);
        $this->assertEquals(67890, $hourSeconds);
        $this->assertCount(999, $hours);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(3, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('testSomething', $issues[1]->testName);
        self::assertSame('testSomething', $issues[2]->testName);
    }

    public function testDetectsNegativeMagicNumbers(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(-100, $result);
        $this->assertGreaterThan(-50, $value);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
        self::assertStringContainsString('-100', $issues[0]->message);
        self::assertStringContainsString('-50', $issues[1]->message);
    }

    public function testCustomAllowlistPermitsSpecificNumbers(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(12345, $result);
    }
}
PHP;

        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        // 12345 is normally flagged, but our custom allowlist includes it
        $visitor = new MagicNumberTestVisitor(trivialValues: [0, 1, 12345]);
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        self::assertCount(0, $visitor->getIssues(), 'Custom allowlist should permit 12345');
    }
}

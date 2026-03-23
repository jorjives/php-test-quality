<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\LongTestVisitor;

final class LongTestVisitorTest extends TestCase
{
    private function analyzeCode(string $code): LongTestVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new LongTestVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsLongTestMethod(): void
    {
        // 42 lines total (start to end), exceeds 40 line threshold
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testVeryLongMethod(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        $p = 16;
        $q = 17;
        $r = 18;
        $s = 19;
        $t = 20;
        $u = 21;
        $v = 22;
        $w = 23;
        $x = 24;
        $y = 25;
        $z = 26;
        $aa = 27;
        $bb = 28;
        $cc = 29;
        $dd = 30;
        $ee = 31;
        $ff = 32;
        $gg = 33;
        $hh = 34;
        $ii = 35;
        $jj = 36;
        $kk = 37;
        $ll = 38;
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect one long test');
        self::assertSame('testVeryLongMethod', $issues[0]->testName);
        self::assertSame('long_test', $issues[0]->type);
        self::assertStringContainsString('42 lines', $issues[0]->message);
        self::assertStringContainsString('40', $issues[0]->message);
    }

    public function testIgnoresShortTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testShortMethod(): void
    {
        $result = 1 + 1;
        self::assertEquals(2, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag short test methods');
    }

    public function testIgnoresLongNonTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): array
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        $p = 16;
        $q = 17;
        $r = 18;
        $s = 19;
        $t = 20;
        $u = 21;
        $v = 22;
        $w = 23;
        $x = 24;
        $y = 25;
        $z = 26;
        $aa = 27;
        $bb = 28;
        return [$a, $b];
    }

    public function testShort(): void
    {
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag non-test methods even if long');
    }

    public function testDetectsLongMethodWithTestAttribute(): void
    {
        // Method named without 'test' prefix but with #[Test] attribute, exceeds 40 line threshold
        $code = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test;

class SomeTest extends TestCase {
    #[Test]
    public function veryLongMethodWithAttribute(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        $p = 16;
        $q = 17;
        $r = 18;
        $s = 19;
        $t = 20;
        $u = 21;
        $v = 22;
        $w = 23;
        $x = 24;
        $y = 25;
        $z = 26;
        $aa = 27;
        $bb = 28;
        $cc = 29;
        $dd = 30;
        $ee = 31;
        $ff = 32;
        $gg = 33;
        $hh = 34;
        $ii = 35;
        $jj = 36;
        $kk = 37;
        $ll = 38;
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect long test with #[Test] attribute');
        self::assertSame('veryLongMethodWithAttribute', $issues[0]->testName);
        self::assertSame('long_test', $issues[0]->type);
    }

    public function testIgnoresMethodExactlyAtThreshold(): void
    {
        // Method that is exactly 40 lines (should NOT flag)
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testExactlyFortyLines(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        $p = 16;
        $q = 17;
        $r = 18;
        $s = 19;
        $t = 20;
        $u = 21;
        $v = 22;
        $w = 23;
        $x = 24;
        $y = 25;
        $z = 26;
        $aa = 27;
        $bb = 28;
        $cc = 29;
        $dd = 30;
        $ee = 31;
        $ff = 32;
        $gg = 33;
        $hh = 34;
        $ii = 35;
        $jj = 36;
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag methods exactly at threshold');
    }

    public function testResetClearsIssues(): void
    {
        // 42 lines - exceeds 40 line threshold
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testVeryLongMethod(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        $p = 16;
        $q = 17;
        $r = 18;
        $s = 19;
        $t = 20;
        $u = 21;
        $v = 22;
        $w = 23;
        $x = 24;
        $y = 25;
        $z = 26;
        $aa = 27;
        $bb = 28;
        $cc = 29;
        $dd = 30;
        $ee = 31;
        $ff = 32;
        $gg = 33;
        $hh = 34;
        $ii = 35;
        $jj = 36;
        $kk = 37;
        $ll = 38;
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues(), 'reset() should clear all issues');
    }

    public function testGetTypeReturnsLongTest(): void
    {
        $visitor = new LongTestVisitor();

        self::assertSame('long_test', $visitor->getType());
    }

    public function testGetNameReturnsLongTest(): void
    {
        $visitor = new LongTestVisitor();

        self::assertSame('Long Test', $visitor->getName());
    }

    public function testDetectsMultipleLongTests(): void
    {
        // Both methods exceed 40 line threshold
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testFirstLongMethod(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        $p = 16;
        $q = 17;
        $r = 18;
        $s = 19;
        $t = 20;
        $u = 21;
        $v = 22;
        $w = 23;
        $x = 24;
        $y = 25;
        $z = 26;
        $aa = 27;
        $bb = 28;
        $cc = 29;
        $dd = 30;
        $ee = 31;
        $ff = 32;
        $gg = 33;
        $hh = 34;
        $ii = 35;
        $jj = 36;
        $kk = 37;
        $ll = 38;
        self::assertTrue(true);
    }

    public function testSecondLongMethod(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        $p = 16;
        $q = 17;
        $r = 18;
        $s = 19;
        $t = 20;
        $u = 21;
        $v = 22;
        $w = 23;
        $x = 24;
        $y = 25;
        $z = 26;
        $aa = 27;
        $bb = 28;
        $cc = 29;
        $dd = 30;
        $ee = 31;
        $ff = 32;
        $gg = 33;
        $hh = 34;
        $ii = 35;
        $jj = 36;
        $kk = 37;
        $ll = 38;
        self::assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues, 'Should detect multiple long tests');
        self::assertSame('testFirstLongMethod', $issues[0]->testName);
        self::assertSame('testSecondLongMethod', $issues[1]->testName);
    }
}

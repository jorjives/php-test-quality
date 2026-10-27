<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\LongTestVisitor;

final class LongTestVisitorTest extends TestCase
{
    private const DEFAULT_THRESHOLD = 40;

    private function analyzeCode(string $code, int $lineThreshold = self::DEFAULT_THRESHOLD): LongTestVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new LongTestVisitor($lineThreshold);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    /**
     * Build a method spanning exactly $lines lines, from its signature to its closing brace.
     */
    private function method(string $name, int $lines, string $visibility = 'public'): string
    {
        // Signature, opening brace, final statement and closing brace account for 4 lines
        $body = '';
        for ($i = 1; $i <= $lines - 4; $i++) {
            $body .= "        \$v{$i} = {$i};\n";
        }

        return "    {$visibility} function {$name}(): void\n"
            . "    {\n"
            . $body
            . "        self::assertTrue(true);\n"
            . "    }\n";
    }

    private function classWith(string ...$methods): string
    {
        return "<?php\nclass SomeTest extends TestCase {\n" . implode("\n", $methods) . "}\n";
    }

    public function testDetectsLongTestMethod(): void
    {
        $code = $this->classWith($this->method('testVeryLongMethod', self::DEFAULT_THRESHOLD + 2));

        $issues = $this->analyzeCode($code)->getIssues();

        self::assertCount(1, $issues, 'Should detect one long test');
        self::assertSame('testVeryLongMethod', $issues[0]->testName, 'Issue should name the long test');
        self::assertSame('long_test', $issues[0]->type, 'Issue should have the long_test type');
        self::assertStringContainsString('42 lines', $issues[0]->message, 'Message should report the line count');
        self::assertStringContainsString('threshold: 40', $issues[0]->message, 'Message should report the threshold');
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
        $code = $this->classWith(
            $this->method('helperMethod', self::DEFAULT_THRESHOLD + 2, 'private'),
            $this->method('testShort', 4),
        );

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag non-test methods even if long');
    }

    public function testDetectsLongMethodWithTestAttribute(): void
    {
        // Method named without 'test' prefix but with #[Test] attribute
        $code = "<?php\nuse PHPUnit\\Framework\\Attributes\\Test;\n\n"
            . "class SomeTest extends TestCase {\n"
            . "    #[Test]\n"
            . $this->method('veryLongMethodWithAttribute', self::DEFAULT_THRESHOLD + 2)
            . "}\n";

        $issues = $this->analyzeCode($code)->getIssues();

        self::assertCount(1, $issues, 'Should detect long test with #[Test] attribute');
        self::assertSame('veryLongMethodWithAttribute', $issues[0]->testName, 'Issue should name the attributed test');
        self::assertSame('long_test', $issues[0]->type, 'Issue should have the long_test type');
    }

    public function testIgnoresMethodExactlyAtThreshold(): void
    {
        $code = $this->classWith($this->method('testExactlyAtThreshold', self::DEFAULT_THRESHOLD));

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag methods exactly at threshold');
    }

    public function testFlagsMethodOneLineOverThreshold(): void
    {
        $code = $this->classWith($this->method('testOneOverThreshold', self::DEFAULT_THRESHOLD + 1));

        $visitor = $this->analyzeCode($code);

        self::assertCount(1, $visitor->getIssues(), 'Should flag methods one line over the threshold');
    }

    public function testResetClearsIssues(): void
    {
        $code = $this->classWith($this->method('testVeryLongMethod', self::DEFAULT_THRESHOLD + 2));

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues(), 'Precondition: the long test should be flagged');

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

    public function testCustomThresholdDetectsShorterTests(): void
    {
        $customThreshold = 15;
        $code = $this->classWith($this->method('testMediumLength', $customThreshold + 4));

        $issues = $this->analyzeCode($code, $customThreshold)->getIssues();

        self::assertCount(1, $issues, 'Custom threshold of 15 should flag this test');
        self::assertStringContainsString('threshold: 15', $issues[0]->message, 'Message should show configured threshold');
    }

    public function testDetectsMultipleLongTests(): void
    {
        $code = $this->classWith(
            $this->method('testFirstLongMethod', self::DEFAULT_THRESHOLD + 2),
            $this->method('testSecondLongMethod', self::DEFAULT_THRESHOLD + 2),
        );

        $issues = $this->analyzeCode($code)->getIssues();

        self::assertCount(2, $issues, 'Should detect multiple long tests');
        self::assertSame('testFirstLongMethod', $issues[0]->testName, 'First issue should name the first long test');
        self::assertSame('testSecondLongMethod', $issues[1]->testName, 'Second issue should name the second long test');
    }
}

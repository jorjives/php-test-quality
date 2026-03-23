<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\RedundantPrintVisitor;

final class RedundantPrintVisitorTest extends TestCase
{
    private function analyzeCode(string $code): RedundantPrintVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new RedundantPrintVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsVarDumpCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        var_dump($result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsPrintRCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        print_r($array);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsVarExportCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        var_export($data);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsErrorLogCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        error_log('Debug message');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsEchoStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        echo 'Debug output';
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsPrintStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        print 'Result: x';
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsDumpCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        dump($object);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsDdCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        dd($object);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testIgnoresPrintOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        var_dump($x);
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

    public function testDetectsMultiplePrintStatements(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        var_dump($x);
        echo 'debug';
        print_r($array);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(3, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
        self::assertSame('testSomething', $issues[1]->testName);
        self::assertSame('redundant_print', $issues[1]->type);
        self::assertSame('testSomething', $issues[2]->testName);
        self::assertSame('redundant_print', $issues[2]->type);
    }

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithPrint(): void
    {
        var_dump($data);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsRedundantPrint(): void
    {
        $visitor = new RedundantPrintVisitor();

        self::assertSame('redundant_print', $visitor->getType());
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
        echo 'debug output';
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('itDoesSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
    }

    public function testDetectsCaseInsensitiveFunctionNames(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        VAR_DUMP($result);
        PRINT_R($array);
        Var_Export($data);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(3, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('redundant_print', $issues[0]->type);
        self::assertSame('testSomething', $issues[1]->testName);
        self::assertSame('redundant_print', $issues[1]->type);
        self::assertSame('testSomething', $issues[2]->testName);
        self::assertSame('redundant_print', $issues[2]->type);
    }

    public function testGetNameReturnsRedundantPrint(): void
    {
        $visitor = new RedundantPrintVisitor();

        self::assertSame('Redundant Print', $visitor->getName());
    }
}

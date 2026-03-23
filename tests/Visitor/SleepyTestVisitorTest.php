<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\SleepyTestVisitor;

final class SleepyTestVisitorTest extends TestCase
{
    private function analyzeCode(string $code): SleepyTestVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new SleepyTestVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsSleepCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        sleep(2);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('sleepy_test', $issues[0]->type);
    }

    public function testDetectsUsleepCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        usleep(1000);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('sleepy_test', $issues[0]->type);
    }

    public function testDetectsTimeNanosleepCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        time_nanosleep(1, 0);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('sleepy_test', $issues[0]->type);
    }

    public function testIgnoresSleepOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        sleep(1);
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

    public function testIgnoresNonTestMethods(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function somePrivateMethod(): void
    {
        sleep(1);
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
    public function testWithSleep(): void
    {
        sleep(2);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsSleepyTest(): void
    {
        $visitor = new SleepyTestVisitor();

        self::assertSame('sleepy_test', $visitor->getType());
    }

    public function testDetectsMultipleSleepCalls(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        sleep(1);
        usleep(500);
        time_nanosleep(1, 100);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(3, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('sleepy_test', $issues[0]->type);
        self::assertSame('testSomething', $issues[1]->testName);
        self::assertSame('sleepy_test', $issues[1]->type);
        self::assertSame('testSomething', $issues[2]->testName);
        self::assertSame('sleepy_test', $issues[2]->type);
    }

    public function testGetNameReturnsSleepyTest(): void
    {
        $visitor = new SleepyTestVisitor();

        self::assertSame('Sleepy Test', $visitor->getName());
    }
}

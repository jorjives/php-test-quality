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

        self::assertCount(1, $issues, 'Should flag the sleep() call');
        self::assertSame('testSomething', $issues[0]->testName, 'Issue should name the offending test');
        self::assertSame('sleepy_test', $issues[0]->type, 'Issue type should be sleepy_test');
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

        self::assertCount(1, $issues, 'Should flag the usleep() call');
        self::assertSame('testSomething', $issues[0]->testName, 'Issue should name the offending test');
        self::assertSame('sleepy_test', $issues[0]->type, 'Issue type should be sleepy_test');
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

        self::assertCount(1, $issues, 'Should flag the time_nanosleep() call');
        self::assertSame('testSomething', $issues[0]->testName, 'Issue should name the offending test');
        self::assertSame('sleepy_test', $issues[0]->type, 'Issue type should be sleepy_test');
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
        self::assertCount(1, $visitor->getIssues(), 'Should flag the sleep() call before reset');

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues(), 'Reset should clear all issues');
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

        self::assertCount(3, $issues, 'Should flag each of the three sleep calls');
        self::assertSame('testSomething', $issues[0]->testName, 'First issue should name the offending test');
        self::assertSame('sleepy_test', $issues[0]->type, 'First issue type should be sleepy_test');
        self::assertSame('testSomething', $issues[1]->testName, 'Second issue should name the offending test');
        self::assertSame('sleepy_test', $issues[1]->type, 'Second issue type should be sleepy_test');
        self::assertSame('testSomething', $issues[2]->testName, 'Third issue should name the offending test');
        self::assertSame('sleepy_test', $issues[2]->type, 'Third issue type should be sleepy_test');
    }

    public function testGetNameReturnsSleepyTest(): void
    {
        $visitor = new SleepyTestVisitor();

        self::assertSame('Sleepy Test', $visitor->getName());
    }
}

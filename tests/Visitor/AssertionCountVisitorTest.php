<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\AssertionCountVisitor;

final class AssertionCountVisitorTest extends TestCase
{
    private function analyzeCode(string $code): AssertionCountVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new AssertionCountVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsTestWithoutAssertions(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = doSomething();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('no_assertions', $issues[0]->type);
    }

    public function testIgnoresTestWithThisAssert(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = doSomething();
        $this->assertSame('expected', $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresTestWithSelfAssert(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = doSomething();
        self::assertSame('expected', $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresTestWithExpectException(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        throwSomething();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresTestWithSelfExpectException(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Something went wrong');
        throwSomething();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresTestWithMockExpectation(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $mock = $this->createMock(Collaborator::class);
        $mock->expects($this->once())
            ->method('doSomething')
            ->with($this->callback(fn ($arg) => $arg === 'expected'));

        $subject = new Subject($mock);
        $subject->run();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
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
        $result = doSomething();
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
        // No assertions here, but it's not a test
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
    public function testNoAssertions(): void
    {
        $x = 1;
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsNoAssertions(): void
    {
        $visitor = new AssertionCountVisitor();

        self::assertSame('no_assertions', $visitor->getType());
    }

    public function testGetNameReturnsAssertionCount(): void
    {
        $visitor = new AssertionCountVisitor();

        self::assertSame('Assertion Count', $visitor->getName());
    }
}

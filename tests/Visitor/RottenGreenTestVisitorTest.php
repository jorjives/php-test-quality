<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\RottenGreenTestVisitor;

final class RottenGreenTestVisitorTest extends TestCase
{
    private function analyzeCode(string $code): RottenGreenTestVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new RottenGreenTestVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsAssertionInIfBlock(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = getValue();
        if ($result > 0) {
            $this->assertTrue($result > 0);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
        self::assertStringContainsString('assertTrue', $issues[0]->message);
        self::assertStringContainsString('conditional', $issues[0]->message);
    }

    public function testDetectsAssertionInElseBlock(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = getValue();
        if ($result > 0) {
            // something
        } else {
            $this->assertFalse($result > 0);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
        self::assertStringContainsString('assertFalse', $issues[0]->message);
        self::assertStringContainsString('conditional', $issues[0]->message);
    }

    public function testDetectsAssertionInElseIfBlock(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = getValue();
        if ($result > 10) {
            // something
        } elseif ($result > 0) {
            $this->assertTrue($result > 0);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
    }

    public function testDetectsAssertionInSwitchCase(): void
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

        self::assertCount(2, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
        self::assertStringContainsString('assertTrue', $issues[0]->message);
        self::assertStringContainsString('conditional', $issues[0]->message);

        self::assertSame('testSomething', $issues[1]->testName);
        self::assertSame('rotten_green_test', $issues[1]->type);
        self::assertStringContainsString('assertFalse', $issues[1]->message);
    }

    public function testDetectsAssertionAfterReturn(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $user = getUser();
        if ($user === null) {
            return;
        }
        $this->assertNotNull($user);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
        self::assertStringContainsString('assertNotNull', $issues[0]->message);
        self::assertStringContainsString('unreachable', $issues[0]->message);
    }

    public function testDetectsAssertionAfterThrow(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        try {
            doSomething();
        } catch (Exception $e) {
            throw $e;
        }
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
        self::assertStringContainsString('assertTrue', $issues[0]->message);
        self::assertStringContainsString('unreachable', $issues[0]->message);
    }

    public function testDoesNotFlagNormalAssertion(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $user = new User('John');
        $this->assertEquals('John', $user->getName());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testDoesNotFlagAssertionInHelperMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        if (true) {
            $this->assertTrue(true);
        }
    }

    public function testSomething(): void
    {
        $this->helperMethod();
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Only the assertion in testSomething should be flagged if in conditional
        // Assertions in helper methods should be ignored
        self::assertCount(0, $issues);
    }

    public function testResetClearsState(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = getValue();
        if ($result > 0) {
            $this->assertTrue($result > 0);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsRottenGreenTest(): void
    {
        $visitor = new RottenGreenTestVisitor();

        self::assertSame('rotten_green_test', $visitor->getType());
    }

    public function testGetNameReturnsRottenGreenTest(): void
    {
        $visitor = new RottenGreenTestVisitor();

        self::assertSame('Rotten Green Test', $visitor->getName());
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
        $result = getValue();
        if ($result > 0) {
            $this->assertTrue($result > 0);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('itDoesSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
    }

    public function testDetectsMultipleAssertionsInConditionals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $value = getValue();
        if ($value > 0) {
            $this->assertTrue($value > 0);
            $this->assertGreaterThan(0, $value);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('testSomething', $issues[1]->testName);
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
                $this->assertTrue($value > 10);
            }
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
    }

    public function testDetectsStaticCallAssertions(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $value = getValue();
        if ($value > 0) {
            self::assertTrue($value > 0);
        }
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testSomething', $issues[0]->testName);
        self::assertSame('rotten_green_test', $issues[0]->type);
        self::assertStringContainsString('assertTrue', $issues[0]->message);
    }

    public function testDetectsAssertionInTernaryExpression(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $value = getValue();
        $result = $value > 0 ? 'positive' : 'non-positive';
        $this->assertSame('positive', $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        // Ternary itself doesn't create conditional for assertion depth,
        // only if assertion is directly in the ternary operator expression
        $issues = $visitor->getIssues();
        self::assertCount(0, $issues);
    }

    public function testDetectsNormalAssertionsOutsideConditionals(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $result = getValue();
        if ($result > 0) {
            $this->assertTrue($result > 0);
        }
        $this->assertNotNull($result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Only the assertion inside the if should be flagged
        self::assertCount(1, $issues);
        self::assertStringContainsString('assertTrue', $issues[0]->message);
    }

    public function testIgnoresConditionalOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function helperMethod(): void
    {
        if (true) {
            $this->assertTrue(true);
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

    public function testDoesNotFlagAssertionAfterClosureWithReturn(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $mock = $this->createMock(SomeClass::class);
        $mock->method('__call')
            ->willReturnCallback(function ($method) {
                if ($method === 'get') {
                    return null;
                }
                return true;
            });

        $result = $mock->doSomething();

        $this->assertNotNull($result);
        $this->assertTrue($result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Assertions after the closure should NOT be flagged as unreachable
        // The return statements are inside the closure scope, not the test method scope
        self::assertCount(0, $issues);
    }

    public function testDoesNotFlagAssertionAfterArrowFunctionWithReturn(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $items = [1, 2, 3];
        $filtered = array_filter($items, fn($item) => $item > 1 ? true : false);

        $this->assertCount(2, $filtered);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Assertions after arrow function should NOT be flagged
        self::assertCount(0, $issues);
    }

    public function testDoesNotFlagAssertionAfterNestedClosures(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $mock = $this->createMock(SomeClass::class);
        $mock->method('process')
            ->willReturnCallback(function ($data) {
                return array_map(function ($item) {
                    if ($item === null) {
                        return 'empty';
                    }
                    return $item;
                }, $data);
            });

        $result = $mock->process(['a', 'b']);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Nested closures with returns should not affect outer scope
        self::assertCount(0, $issues);
    }
}

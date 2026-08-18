<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\ExistenceCheckVisitor;

final class ExistenceCheckVisitorTest extends TestCase
{
    private function analyzeCode(string $code): ExistenceCheckVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new ExistenceCheckVisitor();
        $visitor->setCurrentFile('test.php');
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsAssertTrueClassExists(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SomeClass::class));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testClassExists', $issues[0]->testName);
        self::assertSame('existence_check_assertion', $issues[0]->type);
    }

    public function testDetectsAssertTrueMethodExists(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testHasMethod(): void
    {
        $this->assertTrue(method_exists(SomeClass::class, 'doSomething'));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testHasMethod', $issues[0]->testName);
        self::assertSame('existence_check_assertion', $issues[0]->type);
    }

    public function testDetectsAssertFalseClassExists(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testClassDoesNotExist(): void
    {
        $this->assertFalse(class_exists(RemovedClass::class));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('existence_check_assertion', $issues[0]->type);
    }

    public function testDetectsStaticCallForm(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testHasMethod(): void
    {
        self::assertTrue(method_exists(SomeClass::class, 'doSomething'));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('existence_check_assertion', $issues[0]->type);
    }

    public function testIgnoresClassExistsUsedAsGuard(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testBehaviour(): void
    {
        if (class_exists(SomeClass::class)) {
            $sut = new SomeClass();
        }
        $this->assertTrue($sut->isValid());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testIgnoresUnrelatedBooleanAssertion(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testIsValid(): void
    {
        $this->assertTrue($sut->isValid());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testIgnoresNonTestMethods(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    private function helper(): void
    {
        $this->assertTrue(class_exists(SomeClass::class));
    }

    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SomeClass::class));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsExistenceCheckAssertion(): void
    {
        $visitor = new ExistenceCheckVisitor();
        self::assertSame('existence_check_assertion', $visitor->getType());
    }

    public function testGetNameReturnsExistenceCheckAssertionName(): void
    {
        $visitor = new ExistenceCheckVisitor();
        self::assertSame('Existence Check Assertion', $visitor->getName());
    }

    public function testDetectsMultipleTestMethodsInClass(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SomeClass::class));
    }

    public function testMethodExists(): void
    {
        $this->assertTrue(method_exists(SomeClass::class, 'foo'));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
        self::assertSame('testClassExists', $issues[0]->testName);
        self::assertSame('testMethodExists', $issues[1]->testName);
    }

    public function testDetectsWithTestAttribute(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    #[Test]
    public function classExistsCheck(): void
    {
        $this->assertTrue(class_exists(SomeClass::class));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('classExistsCheck', $issues[0]->testName);
    }
}

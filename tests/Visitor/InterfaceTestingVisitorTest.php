<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\InterfaceTestingVisitor;

final class InterfaceTestingVisitorTest extends TestCase
{
    private function analyzeCode(string $code): InterfaceTestingVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new InterfaceTestingVisitor();
        $visitor->setCurrentFile('test.php');
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    // ========== Mock-Only Interface Testing ==========

    public function testDetectsMockOnlyInterfaceTestingWithWillReturn(): void
    {
        $code = <<<'PHP'
<?php
class SomeInterfaceTest extends TestCase {
    public function testInterfaceMethod(): void
    {
        $sut = $this->createMock(SomeInterface::class);
        $sut->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testInterfaceMethod', $issues[0]->testName);
        self::assertSame('interface_mock_testing', $issues[0]->type);
    }

    public function testDetectsMockOnlyInterfaceTestingWithExpects(): void
    {
        $code = <<<'PHP'
<?php
class SomeInterfaceTest extends TestCase {
    public function testInterfaceMethod(): void
    {
        $sut = $this->createMock(MyInterface::class);
        $sut->expects($this->once())->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testInterfaceMethod', $issues[0]->testName);
        self::assertSame('interface_mock_testing', $issues[0]->type);
    }

    public function testIgnoresMockOfConcreteClass(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testSomething(): void
    {
        $sut = $this->createMock(SomeClass::class);
        $sut->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testIgnoresMockOfInterfaceWithRealAssertions(): void
    {
        $code = <<<'PHP'
<?php
class SomeInterfaceTest extends TestCase {
    public function testUsesInterfaceAsInjectedDependency(): void
    {
        $dependency = $this->createMock(SomeInterface::class);
        $dependency->method('getValue')->willReturn('test');

        $sut = new SomeClass($dependency);
        $this->assertEquals('test', $sut->process());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testIgnoresMockWithoutWillReturn(): void
    {
        $code = <<<'PHP'
<?php
class SomeInterfaceTest extends TestCase {
    public function testInterfaceMethod(): void
    {
        $sut = $this->createMock(SomeInterface::class);
        $sut->method('doSomething');
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    // ========== Implementation Check Testing ==========

    public function testDetectsOnlyAssertInstanceOfInterface(): void
    {
        $code = <<<'PHP'
<?php
class ConcreteClassTest extends TestCase {
    public function testImplementsInterface(): void
    {
        $sut = new ConcreteClass();
        $this->assertInstanceOf(SomeInterface::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('testImplementsInterface', $issues[0]->testName);
        self::assertSame('interface_implementation_test', $issues[0]->type);
    }

    public function testIgnoresAssertInstanceOfConcreteClass(): void
    {
        $code = <<<'PHP'
<?php
class ConcreteClassTest extends TestCase {
    public function testIsCorrectClass(): void
    {
        $sut = new ConcreteClass();
        $this->assertInstanceOf(ConcreteClass::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testIgnoresAssertInstanceOfWithOtherAssertions(): void
    {
        $code = <<<'PHP'
<?php
class ConcreteClassTest extends TestCase {
    public function testImplementsInterfaceAndHasBehavior(): void
    {
        $sut = new ConcreteClass();
        $this->assertInstanceOf(SomeInterface::class, $sut);
        $this->assertEquals('expected', $sut->getValue());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(0, $issues);
    }

    public function testDetectsPsrInterfaceAssertInstanceOf(): void
    {
        $code = <<<'PHP'
<?php
class LoggerTest extends TestCase {
    public function testImplementsPsrInterface(): void
    {
        $logger = new MyLogger();
        $this->assertInstanceOf(Psr\Log\LoggerInterface::class, $logger);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('interface_implementation_test', $issues[0]->type);
    }

    public function testIgnoresInterfacePatternClassNames(): void
    {
        $code = <<<'PHP'
<?php
class SomeHandlerTest extends TestCase {
    public function testCreatesHandler(): void
    {
        $sut = new SomeHandler();
        $this->assertInstanceOf(SomeHandler::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // SomeHandler doesn't end with Interface so it shouldn't be detected
        self::assertCount(0, $issues);
    }

    public function testIgnoresRepositoryPatternAsConcreteClass(): void
    {
        $code = <<<'PHP'
<?php
class UserRepositoryTest extends TestCase {
    public function testIsRepository(): void
    {
        $sut = new UserRepository();
        $this->assertInstanceOf(UserRepository::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Repository pattern alone is not enough - too many concrete Repository classes
        self::assertCount(0, $issues);
    }

    public function testDetectsIterableAsInterface(): void
    {
        $code = <<<'PHP'
<?php
class CollectionTest extends TestCase {
    public function testIsIterable(): void
    {
        $sut = new Collection();
        $this->assertInstanceOf(Iterable::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Iterable matches /able$/ pattern
        self::assertCount(1, $issues);
        self::assertSame('interface_implementation_test', $issues[0]->type);
    }

    // ========== Edge Cases ==========

    public function testHandlesMultipleAssertInstanceOfCalls(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testMultipleInterfaceChecks(): void
    {
        $sut = new SomeClass();
        $this->assertInstanceOf(SomeInterface::class, $sut);
        $this->assertInstanceOf(AnotherInterface::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Multiple interface checks with no other assertions should be detected
        self::assertCount(1, $issues);
        self::assertSame('interface_implementation_test', $issues[0]->type);
    }

    public function testIgnoresNonTestMethods(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function helperMethod(): void
    {
        $sut = $this->createMock(SomeInterface::class);
        $sut->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
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
class SomeInterfaceTest extends TestCase {
    public function testImplementsInterface(): void
    {
        $sut = new SomeClass();
        $this->assertInstanceOf(SomeInterface::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues());
    }

    public function testGetTypeReturnsInterfaceMockTesting(): void
    {
        $visitor = new InterfaceTestingVisitor();
        self::assertSame('interface_testing', $visitor->getType());
    }

    public function testGetNameReturnsInterfaceTestingName(): void
    {
        $visitor = new InterfaceTestingVisitor();
        self::assertSame('Interface Testing Anti-Patterns', $visitor->getName());
    }

    public function testDetectsMultipleTestMethodsInClass(): void
    {
        $code = <<<'PHP'
<?php
class SomeInterfaceTest extends TestCase {
    public function testMockOnlyInterface(): void
    {
        $sut = $this->createMock(SomeInterface::class);
        $sut->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
    }

    public function testOnlyAssertsImplementation(): void
    {
        $sut = new ConcreteClass();
        $this->assertInstanceOf(AnotherInterface::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
        self::assertSame('testMockOnlyInterface', $issues[0]->testName);
        self::assertSame('interface_mock_testing', $issues[0]->type);
        self::assertSame('testOnlyAssertsImplementation', $issues[1]->testName);
        self::assertSame('interface_implementation_test', $issues[1]->type);
    }

    public function testDetectsFullyQualifiedInterfaceInCreateMock(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testInterface(): void
    {
        $sut = $this->createMock(\App\Domain\SomeInterface::class);
        $sut->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('interface_mock_testing', $issues[0]->type);
    }

    public function testDetectsInterfaceNameWithIPrefix(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testInterface(): void
    {
        $sut = new SomeClass();
        $this->assertInstanceOf(IFoo::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // IFoo matches /^I[A-Z]/ pattern
        self::assertCount(1, $issues);
        self::assertSame('interface_implementation_test', $issues[0]->type);
    }

    public function testIgnoresMockWhenSutIsActuallyTestedLogic(): void
    {
        $code = <<<'PHP'
<?php
class SomeClassTest extends TestCase {
    public function testIntegrationWithInterface(): void
    {
        $dependency = $this->createMock(SomeInterface::class);
        $sut = new SomeClass($dependency);
        // Testing actual behavior
        $this->assertTrue($sut->validate());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Mock is dependency but SUT is actual class being tested
        self::assertCount(0, $issues);
    }

    public function testHandlesFactoryPatternInterface(): void
    {
        $code = <<<'PHP'
<?php
class FactoryTest extends TestCase {
    public function testFactoryCreatesInterface(): void
    {
        $factory = new SomeFactory();
        $sut = $factory->create();
        $this->assertInstanceOf(SomeInterface::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        // Only asserting interface implementation with no behavior check
        self::assertCount(1, $issues);
        self::assertSame('interface_implementation_test', $issues[0]->type);
    }

    public function testDetectsStaticMethodMock(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithStaticMock(): void
    {
        $sut = self::createMock(SomeInterface::class);
        $sut->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('interface_mock_testing', $issues[0]->type);
    }

    public function testDetectsMockOnlyInterfaceTestingWithTestAttribute(): void
    {
        $code = <<<'PHP'
<?php
class SomeInterfaceTest extends TestCase {
    #[Test]
    public function interfaceMethodTest(): void
    {
        $sut = $this->createMock(SomeInterface::class);
        $sut->method('doSomething')->willReturn('value');
        $this->assertEquals('value', $sut->doSomething());
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('interfaceMethodTest', $issues[0]->testName);
        self::assertSame('interface_mock_testing', $issues[0]->type);
    }

    public function testDetectsImplementationTestWithTestAttribute(): void
    {
        $code = <<<'PHP'
<?php
class ConcreteClassTest extends TestCase {
    #[Test]
    public function implementsInterfaceCheck(): void
    {
        $sut = new ConcreteClass();
        $this->assertInstanceOf(SomeInterface::class, $sut);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues);
        self::assertSame('implementsInterfaceCheck', $issues[0]->testName);
        self::assertSame('interface_implementation_test', $issues[0]->type);
    }
}

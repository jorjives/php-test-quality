<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\ConstructorInitializationVisitor;

final class ConstructorInitializationVisitorTest extends TestCase
{
    private function analyzeCode(string $code): ConstructorInitializationVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new ConstructorInitializationVisitor();
        $visitor->setCurrentFile('test/SomeTest.php');
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsConstructorInTestClass(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function __construct()
    {
        $this->data = [];
        $this->counter = 0;
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect constructor with initialization logic in test class');
        self::assertSame('SomeTest', $issues[0]->testName, 'Issue testName should be the class name');
        self::assertSame('constructor_initialization', $issues[0]->type);
        self::assertStringContainsString('constructor', $issues[0]->message);
        self::assertStringContainsString('setUp', $issues[0]->message);
    }

    public function testIgnoresSetUpMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    protected function setUp(): void
    {
        $this->data = [];
        $this->counter = 0;
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag setUp() method - it is the correct way');
    }

    public function testIgnoresConstructorInNonTestClass(): void
    {
        $code = <<<'PHP'
<?php
class SomeService {
    public function __construct()
    {
        $this->data = [];
        $this->counter = 0;
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should only flag constructors in test classes');
    }

    public function testDetectsConstructorWithParentCallAndOtherLogic(): void
    {
        $code = <<<'PHP'
<?php
class UserTest extends TestCase {
    public function __construct()
    {
        parent::__construct();
        $this->fixture = new UserFixture();
        $this->setupDatabase();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect when constructor has parent::__construct() plus other logic');
        self::assertSame('UserTest', $issues[0]->testName);
        self::assertSame('constructor_initialization', $issues[0]->type);
    }

    public function testIgnoresConstructorWithOnlyParentCall(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function __construct()
    {
        parent::__construct();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag constructor with only parent::__construct() call');
    }

    public function testIgnoresEmptyConstructor(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function __construct()
    {
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag empty constructors');
    }

    public function testGetTypeReturnsCorrectType(): void
    {
        $visitor = new ConstructorInitializationVisitor();

        self::assertSame('constructor_initialization', $visitor->getType());
    }

    public function testGetNameReturnsReadableName(): void
    {
        $visitor = new ConstructorInitializationVisitor();

        self::assertSame('Constructor Initialization', $visitor->getName());
    }

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function __construct()
    {
        $this->data = [];
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues());

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues(), 'reset() should clear all issues');
    }

    public function testDetectsConstructorWithPropertyAssignment(): void
    {
        $code = <<<'PHP'
<?php
class ApiTest extends TestCase {
    public function __construct()
    {
        $this->client = new HttpClient();
        $this->baseUrl = 'http://localhost:8000';
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect constructors that assign to $this->property');
        self::assertSame('ApiTest', $issues[0]->testName);
        self::assertSame('constructor_initialization', $issues[0]->type);
    }

    public function testDetectsConstructorWithMethodCalls(): void
    {
        $code = <<<'PHP'
<?php
class DatabaseTest extends TestCase {
    public function __construct()
    {
        $this->initializeDatabase();
        $this->loadFixtures();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect constructors with method calls');
        self::assertSame('DatabaseTest', $issues[0]->testName);
    }

    public function testDetectsConstructorWithStatements(): void
    {
        $code = <<<'PHP'
<?php
class LogTest extends TestCase {
    public function __construct()
    {
        $config = parse_ini_file('test.ini');
        $this->logger = new Logger($config);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect constructors with various statements');
        self::assertSame('LogTest', $issues[0]->testName);
    }

    public function testDetectsMultipleConstructorsInDifferentClasses(): void
    {
        $code = <<<'PHP'
<?php
class FirstTest extends TestCase {
    public function __construct()
    {
        $this->data = [];
    }
}

class SecondTest extends TestCase {
    public function __construct()
    {
        $this->config = [];
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues, 'Should detect constructors in multiple test classes');
        self::assertSame('FirstTest', $issues[0]->testName);
        self::assertSame('SecondTest', $issues[1]->testName);
    }

    public function testIgnoresConstructorInNestedNonTestClass(): void
    {
        $code = <<<'PHP'
<?php
class MyTest extends TestCase {
    public function testSomething(): void
    {
        $obj = new class {
            public function __construct()
            {
                $this->value = 1;
            }
        };
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag constructors in nested non-test classes');
    }

    public function testDetectsConstructorWithComments(): void
    {
        $code = <<<'PHP'
<?php
class CacheTest extends TestCase {
    public function __construct()
    {
        // Initialize cache
        $this->cache = new Cache();
        // Set default timeout
        $this->timeout = 30;
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect constructors even with comments');
        self::assertSame('CacheTest', $issues[0]->testName);
    }

    public function testSetCurrentFile(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function __construct()
    {
        $this->data = [];
    }
}
PHP;

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new ConstructorInitializationVisitor();
        $visitor->setCurrentFile('/path/to/SomeTest.php');

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $issues = $visitor->getIssues();
        self::assertCount(1, $issues);
        self::assertSame('/path/to/SomeTest.php', $issues[0]->file);
    }

    public function testIgnoresConstructorWithOnlyComments(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function __construct()
    {
        // This is just a comment
        // Another comment
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues(), 'Should not flag constructors with only comments');
    }

    public function testDetectsConstructorExtendsTestCaseVariant(): void
    {
        $code = <<<'PHP'
<?php
class RepositoryTest extends \PHPUnit\Framework\TestCase {
    public function __construct()
    {
        $this->repository = new UserRepository();
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should detect constructors in classes extending TestCase with full namespace');
        self::assertSame('RepositoryTest', $issues[0]->testName);
    }
}

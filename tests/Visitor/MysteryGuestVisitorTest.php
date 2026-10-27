<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Visitor\MysteryGuestVisitor;

final class MysteryGuestVisitorTest extends TestCase
{
    private function analyzeCode(string $code): MysteryGuestVisitor
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new MysteryGuestVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }

    public function testDetectsFileGetContents(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testImport(): void
    {
        $data = file_get_contents('fixtures/users.json');
        $result = json_decode($data, true);
        $this->assertCount(10, $result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the file_get_contents() call');
        self::assertSame('testImport', $issues[0]->testName, 'Issue should name the offending test');
        self::assertSame('mystery_guest', $issues[0]->type, 'Issue type should be mystery_guest');
        self::assertStringContainsString(
            'file_get_contents',
            $issues[0]->message,
            'Message should mention file_get_contents'
        );
    }

    public function testDetectsFopen(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testReadFile(): void
    {
        $handle = fopen('data.txt', 'r');
        $content = fread($handle, 100);
        fclose($handle);
        $this->assertNotEmpty($content);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues, 'Should flag two file-access calls');
        self::assertSame('testReadFile', $issues[0]->testName, 'Issue should name the offending test');
        self::assertSame('mystery_guest', $issues[0]->type, 'Issue type should be mystery_guest');
    }

    public function testDetectsFgets(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testReadLine(): void
    {
        $handle = fopen('data.txt', 'r');
        $line = fgets($handle);
        fclose($handle);
        $this->assertNotEmpty($line);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues, 'Should flag two file-access calls');
        self::assertStringContainsString('fgets', $issues[1]->message, 'Second issue should mention fgets');
    }

    public function testDetectsFileFunction(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testReadLines(): void
    {
        $lines = file('log.txt');
        $this->assertCount(5, $lines);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the file() call');
        self::assertSame('testReadLines', $issues[0]->testName, 'Issue should name the offending test');
        self::assertStringContainsString('file()', $issues[0]->message, 'Message should mention file()');
    }

    public function testDetectsReadfile(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testOutput(): void
    {
        ob_start();
        readfile('output.html');
        $content = ob_get_clean();
        $this->assertNotEmpty($content);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the readfile() call');
        self::assertStringContainsString('readfile', $issues[0]->message, 'Message should mention readfile');
    }

    public function testDetectsParseIniFile(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testConfig(): void
    {
        $config = parse_ini_file('config.ini');
        $this->assertArrayHasKey('database', $config);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the parse_ini_file() call');
        self::assertStringContainsString(
            'parse_ini_file',
            $issues[0]->message,
            'Message should mention parse_ini_file'
        );
    }

    public function testDetectsSimplexmlLoadFile(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testXml(): void
    {
        $xml = simplexml_load_file('data.xml');
        $this->assertNotNull($xml);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the simplexml_load_file() call');
        self::assertStringContainsString(
            'simplexml_load_file',
            $issues[0]->message,
            'Message should mention simplexml_load_file'
        );
    }

    public function testDetectsIncludeStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithFixture(): void
    {
        include 'fixtures/data.php';
        $this->assertNotEmpty($fixtureData);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the include statement');
        self::assertStringContainsString('include', $issues[0]->message, 'Message should mention include');
    }

    public function testDetectsRequireStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithFixture(): void
    {
        require 'fixtures/helpers.php';
        $result = helperFunction();
        $this->assertTrue($result);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the require statement');
        self::assertStringContainsString('require', $issues[0]->message, 'Message should mention require');
    }

    public function testDetectsIncludeOnceStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithFixture(): void
    {
        include_once 'fixtures/data.php';
        $this->assertNotEmpty($fixtureData);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the include_once statement');
        self::assertStringContainsString('include_once', $issues[0]->message, 'Message should mention include_once');
    }

    public function testDetectsRequireOnceStatement(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithFixture(): void
    {
        require_once 'fixtures/bootstrap.php';
        $this->assertTrue(defined('BOOTSTRAPPED'));
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the require_once statement');
        self::assertStringContainsString('require_once', $issues[0]->message, 'Message should mention require_once');
    }

    public function testDetectsDatabaseQuery(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testDatabase(): void
    {
        $result = $this->pdo->query('SELECT * FROM users');
        $users = $result->fetchAll();
        $this->assertCount(10, $users);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues, 'Should flag two database calls');
        self::assertSame('mystery_guest', $issues[0]->type, 'Issue type should be mystery_guest');
    }

    public function testDetectsDoctrineFind(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testFindUser(): void
    {
        $user = $this->repository->find(1);
        $this->assertNotNull($user);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the repository find() call');
        self::assertStringContainsString('find()', $issues[0]->message, 'Message should mention find()');
    }

    public function testDetectsFindBy(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testFindUsers(): void
    {
        $users = $this->repository->findBy(['active' => true]);
        $this->assertNotEmpty($users);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the repository findBy() call');
        self::assertStringContainsString('findBy()', $issues[0]->message, 'Message should mention findBy()');
    }

    public function testDetectsFindOneBy(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testFindUser(): void
    {
        $user = $this->repository->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($user);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the repository findOneBy() call');
        self::assertStringContainsString('findOneBy()', $issues[0]->message, 'Message should mention findOneBy()');
    }

    public function testDetectsCreateQueryBuilder(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testQuery(): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $users = $qb->select('u')->from('User', 'u')->getQuery()->getResult();
        $this->assertNotEmpty($users);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the createQueryBuilder() call');
        self::assertStringContainsString(
            'createQueryBuilder()',
            $issues[0]->message,
            'Message should mention createQueryBuilder()'
        );
    }

    public function testDetectsFetchAll(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testFetch(): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users');
        $stmt->execute();
        $users = $stmt->fetchAll();
        $this->assertCount(5, $users);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(2, $issues);
    }

    public function testIgnoresFileOperationsOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function loadFixture(): array
    {
        return json_decode(file_get_contents('fixtures/data.json'), true);
    }

    public function testSomething(): void
    {
        $data = $this->loadFixture();
        $this->assertNotEmpty($data);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresDatabaseOperationsOutsideTestMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    private function getUsers(): array
    {
        return $this->repository->findAll();
    }

    public function testSomething(): void
    {
        $users = $this->getUsers();
        $this->assertNotEmpty($users);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresSetUpMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    protected function setUp(): void
    {
        $this->config = parse_ini_file('test.ini');
    }

    public function testSomething(): void
    {
        $this->assertNotEmpty($this->config);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testIgnoresTearDownMethod(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    protected function tearDown(): void
    {
        file_put_contents('log.txt', 'cleaned up');
    }

    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);

        self::assertCount(0, $visitor->getIssues());
    }

    public function testDetectsMultipleMysteryGuests(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testComplex(): void
    {
        $config = file_get_contents('config.json');
        $users = $this->repository->findAll();
        include 'helpers.php';
        $this->assertNotEmpty($users);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(3, $issues);
    }

    public function testResetClearsIssues(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithFile(): void
    {
        $data = file_get_contents('data.json');
        $this->assertNotEmpty($data);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        self::assertCount(1, $visitor->getIssues(), 'Should flag the file read before reset');

        $visitor->reset();
        self::assertCount(0, $visitor->getIssues(), 'Reset should clear all issues');
    }

    public function testGetTypeReturnsMysteryGuest(): void
    {
        $visitor = new MysteryGuestVisitor();

        self::assertSame('mystery_guest', $visitor->getType());
    }

    public function testGetNameReturnsMysteryGuest(): void
    {
        $visitor = new MysteryGuestVisitor();

        self::assertSame('Mystery Guest', $visitor->getName());
    }

    public function testDetectsTestWithAttribute(): void
    {
        $code = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test;

class SomeTest extends TestCase {
    #[Test]
    public function readsFromFile(): void
    {
        $data = file_get_contents('data.json');
        $this->assertNotEmpty($data);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the file read in the attributed test');
        self::assertSame('readsFromFile', $issues[0]->testName, 'Issue should name the attributed test');
    }

    public function testDetectsFindAll(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testFindAll(): void
    {
        $users = $this->repository->findAll();
        $this->assertNotEmpty($users);
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $issues = $visitor->getIssues();

        self::assertCount(1, $issues, 'Should flag the repository findAll() call');
        self::assertStringContainsString('findAll()', $issues[0]->message, 'Message should mention findAll()');
    }

    public function testSetCurrentFile(): void
    {
        $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithFile(): void
    {
        $data = file_get_contents('data.json');
        $this->assertNotEmpty($data);
    }
}
PHP;

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $visitor = new MysteryGuestVisitor();
        $visitor->setCurrentFile('/path/to/SomeTest.php');

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $issues = $visitor->getIssues();
        self::assertCount(1, $issues, 'Should flag the file read');
        self::assertSame(
            '/path/to/SomeTest.php',
            $issues[0]->file,
            'Issue should carry the file set via setCurrentFile()'
        );
    }
}

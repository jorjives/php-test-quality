<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests;

use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Analyzer;
use TestQualityAnalyzer\Visitor\AssertionCountVisitor;

final class AnalyzerTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/test-quality-fixtures-' . uniqid();
        mkdir($this->fixtureDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTestFile(string $relativePath, string $content): void
    {
        $fullPath = $this->fixtureDir . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($fullPath, $content);
    }

    public function testAnalyzesDirectoryRecursively(): void
    {
        $this->createTestFile('Unit/SomeTest.php', <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testWithAssertion(): void {
        self::assertTrue(true);
    }
    public function testWithoutAssertion(): void {
        $x = 1;
    }
}
PHP);

        $this->createTestFile('Integration/OtherTest.php', <<<'PHP'
<?php
class OtherTest extends TestCase {
    public function testNoAssertions(): void {
        doSomething();
    }
}
PHP);

        $analyzer = new Analyzer();
        $analyzer->addVisitor(new AssertionCountVisitor());
        $result = $analyzer->analyzeDirectory($this->fixtureDir);

        self::assertSame(2, $result->filesScanned);
        self::assertSame(3, $result->testsFound);
        self::assertCount(2, $result->issues);
    }

    public function testIgnoresNonTestFiles(): void
    {
        $this->createTestFile('SomeClass.php', <<<'PHP'
<?php
class SomeClass {
    public function doSomething(): void {
        // No assertions here
    }
}
PHP);

        $this->createTestFile('SomeTest.php', <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void {
        self::assertTrue(true);
    }
}
PHP);

        $analyzer = new Analyzer();
        $analyzer->addVisitor(new AssertionCountVisitor());
        $result = $analyzer->analyzeDirectory($this->fixtureDir);

        self::assertSame(1, $result->filesScanned);
    }

    public function testReturnsEmptyResultForEmptyDirectory(): void
    {
        $analyzer = new Analyzer();
        $analyzer->addVisitor(new AssertionCountVisitor());
        $result = $analyzer->analyzeDirectory($this->fixtureDir);

        self::assertSame(0, $result->filesScanned);
        self::assertSame(0, $result->testsFound);
        self::assertFalse($result->hasIssues());
    }

    public function testGetRegisteredTypesReturnsTypeAndNameForEachVisitor(): void
    {
        $analyzer = new Analyzer();
        $analyzer->addVisitor(new AssertionCountVisitor());

        $types = $analyzer->getRegisteredTypes();

        self::assertCount(1, $types);
        self::assertSame('no_assertions', $types[0]['type']);
        self::assertSame('Assertion Count', $types[0]['name']);
    }

    public function testGetRegisteredTypesReturnsEmptyArrayWhenNoVisitors(): void
    {
        $analyzer = new Analyzer();

        $types = $analyzer->getRegisteredTypes();

        self::assertCount(0, $types);
    }
}

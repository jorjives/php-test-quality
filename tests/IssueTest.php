<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests;

use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\Issue;

final class IssueTest extends TestCase
{
    public function testIssueStoresAllProperties(): void
    {
        $issue = new Issue(
            file: 'test/Unit/SomeTest.php',
            testName: 'testSomething',
            type: 'no_assertions',
            message: 'Test has no assertions'
        );

        self::assertSame('test/Unit/SomeTest.php', $issue->file);
        self::assertSame('testSomething', $issue->testName);
        self::assertSame('no_assertions', $issue->type);
        self::assertSame('Test has no assertions', $issue->message);
    }

    public function testIssueConvertsToArray(): void
    {
        $issue = new Issue(
            file: 'test/Unit/SomeTest.php',
            testName: 'testSomething',
            type: 'no_assertions',
            message: 'Test has no assertions'
        );

        $array = $issue->toArray();

        self::assertSame([
            'file' => 'test/Unit/SomeTest.php',
            'test' => 'testSomething',
            'type' => 'no_assertions',
            'message' => 'Test has no assertions',
        ], $array);
    }
}

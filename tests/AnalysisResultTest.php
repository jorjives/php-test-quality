<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Tests;

use PHPUnit\Framework\TestCase;
use TestQualityAnalyzer\AnalysisResult;
use TestQualityAnalyzer\Issue;

final class AnalysisResultTest extends TestCase
{
    public function testEmptyResultHasNoIssues(): void
    {
        $result = new AnalysisResult(filesScanned: 10, testsFound: 50, issues: []);

        self::assertSame(10, $result->filesScanned);
        self::assertSame(50, $result->testsFound);
        self::assertFalse($result->hasIssues());
        self::assertSame([], $result->issues);
    }

    public function testResultWithIssuesHasIssues(): void
    {
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'No assertions');
        $result = new AnalysisResult(filesScanned: 1, testsFound: 1, issues: [$issue]);

        self::assertTrue($result->hasIssues());
        self::assertCount(1, $result->issues);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'No assertions');
        $result = new AnalysisResult(filesScanned: 1, testsFound: 1, issues: [$issue]);

        $json = $result->toJson();
        $decoded = json_decode($json, true);

        self::assertSame(1, $decoded['summary']['files_scanned']);
        self::assertSame(1, $decoded['summary']['tests_found']);
        self::assertSame(1, $decoded['issues']['no_assertions']);
        self::assertCount(1, $decoded['details']);
    }

    public function testGetIssueCounts(): void
    {
        $issues = [
            new Issue('a.php', 'testA', 'no_assertions', 'msg'),
            new Issue('b.php', 'testB', 'no_assertions', 'msg'),
            new Issue('c.php', 'testC', 'other_type', 'msg'),
        ];
        $result = new AnalysisResult(filesScanned: 3, testsFound: 3, issues: $issues);

        $counts = $result->getIssueCounts();

        self::assertSame(2, $counts['no_assertions']);
        self::assertSame(1, $counts['other_type']);
    }

    public function testToTextWithNoIssuesShowsSuccess(): void
    {
        $result = new AnalysisResult(filesScanned: 10, testsFound: 50, issues: []);

        $text = $result->toText();

        self::assertStringContainsString('TEST QUALITY ANALYSIS REPORT', $text);
        self::assertStringContainsString('Files scanned: 10', $text);
        self::assertStringContainsString('Tests found: 50', $text);
        self::assertStringContainsString('✓ No issues detected!', $text);
        self::assertStringNotContainsString('ISSUES DETECTED', $text);
    }

    public function testToTextWithIssuesShowsDetails(): void
    {
        $issues = [
            new Issue('tests/UserTest.php', 'testUserCreation', 'no_assertions', 'Test has no assertions'),
            new Issue('tests/UserTest.php', 'testUserUpdate', 'no_assertions', 'Test has no assertions'),
            new Issue('tests/ProductTest.php', 'testProductPrice', 'other_type', 'Some other issue'),
        ];
        $result = new AnalysisResult(filesScanned: 2, testsFound: 3, issues: $issues);

        $text = $result->toText();

        self::assertStringContainsString('TEST QUALITY ANALYSIS REPORT', $text);
        self::assertStringContainsString('Files scanned: 2', $text);
        self::assertStringContainsString('Tests found: 3', $text);
        self::assertStringContainsString('ISSUES DETECTED', $text);
        self::assertStringContainsString('No assertions: 2', $text);
        self::assertStringContainsString('TOTAL: 3', $text);
        self::assertStringContainsString('DETAILED FINDINGS', $text);
        self::assertStringContainsString('📁 tests/UserTest.php', $text);
        self::assertStringContainsString('testUserCreation', $text);
        self::assertStringContainsString('testUserUpdate', $text);
        self::assertStringContainsString('📁 tests/ProductTest.php', $text);
        self::assertStringContainsString('testProductPrice', $text);
        self::assertStringContainsString('Test has no assertions', $text);
        self::assertStringContainsString('Some other issue', $text);
    }

    public function testToTextShowsChecksRun(): void
    {
        $result = new AnalysisResult(
            filesScanned: 5,
            testsFound: 10,
            issues: [],
            checksRun: ['Assertion Count', 'Sleepy Test', 'Redundant Print'],
        );

        $text = $result->toText();

        self::assertStringContainsString('CHECKS RUN', $text);
        self::assertStringContainsString('✓ Assertion Count', $text);
        self::assertStringContainsString('✓ Sleepy Test', $text);
        self::assertStringContainsString('✓ Redundant Print', $text);
    }
}

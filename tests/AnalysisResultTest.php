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

        self::assertSame(10, $result->filesScanned, 'Result should keep the files scanned count');
        self::assertSame(50, $result->testsFound, 'Result should keep the tests found count');
        self::assertFalse($result->hasIssues(), 'Result without issues should report none');
        self::assertSame([], $result->issues, 'Issue list should be empty');
    }

    public function testResultWithIssuesHasIssues(): void
    {
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'No assertions');
        $result = new AnalysisResult(filesScanned: 1, testsFound: 1, issues: [$issue]);

        self::assertTrue($result->hasIssues(), 'Result with an issue should report issues');
        self::assertCount(1, $result->issues, 'Result should hold the single issue');
    }

    public function testToJsonReturnsValidJson(): void
    {
        $issue = new Issue('test.php', 'testX', 'no_assertions', 'No assertions');
        $result = new AnalysisResult(filesScanned: 1, testsFound: 1, issues: [$issue]);

        $json = $result->toJson();
        $decoded = json_decode($json, true);

        self::assertSame(1, $decoded['summary']['files_scanned'], 'JSON summary should include files scanned');
        self::assertSame(1, $decoded['summary']['tests_found'], 'JSON summary should include tests found');
        self::assertSame(1, $decoded['issues']['no_assertions'], 'JSON should count issues by type');
        self::assertCount(1, $decoded['details'], 'JSON details should list each issue');
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

        self::assertSame(2, $counts['no_assertions'], 'Both no_assertions issues should be counted');
        self::assertSame(1, $counts['other_type'], 'The other_type issue should be counted separately');
    }

    public function testToTextWithNoIssuesShowsSuccess(): void
    {
        $result = new AnalysisResult(filesScanned: 10, testsFound: 50, issues: []);

        $text = $result->toText();

        self::assertStringContainsString('TEST QUALITY ANALYSIS REPORT', $text, 'Report should have its title');
        self::assertStringContainsString('Files scanned: 10', $text, 'Report should show files scanned');
        self::assertStringContainsString('Tests found: 50', $text, 'Report should show tests found');
        self::assertStringContainsString('✓ No issues detected!', $text, 'Report should announce a clean run');
        self::assertStringNotContainsString('ISSUES DETECTED', $text, 'Clean report should have no issues section');
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

        self::assertStringContainsString('TEST QUALITY ANALYSIS REPORT', $text, 'Report should have its title');
        self::assertStringContainsString('Files scanned: 2', $text, 'Report should show files scanned');
        self::assertStringContainsString('Tests found: 3', $text, 'Report should show tests found');
        self::assertStringContainsString('ISSUES DETECTED', $text, 'Report should have an issues section');
        self::assertStringContainsString('No assertions: 2', $text, 'Report should count issues by type');
        self::assertStringContainsString('TOTAL: 3', $text, 'Report should show the total issue count');
        self::assertStringContainsString('DETAILED FINDINGS', $text, 'Report should have a findings section');
        self::assertStringContainsString('📁 tests/UserTest.php', $text, 'Findings should name the first file');
        self::assertStringContainsString('testUserCreation', $text, 'Findings should name the first failing test');
        self::assertStringContainsString('testUserUpdate', $text, 'Findings should name the second failing test');
        self::assertStringContainsString('📁 tests/ProductTest.php', $text, 'Findings should name the second file');
        self::assertStringContainsString('testProductPrice', $text, 'Findings should name the test in the second file');
        self::assertStringContainsString('Test has no assertions', $text, 'Findings should include the no_assertions message');
        self::assertStringContainsString('Some other issue', $text, 'Findings should include the other_type message');
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

        self::assertStringContainsString('CHECKS RUN', $text, 'Report should have a checks run section');
        self::assertStringContainsString('✓ Assertion Count', $text, 'Report should list the Assertion Count check');
        self::assertStringContainsString('✓ Sleepy Test', $text, 'Report should list the Sleepy Test check');
        self::assertStringContainsString('✓ Redundant Print', $text, 'Report should list the Redundant Print check');
    }
}

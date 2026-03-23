<?php

declare(strict_types=1);

namespace TestQualityAnalyzer;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\Parser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final class Analyzer
{
    private Parser $parser;

    /** @var VisitorInterface[] */
    private array $visitors = [];

    private int $filesScanned = 0;
    private int $testsFound = 0;

    /** @var Issue[] */
    private array $issues = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function addVisitor(VisitorInterface $visitor): void
    {
        $this->visitors[] = $visitor;
    }

    /**
     * Get registered smell types with their names
     *
     * @return array<array{type: string, name: string}>
     */
    public function getRegisteredTypes(): array
    {
        return array_map(
            fn(VisitorInterface $visitor) => [
                'type' => $visitor->getType(),
                'name' => $visitor->getName(),
            ],
            $this->visitors
        );
    }

    public function analyzeDirectory(string $directory): AnalysisResult
    {
        $this->filesScanned = 0;
        $this->testsFound = 0;
        $this->issues = [];

        if (!is_dir($directory)) {
            return new AnalysisResult(0, 0, []);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        $testFiles = new RegexIterator($iterator, '/.*Test\.php$/');

        foreach ($testFiles as $file) {
            $this->analyzeFile($file->getPathname(), $directory);
        }

        $checksRun = array_map(
            fn(VisitorInterface $visitor) => $visitor->getName(),
            $this->visitors
        );

        return new AnalysisResult(
            filesScanned: $this->filesScanned,
            testsFound: $this->testsFound,
            issues: $this->issues,
            checksRun: $checksRun,
        );
    }

    private function analyzeFile(string $filepath, string $baseDir): void
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return;
        }

        $ast = $this->parser->parse($content);
        if ($ast === null) {
            return;
        }

        $this->filesScanned++;
        $relativePath = $this->getRelativePath($filepath, $baseDir);

        // Reset all visitors and set current file
        foreach ($this->visitors as $visitor) {
            $visitor->reset();
            $visitor->setCurrentFile($relativePath);
        }

        // Create traverser and add all visitors
        $traverser = new NodeTraverser();
        foreach ($this->visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        // Count test methods
        $this->testsFound += $this->countTestMethods($ast);

        // Traverse once with all visitors
        $traverser->traverse($ast);

        // Collect issues from all visitors
        foreach ($this->visitors as $visitor) {
            foreach ($visitor->getIssues() as $issue) {
                $this->issues[] = $issue;
            }
        }
    }

    /**
     * @param \PhpParser\Node[] $ast
     */
    private function countTestMethods(array $ast): int
    {
        $count = 0;
        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Class_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod) {
                        if (str_starts_with($stmt->name->name, 'test') || $this->hasTestAttribute($stmt)) {
                            $count++;
                        }
                    }
                }
            }
        }
        return $count;
    }

    private function hasTestAttribute(\PhpParser\Node\Stmt\ClassMethod $node): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                if ($attrName === 'Test' || str_ends_with($attrName, '\Test')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getRelativePath(string $filepath, string $baseDir): string
    {
        $realBase = realpath($baseDir) ?: $baseDir;
        $realFile = realpath($filepath) ?: $filepath;

        if (str_starts_with($realFile, $realBase)) {
            return ltrim(substr($realFile, strlen($realBase)), '/');
        }
        return $filepath;
    }
}

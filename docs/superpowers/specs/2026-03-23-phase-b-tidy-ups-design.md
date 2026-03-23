# Phase B: Tidy-Ups Design

## Goal

Clean up structural issues inherited from the extraction. No new features — just make the codebase consistent, DRY, and passing static analysis cleanly.

## Changes

### 1. Extract AbstractTestVisitor base class

**Create:** `src/TestQualityAnalyzer/Visitor/AbstractTestVisitor.php`

Namespace: `TestQualityAnalyzer\Visitor` (same as the other visitors).

An abstract class that provides shared boilerplate:

```php
abstract class AbstractTestVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    protected ?string $currentFile = null;

    public function setCurrentFile(string $file): void { ... }
    protected function isTestMethod(ClassMethod $node): bool { ... }
    abstract public function getType(): string;
    abstract public function getName(): string;
}
```

**Modify:** 13 of the 14 visitors (all except `ConstructorInitializationVisitor`):
- Remove the `final` keyword from the class declaration
- Change `extends NodeVisitorAbstract implements VisitorInterface` to `extends AbstractTestVisitor`
- Remove duplicated `isTestMethod()` method
- Remove duplicated `$currentFile` property (was `private`, now inherited as `protected`) and `setCurrentFile()` method
- Keep each visitor's own `$issues`, `getIssues()`, and `reset()` (these vary per visitor)
- In each visitor's `reset()`, add `$this->currentFile = null;` if not already present (the base class property must be cleaned up)

**Exception — `ConstructorInitializationVisitor`:** This visitor operates at the class level using `isTestClass()`, not `isTestMethod()`. It still benefits from the base class for `$currentFile` and `setCurrentFile()`:
- Remove `final`, change to `extends AbstractTestVisitor`
- Remove its own `$currentFile` and `setCurrentFile()`
- Keep its own `isTestClass()` method (unrelated to `isTestMethod()`)

### 2. Fix PHPStan errors and alignment

**Modify:** `composer.json` — change the `analyze` script from:
```json
"analyze": "phpstan analyse src tests --level=max"
```
to:
```json
"analyze": "phpstan analyse"
```
This defers to `phpstan.neon` (level 6, paths: src + tests). Level 6 is the appropriate target for this codebase — level max surfaces many false positives from php-parser's loosely-typed AST nodes.

**Modify:** Fix all PHPStan level-6 errors. Known issues:
- `AssertionRouletteVisitor.php:186` — accesses `->value` on `Arg|VariadicPlaceholder` without type guard. Fix: check `instanceof Arg` before accessing `->value`. Same issue in `assertionHasMessage()` method.
- Scan for similar patterns in other visitors (e.g. `InterfaceTestingVisitor`, `MagicNumberTestVisitor`) where `$arg->value` is accessed without guarding against `VariadicPlaceholder`.

Run `composer analyze` after fixes and iterate until zero errors.

### 3. Enable ConditionalTestLogicVisitor

**Modify:** `bin/analyze` — uncomment the `ConditionalTestLogicVisitor` registration (line 130) so it runs by default. Users who find it noisy can use `--only=` to exclude it.

**Note:** This changes the default output. CLI integration tests may need updating if they assert on specific issue counts or detector lists.

### 4. Trivial fixes

**Remove:** `bin/.gitkeep`, `src/TestQualityAnalyzer/Visitor/.gitkeep`, `tests/.gitkeep` (directories contain real files).

**Modify:** `composer.json` — correct description spelling from `"analyzer"` to `"analyser"`:
```json
"description": "AST-based test quality analyser for PHPUnit tests"
```

**Create:** `README.md` — minimal content as a bullet list:
- Package name and one-line description
- Requirements (PHP >= 8.4)
- Installation (`composer require --dev`)
- Basic usage (`bin/analyze`, `--format`, `--baseline`, `--only`)
- Table of detectors: type key and name for each of the 14 visitors
- Licence (MIT)

## What Stays Unchanged

- `VisitorInterface` — remains as-is; `AbstractTestVisitor` implements it
- `Analyzer`, `AnalysisResult`, `Baseline`, `Issue` — no changes
- All visitor detection logic — only shared boilerplate is extracted
- Test fixtures under `var/`
- `Dockerfile`, `phpunit.xml`

## Verification Criteria

1. `composer test` — all existing tests pass (no regressions)
2. `composer analyze` — PHPStan passes cleanly at level 6 via the updated composer script, zero errors
3. `bin/analyze var/test-examples/` — still works correctly
4. `bin/analyze --list-types` — lists all 14 detectors including `conditional_test_logic`
5. Each visitor still has its own test file and all pass
6. `.gitkeep` files no longer present; directories still exist with their real files

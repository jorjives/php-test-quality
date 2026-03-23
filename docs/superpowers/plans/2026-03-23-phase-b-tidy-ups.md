# Phase B: Tidy-Ups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clean up structural issues inherited from extraction — extract shared visitor boilerplate, fix PHPStan, enable disabled visitor, add README.

**Architecture:** Create `AbstractTestVisitor` base class with shared `isTestMethod()` and `$currentFile` boilerplate. All 14 visitors extend it instead of `NodeVisitorAbstract`. Fix PHPStan level alignment and the one level-6 error. Enable the commented-out visitor. Trivial cleanup.

**Tech Stack:** PHP 8.4, nikic/php-parser 5.x, PHPUnit 11, PHPStan 2

**Spec:** `docs/superpowers/specs/2026-03-23-phase-b-tidy-ups-design.md`

**Important:** PHP is not available on the host. All commands must run via Docker:
- Tests: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit`
- PHPStan: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpstan analyse --memory-limit=512M`
- CLI: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php bin/analyze <args>`

---

## File Structure

| Action | File | Purpose |
|--------|------|---------|
| Create | `src/TestQualityAnalyzer/Visitor/AbstractTestVisitor.php` | Base class with shared `isTestMethod()`, `$currentFile`, `setCurrentFile()` |
| Modify | 13 visitors (all except `ConstructorInitializationVisitor`) | Remove `final`, extend `AbstractTestVisitor`, remove duplicated methods |
| Modify | `src/TestQualityAnalyzer/Visitor/ConstructorInitializationVisitor.php` | Remove `final`, extend `AbstractTestVisitor`, remove `$currentFile`/`setCurrentFile()` only (keeps its own `isTestClass()`) |
| Modify | `src/TestQualityAnalyzer/Visitor/AssertionRouletteVisitor.php` | Fix PHPStan error: type guard on `$arg->value` access |
| Modify | `composer.json` | Fix `analyze` script, correct description spelling |
| Modify | `bin/analyze` | Uncomment `ConditionalTestLogicVisitor` |
| Remove | `bin/.gitkeep`, `src/TestQualityAnalyzer/Visitor/.gitkeep`, `tests/.gitkeep` |
| Create | `README.md` | Minimal project documentation |

---

### Task 1: Create AbstractTestVisitor base class

**Files:**
- Create: `src/TestQualityAnalyzer/Visitor/AbstractTestVisitor.php`

- [ ] **Step 1: Create the base class**

Create `src/TestQualityAnalyzer/Visitor/AbstractTestVisitor.php` with this content:

```php
<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\VisitorInterface;

abstract class AbstractTestVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    protected ?string $currentFile = null;

    public function setCurrentFile(string $file): void
    {
        $this->currentFile = $file;
    }

    protected function isTestMethod(ClassMethod $node): bool
    {
        if (str_starts_with($node->name->name, 'test')) {
            return true;
        }

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
}
```

- [ ] **Step 2: Run tests to verify no regressions**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all 264 tests pass (the new class exists but nothing uses it yet).

- [ ] **Step 3: Commit**

```bash
git add src/TestQualityAnalyzer/Visitor/AbstractTestVisitor.php
git commit -m "feat: add AbstractTestVisitor base class with shared isTestMethod()"
```

---

### Task 2: Migrate 13 visitors to AbstractTestVisitor

Each of the following 13 visitors needs the same transformation:
- `AssertionCountVisitor.php`
- `AssertionRouletteVisitor.php`
- `ConditionalTestLogicVisitor.php`
- `EmptyTestVisitor.php`
- `ExceptionHandlingVisitor.php`
- `InterfaceTestingVisitor.php`
- `LongTestVisitor.php`
- `MagicNumberTestVisitor.php`
- `MysteryGuestVisitor.php`
- `RedundantAssertionVisitor.php`
- `RedundantPrintVisitor.php`
- `RottenGreenTestVisitor.php`
- `SleepyTestVisitor.php`

**Files:**
- Modify: all 13 files in `src/TestQualityAnalyzer/Visitor/`

For each visitor, apply these changes:

- [ ] **Step 1: Transform all 13 visitors**

For each of the 13 visitors listed above, make these edits:

1. Remove the `final` keyword from the class declaration
2. Change `extends NodeVisitorAbstract implements VisitorInterface` to `extends AbstractTestVisitor`
3. Remove the `use` imports that are no longer needed: `PhpParser\NodeVisitorAbstract`, `TestQualityAnalyzer\VisitorInterface` (unless used elsewhere in the file), and `PhpParser\Node\Stmt\ClassMethod` (only if the visitor doesn't use `ClassMethod` in its own code beyond `isTestMethod`)
4. Remove the entire `isTestMethod()` method
5. Remove the `private ?string $currentFile = null;` property declaration
6. Remove the entire `setCurrentFile()` method
7. In the visitor's `reset()` method, add `$this->currentFile = null;` (none of the 13 visitors currently reset this — it must be added to every one)

**Important:** Many visitors use `ClassMethod` in their own `enterNode()`/`leaveNode()` — do NOT remove that import if so. Only remove imports that become truly unused. Do NOT add a `use` import for `AbstractTestVisitor` — it is in the same namespace as the visitors.

- [ ] **Step 2: Run tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all 264 tests pass.

- [ ] **Step 3: Commit**

```bash
git add src/TestQualityAnalyzer/Visitor/
git commit -m "refactor: migrate 13 visitors to AbstractTestVisitor base class"
```

---

### Task 3: Migrate ConstructorInitializationVisitor

**Files:**
- Modify: `src/TestQualityAnalyzer/Visitor/ConstructorInitializationVisitor.php`

This visitor is special — it uses `isTestClass()` (class-level detection), not `isTestMethod()`. It still benefits from the shared `$currentFile` and `setCurrentFile()`.

- [ ] **Step 1: Transform ConstructorInitializationVisitor**

1. Remove the `final` keyword
2. Change `extends NodeVisitorAbstract implements VisitorInterface` to `extends AbstractTestVisitor`
3. Remove the `use` imports for `NodeVisitorAbstract` and `VisitorInterface`
4. Remove `private ?string $currentFile = null;` property
5. Remove the `setCurrentFile()` method
6. Keep `isTestClass()` — it is NOT the same as `isTestMethod()`
7. In `reset()`, add `$this->currentFile = null;` (same as the other 13 — none currently reset it)

- [ ] **Step 2: Run tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all 264 tests pass.

- [ ] **Step 3: Commit**

```bash
git add src/TestQualityAnalyzer/Visitor/ConstructorInitializationVisitor.php
git commit -m "refactor: migrate ConstructorInitializationVisitor to AbstractTestVisitor"
```

---

### Task 4: Fix PHPStan errors and align composer script

**Files:**
- Modify: `src/TestQualityAnalyzer/Visitor/AssertionRouletteVisitor.php`
- Modify: `composer.json`
- Potentially modify: any other visitors where PHPStan reports `$arg->value` access on `Arg|VariadicPlaceholder`

- [ ] **Step 1: Fix composer analyze script**

In `composer.json`, change:
```json
"analyze": "phpstan analyse src tests --level=max"
```
to:
```json
"analyze": "phpstan analyse"
```

- [ ] **Step 2: Run PHPStan to discover all level-6 errors**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpstan analyse --memory-limit=512M
```

Note every error reported. The root cause is accessing `->value` on `Arg|VariadicPlaceholder` — `VariadicPlaceholder` has no `->value` property. Known locations:

- `AssertionRouletteVisitor::hasLastArgAsStringLiteral()` — `$args[count($args) - 1]->value`
- `AssertionRouletteVisitor::assertionHasMessage()` — `@param` docblock types `array<int, Node>` instead of the correct `array<int, Arg|VariadicPlaceholder>`
- `MagicNumberTestVisitor::checkAssertionForMagicNumbers()` — `$arg->value` in foreach
- `RedundantAssertionVisitor` — multiple `$node->args[0]->value` / `$node->args[1]->value` accesses
- `InterfaceTestingVisitor::extractMockedType()` / `extractExpectedTypeFromAssertion()` — `$firstArg->value`

The fix pattern is the same everywhere: guard with `instanceof \PhpParser\Node\Arg` before accessing `->value`. Also fix incorrect `@param` docblocks.

- [ ] **Step 3: Fix all reported PHPStan errors**

Apply the `instanceof Arg` guard pattern to every reported error. For example in `AssertionRouletteVisitor::hasLastArgAsStringLiteral()`:

```php
private function hasLastArgAsStringLiteral(array $args): bool
{
    if (count($args) === 0) {
        return false;
    }

    $lastArg = $args[count($args) - 1];
    if (!$lastArg instanceof \PhpParser\Node\Arg) {
        return false;
    }

    return $lastArg->value instanceof String_;
}
```

Apply the same pattern in all other affected methods. Fix `@param` docblock types where they incorrectly type `$args` as `array<int, Node>`.

- [ ] **Step 4: Re-run PHPStan until zero errors**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpstan analyse --memory-limit=512M
```

Expected: zero errors. If errors remain, fix them and re-run. Iterate until clean.

- [ ] **Step 5: Run tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "fix: resolve all PHPStan level-6 errors and align analyse script with phpstan.neon"
```

---

### Task 5: Enable ConditionalTestLogicVisitor

**Files:**
- Modify: `bin/analyze`

- [ ] **Step 1: Uncomment the visitor registration**

In `bin/analyze`, line ~130, change:
```php
    // new ConditionalTestLogicVisitor(),
```
to:
```php
    new ConditionalTestLogicVisitor(),
```

- [ ] **Step 2: Verify with --list-types**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php bin/analyze --list-types
```

Expected: output includes `conditional_test_logic  Conditional Test Logic` among the 14 listed detectors.

- [ ] **Step 3: Run tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass. (CLI integration tests don't use fixtures with conditionals, so no test updates needed.)

- [ ] **Step 4: Commit**

```bash
git add bin/analyze
git commit -m "feat: enable ConditionalTestLogicVisitor by default"
```

---

### Task 6: Trivial fixes — .gitkeep, description, README

**Files:**
- Remove: `bin/.gitkeep`, `src/TestQualityAnalyzer/Visitor/.gitkeep`, `tests/.gitkeep`
- Modify: `composer.json`
- Create: `README.md`

- [ ] **Step 1: Remove .gitkeep files**

```bash
rm bin/.gitkeep src/TestQualityAnalyzer/Visitor/.gitkeep tests/.gitkeep
```

- [ ] **Step 2: Fix composer.json description**

In `composer.json`, change:
```json
"description": "AST-based test quality analyzer for PHPUnit tests"
```
to:
```json
"description": "AST-based test quality analyser for PHPUnit tests"
```

- [ ] **Step 3: Create README.md**

Create `README.md` with this content:

```markdown
# php-test-quality

AST-based test quality analyser for PHPUnit tests. Detects common test smells by parsing PHP test files and walking the abstract syntax tree.

## Requirements

- PHP >= 8.4

## Installation

```bash
composer require --dev jorj-sh/php-test-quality
```

Or clone and install directly:

```bash
git clone https://github.com/jorjives/php-test-quality.git
cd php-test-quality
composer install
```

## Usage

```bash
# Analyse a test directory
bin/analyze path/to/tests/

# JSON output
bin/analyze path/to/tests/ --format=json

# Run only specific detectors
bin/analyze path/to/tests/ --only=no_assertions,assertion_roulette

# List available detectors
bin/analyze --list-types

# Generate a baseline (requires --reason)
bin/analyze path/to/tests/ --generate-baseline --baseline --reason="Initial baseline"

# Filter using a baseline
bin/analyze path/to/tests/ --baseline
```

## Detectors

| Type | Name | Description |
|------|------|-------------|
| `no_assertions` | Assertion Count | Tests with no assertions |
| `assertion_roulette` | Assertion Roulette | Multiple assertions without descriptive messages |
| `constructor_initialization` | Constructor Initialization | Test classes using `__construct()` instead of `setUp()` |
| `empty_test` | Empty Test | Test methods with no executable statements |
| `sleepy_test` | Sleepy Test | Tests using `sleep()`/`usleep()` |
| `redundant_print` | Redundant Print | Debug output (`var_dump`, `echo`, etc.) in tests |
| `exception_handling` | Exception Handling | Try-catch blocks instead of `expectException()` |
| `interface_testing` | Interface Testing Anti-Patterns | Mock-only interface testing |
| `conditional_test_logic` | Conditional Test Logic | `if`/`switch`/ternary in tests |
| `magic_number_test` | Magic Number Test | Non-trivial numeric literals in assertions |
| `redundant_assertion` | Redundant Assertion | Tautological assertions like `assertEquals(1, 1)` |
| `rotten_green_test` | Rotten Green Test | Assertions inside conditionals or after return/throw |
| `mystery_guest` | Mystery Guest | File I/O or database calls in test bodies |
| `long_test` | Long Test | Tests exceeding 40 lines |

## Docker

```bash
docker build -t php-test-quality .
docker run --rm -v /path/to/tests:/tests php-test-quality /tests
```

## Licence

MIT
```

- [ ] **Step 4: Run tests to verify nothing broke**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: remove .gitkeep files, fix description, add README"
```

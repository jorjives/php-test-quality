# Phase C: Modernise Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace hand-rolled CLI with Symfony Console, add YAML config with configurable thresholds, support global Composer install via `tq` binary.

**Architecture:** Add `symfony/console` and `symfony/yaml` dependencies. Create `Configuration` class for loading/merging YAML config. Create `AnalyzeCommand` and `ListTypesCommand` as Symfony Console commands. Make `LongTestVisitor` and `MagicNumberTestVisitor` thresholds constructor-injectable. Replace `bin/analyze` with `bin/tq`.

**Tech Stack:** PHP 8.4, Symfony Console 7, Symfony YAML 7, nikic/php-parser 5.x, PHPUnit 11, PHPStan 2

**Spec:** `docs/superpowers/specs/2026-03-24-phase-c-modernise-design.md`

**Important:** PHP is not available on the host. All commands must run via Docker:
- Composer: `docker run --rm -v "$(pwd)":/app -w /app composer:2 <command>`
- Tests: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit`
- PHPStan: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpstan analyse --memory-limit=512M`
- CLI: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php bin/tq <args>`

---

## File Structure

| Action | File | Purpose |
|--------|------|---------|
| Create | `src/TestQualityAnalyzer/Configuration.php` | Load, merge, validate YAML config |
| Create | `src/TestQualityAnalyzer/Command/AnalyzeCommand.php` | Symfony Console command — main analysis |
| Create | `src/TestQualityAnalyzer/Command/ListTypesCommand.php` | Symfony Console command — list detectors |
| Create | `bin/tq` | New CLI entrypoint |
| Create | `tests/ConfigurationTest.php` | Config loading/merging/validation tests |
| Create | `tests/Command/AnalyzeCommandTest.php` | AnalyzeCommand integration tests |
| Create | `tests/Command/ListTypesCommandTest.php` | ListTypesCommand tests |
| Modify | `src/TestQualityAnalyzer/Visitor/LongTestVisitor.php` | Configurable line threshold |
| Modify | `src/TestQualityAnalyzer/Visitor/MagicNumberTestVisitor.php` | Configurable allowlist |
| Modify | `tests/Visitor/LongTestVisitorTest.php` | Test custom threshold |
| Modify | `tests/Visitor/MagicNumberTestVisitorTest.php` | Test custom allowlist |
| Modify | `tests/CliIntegrationTest.php` | Update to use `bin/tq` |
| Modify | `composer.json` | New deps, bin field |
| Modify | `Dockerfile` | Update entrypoint |
| Modify | `README.md` | Update docs |
| Remove | `bin/analyze` | Replaced by `bin/tq` |

---

### Task 1: Add Symfony dependencies and update composer.json

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add dependencies and bin field**

In `composer.json`:
1. Add `"symfony/console": "^7.0"` and `"symfony/yaml": "^7.0"` to the `require` section
2. Add `"bin": ["bin/tq"]` at the top level (after `"license"`)

- [ ] **Step 2: Run composer update**

```bash
docker run --rm -v "$(pwd)":/app -w /app composer:2 update
```

Expected: installs Symfony Console, YAML, and their dependencies.

- [ ] **Step 3: Run existing tests to verify no regressions**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all 264 tests pass.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add symfony/console and symfony/yaml dependencies"
```

---

### Task 2: Make LongTestVisitor threshold configurable

**Files:**
- Modify: `src/TestQualityAnalyzer/Visitor/LongTestVisitor.php`
- Modify: `tests/Visitor/LongTestVisitorTest.php`

- [ ] **Step 1: Add test for custom threshold**

Add this test to `tests/Visitor/LongTestVisitorTest.php`:

```php
public function testCustomThresholdDetectsShorterTests(): void
{
    $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testMediumLength(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $d = 4;
        $e = 5;
        $f = 6;
        $g = 7;
        $h = 8;
        $i = 9;
        $j = 10;
        $k = 11;
        $l = 12;
        $m = 13;
        $n = 14;
        $o = 15;
        self::assertTrue(true);
    }
}
PHP;

    $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
    $ast = $parser->parse($code);

    $visitor = new LongTestVisitor(lineThreshold: 15);
    $traverser = new \PhpParser\NodeTraverser();
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    $issues = $visitor->getIssues();
    self::assertCount(1, $issues, 'Custom threshold of 15 should flag this test');
    self::assertStringContainsString('15', $issues[0]->message, 'Message should show configured threshold');
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit --filter=testCustomThresholdDetectsShorterTests
```

Expected: FAIL (constructor doesn't accept parameters yet).

- [ ] **Step 3: Implement configurable threshold**

In `src/TestQualityAnalyzer/Visitor/LongTestVisitor.php`:

1. Remove `private const LINE_THRESHOLD = 40;`
2. Add constructor: `public function __construct(private readonly int $lineThreshold = 40) {}`
3. Replace `self::LINE_THRESHOLD` with `$this->lineThreshold` in both the comparison (line ~30) and the `sprintf` message (line ~38)

- [ ] **Step 4: Run all tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass (existing tests use default constructor, new test uses custom threshold).

- [ ] **Step 5: Commit**

```bash
git add src/TestQualityAnalyzer/Visitor/LongTestVisitor.php tests/Visitor/LongTestVisitorTest.php
git commit -m "feat: make LongTestVisitor threshold configurable via constructor"
```

---

### Task 3: Make MagicNumberTestVisitor allowlist configurable

**Files:**
- Modify: `src/TestQualityAnalyzer/Visitor/MagicNumberTestVisitor.php`
- Modify: `tests/Visitor/MagicNumberTestVisitorTest.php`

- [ ] **Step 1: Add test for custom allowlist**

Add this test to `tests/Visitor/MagicNumberTestVisitorTest.php`:

```php
public function testCustomAllowlistPermitsSpecificNumbers(): void
{
    $code = <<<'PHP'
<?php
class SomeTest extends TestCase {
    public function testSomething(): void
    {
        $this->assertEquals(12345, $result);
    }
}
PHP;

    $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
    $ast = $parser->parse($code);

    // 12345 is normally flagged, but our custom allowlist includes it
    $visitor = new MagicNumberTestVisitor(trivialValues: [0, 1, 12345]);
    $traverser = new \PhpParser\NodeTraverser();
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    self::assertCount(0, $visitor->getIssues(), 'Custom allowlist should permit 12345');
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit --filter=testCustomAllowlistPermitsSpecificNumbers
```

Expected: FAIL.

- [ ] **Step 3: Implement configurable allowlist**

In `src/TestQualityAnalyzer/Visitor/MagicNumberTestVisitor.php`:

1. Rename `private const TRIVIAL_VALUES` to `public const DEFAULT_TRIVIAL_VALUES`
2. Add constructor: `public function __construct(private readonly array $trivialValues = self::DEFAULT_TRIVIAL_VALUES) {}`
3. Replace all references to `self::TRIVIAL_VALUES` with `$this->trivialValues` (4 occurrences in `checkAssertionForMagicNumbers`)

- [ ] **Step 4: Run all tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/TestQualityAnalyzer/Visitor/MagicNumberTestVisitor.php tests/Visitor/MagicNumberTestVisitorTest.php
git commit -m "feat: make MagicNumberTestVisitor allowlist configurable via constructor"
```

---

### Task 4: Create Configuration class

**Files:**
- Create: `src/TestQualityAnalyzer/Configuration.php`
- Create: `tests/ConfigurationTest.php`

- [ ] **Step 1: Write Configuration tests**

Create `tests/ConfigurationTest.php` with tests for:
- `testDefaultsReturnExpectedValues` — verify `defaults()` returns long_test=40, default magic number list, enabled=null (all), disabled=[], baseline=`.tq-baseline.json`
- `testFromFileLoadsYaml` — write a temp YAML file with custom values, load it, verify getters
- `testFromFileWithLongTestThreshold` — YAML sets `thresholds.long_test: 20`, verify `getLongTestThreshold()` returns 20
- `testMagicNumberAllowlistReplacesDefault` — YAML sets `thresholds.magic_number_allowlist: [0, 1, 42]`, verify `getMagicNumberAllowlist()` returns `[0, 1, 42]` (not the full default list)
- `testMagicNumberAllowlistExtraAppendsToDefault` — YAML sets `thresholds.magic_number_allowlist_extra: [42, 99]`, verify result is defaults + [42, 99]
- `testMagicNumberAllowlistWithExtra` — YAML sets both `magic_number_allowlist: [0, 1]` and `magic_number_allowlist_extra: [42]`, verify result is `[0, 1, 42]`
- `testMergeOverridesScalars` — merge two configs, verify higher-priority scalars win
- `testMergeReplacesAllowlist` — merge config with custom allowlist, verify it replaces
- `testMergeUnionsAllowlistExtra` — merge two configs with different extra values, verify union
- `testMergeUnionsDisabledDetectors` — merge two disabled lists, verify union
- `testDetectorsEnabledList` — YAML sets `detectors.enabled: [no_assertions, long_test]`, verify getter
- `testDetectorsDisabledList` — YAML sets `detectors.disabled: [conditional_test_logic]`, verify getter
- `testInvalidThresholdThrows` — YAML sets `thresholds.long_test: -1`, verify exception
- `testInvalidYamlThrows` — write invalid YAML content, verify exception on load
- `testMissingFileThrows` — call `fromFile` with non-existent path, verify exception

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit tests/ConfigurationTest.php
```

Expected: FAIL (class doesn't exist yet).

- [ ] **Step 3: Implement Configuration class**

Create `src/TestQualityAnalyzer/Configuration.php`:

```php
<?php
declare(strict_types=1);
namespace TestQualityAnalyzer;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use TestQualityAnalyzer\Visitor\MagicNumberTestVisitor;
```

The class should:
- Store all config values as private readonly properties
- `static defaults()` — returns config with all hardcoded defaults (long_test=40, magic_number_allowlist=MagicNumberTestVisitor::DEFAULT_TRIVIAL_VALUES, etc.)
- `static fromFile(string $path)` — parse YAML, validate, return config. Throws RuntimeException if file missing or invalid.
- `merge(self $override)` — returns new Configuration with higher-priority values applied per the merge rules in the spec
- Validation: `long_test` must be > 0
- Getters for all values

- [ ] **Step 4: Run tests until green**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit tests/ConfigurationTest.php
```

Expected: all Configuration tests pass.

- [ ] **Step 5: Run all tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/TestQualityAnalyzer/Configuration.php tests/ConfigurationTest.php
git commit -m "feat: add Configuration class with YAML loading and merge support"
```

---

### Task 5: Create ListTypesCommand

**Files:**
- Create: `src/TestQualityAnalyzer/Command/ListTypesCommand.php`
- Create: `tests/Command/ListTypesCommandTest.php`

- [ ] **Step 1: Write ListTypesCommand test**

Create `tests/Command/ListTypesCommandTest.php`:

```php
<?php
declare(strict_types=1);
namespace TestQualityAnalyzer\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TestQualityAnalyzer\Command\ListTypesCommand;

final class ListTypesCommandTest extends TestCase
{
    public function testListsAllDetectorTypes(): void
    {
        $application = new Application();
        $application->add(new ListTypesCommand());
        $command = $application->find('list-types');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertSame(0, $tester->getStatusCode());

        // Verify all known detector types appear in output
        $expectedTypes = [
            'no_assertions', 'assertion_roulette', 'constructor_initialization',
            'empty_test', 'sleepy_test', 'redundant_print', 'exception_handling',
            'interface_testing', 'conditional_test_logic', 'magic_number_test',
            'redundant_assertion', 'rotten_green_test', 'mystery_guest', 'long_test',
        ];

        foreach ($expectedTypes as $type) {
            self::assertStringContainsString($type, $output, "Output should contain detector type: {$type}");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit tests/Command/ListTypesCommandTest.php
```

Expected: FAIL.

- [ ] **Step 3: Implement ListTypesCommand**

Create `src/TestQualityAnalyzer/Command/ListTypesCommand.php`. The command:
- Name: `list-types`
- Description: `List available test smell detector types`
- Creates all 14 visitor instances, iterates and prints `type` + `name` for each
- Returns `Command::SUCCESS`

- [ ] **Step 4: Run test**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit tests/Command/ListTypesCommandTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/TestQualityAnalyzer/Command/ListTypesCommand.php tests/Command/ListTypesCommandTest.php
git commit -m "feat: add ListTypesCommand for Symfony Console"
```

---

### Task 6: Create AnalyzeCommand

This is the largest task — it replaces all logic from `bin/analyze` (310 lines).

**Files:**
- Create: `src/TestQualityAnalyzer/Command/AnalyzeCommand.php`
- Create: `tests/Command/AnalyzeCommandTest.php`

**Important design note on `--baseline` and `--add-to-baseline`:**

Symfony Console's `VALUE_OPTIONAL | VALUE_IS_ARRAY` is a known footgun — bare `--baseline` followed by another flag causes parsing ambiguity. Instead, use this strategy:

- `--baseline` → `VALUE_REQUIRED | VALUE_IS_ARRAY`. Users must always provide a value: `--baseline=.tq-baseline.json` or `--baseline=path1.json --baseline=path2.json`. When no `--baseline` flag is provided at all, the config file's `baseline` key provides the default.
- `--add-to-baseline` → `VALUE_OPTIONAL`. Bare `--add-to-baseline` uses the config default (`.tq-baseline.json`), `--add-to-baseline=path` uses that path.
- `--generate-baseline` → `VALUE_NONE` (flag).

This avoids the parsing ambiguity while preserving all functionality.

- [ ] **Step 1: Write AnalyzeCommand tests**

Create `tests/Command/AnalyzeCommandTest.php` with tests using `CommandTester`. The test class should have a helper:

```php
private function createTester(): CommandTester
{
    $application = new Application();
    $application->add(new AnalyzeCommand());
    $command = $application->find('analyze');
    return new CommandTester($command);
}
```

Tests to write:

- `testAnalyzesDirectoryAndReportsIssues` — run against `var/test-examples/`, verify exit code 1, output contains `no_assertions`
- `testCleanDirectoryExitsZero` — create temp dir with no test files, verify exit code 0
- `testJsonFormatOutput` — run with `--format=json` against `var/test-examples/`, verify output is valid JSON with `json_decode`
- `testOnlyFilterRunsSpecificDetectors` — run with `--only=no_assertions` against `var/test-examples/`, verify output contains `no_assertions` but NOT `assertion_roulette`
- `testGenerateBaselineCreatesFile` — create temp dir with a fixture test file, run with `--generate-baseline --baseline=.tq-baseline.json --reason="test"`, verify file created with expected JSON structure
- `testGenerateBaselineRequiresReason` — run with `--generate-baseline --baseline=.tq-baseline.json` (no reason), verify exit code 1 and error message
- `testBaselineFilteringRemovesKnownIssues` — create temp dir with fixture + baseline file, run with `--baseline=.tq-baseline.json`, verify baselined issues excluded from output
- `testAddToBaselineAppendsIssues` — create temp dir with existing baseline + new fixture, run with `--add-to-baseline --reason="added"`, verify file updated
- `testConfigFileLoadedFromScanDirectory` — write `.tq.yaml` with `thresholds.long_test: 5` in temp dir, create a 10-line test fixture, run against temp dir, verify long_test issue detected (would not be detected at default threshold of 40)
- `testNoConfigFlagSkipsConfigFile` — same setup as above but add `--no-config`, verify long_test NOT detected
- `testConfigFlagLoadsExplicitFile` — write config to a non-default path, run with `--config=/path`, verify it's loaded
- `testInvalidDirectoryReturnsError` — run with non-existent directory, verify error and non-zero exit

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit tests/Command/AnalyzeCommandTest.php
```

Expected: FAIL.

- [ ] **Step 3: Implement AnalyzeCommand**

Create `src/TestQualityAnalyzer/Command/AnalyzeCommand.php`.

**`configure()` method — option definitions:**

```php
protected function configure(): void
{
    $this
        ->setName('analyze')
        ->setDescription('Analyse PHPUnit test files for quality issues')
        ->addArgument('directory', InputArgument::REQUIRED, 'Path to test directory')
        ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text')
        ->addOption('baseline', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Baseline file path(s) for filtering')
        ->addOption('generate-baseline', null, InputOption::VALUE_NONE, 'Generate a new baseline file')
        ->addOption('force', null, InputOption::VALUE_NONE, 'Allow overwriting existing baseline')
        ->addOption('add-to-baseline', null, InputOption::VALUE_OPTIONAL, 'Add issues to baseline file', false)
        ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma-separated detector types to run')
        ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Reason for baseline entries')
        ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to .tq.yaml config file')
        ->addOption('no-config', null, InputOption::VALUE_NONE, 'Skip config file auto-detection');
}
```

Note: `--add-to-baseline` uses `VALUE_OPTIONAL` with default `false`. When bare `--add-to-baseline` is used, value is `null`. When `--add-to-baseline=path`, value is `"path"`. When not used at all, value is `false`. Use `$input->getOption('add-to-baseline') !== false` to check if it was provided.

**`execute()` method — control flow:**

```
1. $directory = $input->getArgument('directory')
2. Validate directory exists, return FAILURE if not

3. Load config:
   if --no-config: $config = Configuration::defaults()
   elif --config=path: $config = Configuration::defaults()->merge(Configuration::fromFile(path))
   else: auto-detect .tq.yaml in $directory; if found, merge with defaults

4. Create visitors:
   $visitors = [all 14 visitors, with LongTestVisitor($config->getLongTestThreshold())
                and MagicNumberTestVisitor($config->getMagicNumberAllowlist())]

5. Filter visitors:
   if --only is set: keep only visitors whose getType() is in the --only list
   else: apply config enabled/disabled (filter by getEnabledDetectors/getDisabledDetectors)

6. Create Analyzer, add filtered visitors, run analyzeDirectory($directory)

7. Baseline workflows (same logic as bin/analyze lines 176-295):
   - if --generate-baseline: require --reason, write baseline to first --baseline path or stdout
   - elif --add-to-baseline: require --reason, merge into baseline file
   - elif --baseline provided: load baseline(s), filter issues

   IMPORTANT: resolve baseline paths relative to $directory (not CWD):
   $resolvedPath = str_starts_with($path, '/') ? $path : rtrim($directory, '/') . '/' . $path

8. Output results via $output->write() (not echo)
   Use $output->getErrorOutput() for stderr messages (replaces fprintf(STDERR, ...))

9. Return Command::SUCCESS (0) or Command::FAILURE (1) based on hasIssues()
   (replaces exit() calls — never use exit() in a Symfony command)
```

Reference the existing `bin/analyze` (lines 120-310) for the exact baseline handling logic. Translate `fprintf(STDERR, ...)` to `$io->error()` or `$output->getErrorOutput()->writeln()`. Translate `exit(1)` to `return Command::FAILURE`.

- [ ] **Step 4: Iterate until all tests pass**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit tests/Command/AnalyzeCommandTest.php
```

Expected: all AnalyzeCommand tests pass.

- [ ] **Step 5: Run all tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/TestQualityAnalyzer/Command/AnalyzeCommand.php tests/Command/AnalyzeCommandTest.php
git commit -m "feat: add AnalyzeCommand — Symfony Console replacement for bin/analyze"
```

---

### Task 7: Create bin/tq and remove bin/analyze

**Files:**
- Create: `bin/tq`
- Remove: `bin/analyze`
- Modify: `tests/CliIntegrationTest.php`

- [ ] **Step 1: Create bin/tq**

Create `bin/tq` with this content:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use TestQualityAnalyzer\Command\AnalyzeCommand;
use TestQualityAnalyzer\Command\ListTypesCommand;

$app = new Application('tq', '1.0.0');
$app->add(new AnalyzeCommand());
$app->add(new ListTypesCommand());
$app->setDefaultCommand('analyze');
$app->run();
```

Make it executable:
```bash
chmod +x bin/tq
```

- [ ] **Step 2: Update CliIntegrationTest**

In `tests/CliIntegrationTest.php`:

1. Change the script path:
```php
$this->analyzeScript = dirname(__DIR__) . '/bin/tq';
```

2. Update command strings that use bare `--baseline` (no value) to use `--baseline=.tq-baseline.json` instead. Symfony Console parses `VALUE_REQUIRED` options differently from the old hand-rolled parser — bare `--baseline` followed by another flag will cause the next flag to be consumed as the baseline value.

   Check `testGenerateBaselineRefusesIfFileExists`, `testGenerateBaselineWithForceOverwritesExisting`, and `testGeneratedBaselineEntriesHaveTimestamps` for instances of `--baseline` that need `=.tq-baseline.json` appended.

3. Verify `--add-to-baseline` bare usage still works (Symfony `VALUE_OPTIONAL` requires value attached with `=`, but bare usage yields `null` which is handled).

- [ ] **Step 3: Run CLI integration tests with new binary**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit tests/CliIntegrationTest.php
```

Expected: all CLI integration tests pass. If any fail due to Symfony Console parsing differences, fix the test command strings to match the new option syntax and re-run.

- [ ] **Step 4: Remove bin/analyze**

```bash
rm bin/analyze
```

- [ ] **Step 5: Verify bin/tq works end-to-end**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php bin/tq var/test-examples/
echo "Exit code: $?"
```

Expected: exits code 1, reports issues.

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php bin/tq list-types
```

Expected: lists all 14 detectors.

- [ ] **Step 6: Run all tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: replace bin/analyze with bin/tq Symfony Console entrypoint"
```

---

### Task 8: Run PHPStan and fix any errors

**Files:**
- Potentially modify: any files with PHPStan errors

- [ ] **Step 1: Run PHPStan**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpstan analyse --memory-limit=512M
```

- [ ] **Step 2: Fix any reported errors**

Apply fixes iteratively until PHPStan reports zero errors.

- [ ] **Step 3: Run all tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 4: Commit (if changes were needed)**

```bash
git add -A
git commit -m "fix: resolve PHPStan errors from Phase C changes"
```

---

### Task 9: Update Dockerfile and README

**Files:**
- Modify: `Dockerfile`
- Modify: `README.md`

- [ ] **Step 1: Update Dockerfile**

Change the entrypoint from:
```dockerfile
ENTRYPOINT ["php", "bin/analyze"]
```
to:
```dockerfile
ENTRYPOINT ["php", "bin/tq"]
```

- [ ] **Step 2: Update README.md**

Update the README to:
1. Change all `bin/analyze` references to `tq` (or `bin/tq` for local usage)
2. Add a **Configuration** section documenting `.tq.yaml` format with example
3. Update the Docker section to use `bin/tq`
4. Add a **Global Installation** section:
   ```bash
   composer global require jorj-sh/php-test-quality
   tq path/to/tests/
   ```

- [ ] **Step 3: Run tests one final time**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add Dockerfile README.md
git commit -m "docs: update Dockerfile entrypoint and README for bin/tq"
```

# Phase C: Modernise Design

## Goal

Replace the hand-rolled CLI with Symfony Console, add YAML-based configuration with configurable thresholds, and support global Composer installation via a `tq` binary.

## New Dependencies

- `symfony/console ^7.0` — CLI framework
- `symfony/yaml ^7.0` — YAML config parser

## Changes

### 1. Symfony Console CLI

**Replace:** `bin/analyze` with `bin/tq`

**Create:** `bin/tq` — thin bootstrap:
```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
use TestQualityAnalyzer\Command\AnalyzeCommand;
use TestQualityAnalyzer\Command\ListTypesCommand;
use Symfony\Component\Console\Application;

$app = new Application('tq', '1.0.0');
$app->add(new AnalyzeCommand());
$app->add(new ListTypesCommand());
$app->setDefaultCommand('analyze');
$app->run();
```

Setting `analyze` as the default command means `tq path/to/tests/` works without typing `tq analyze path/to/tests/`.

**Create:** `src/TestQualityAnalyzer/Command/AnalyzeCommand.php`

A Symfony Console command that replaces all logic from `bin/analyze`:
- Name: `analyze`
- Argument: `directory` (required)
- Options mapping all existing flags:
  - `--format` (`VALUE_REQUIRED`, default: `text`)
  - `--baseline` (`VALUE_OPTIONAL | VALUE_IS_ARRAY`). When used bare (`--baseline`), the value is `null` which the command treats as the default filename `.tq-baseline.json`. When used with a value (`--baseline=path`), uses that path. Repeatable.
  - `--generate-baseline` (`VALUE_NONE`, flag)
  - `--force` (`VALUE_NONE`, flag)
  - `--add-to-baseline` (`VALUE_OPTIONAL`). Bare usage → default filename. With value → that path.
  - `--only` (`VALUE_REQUIRED`, comma-separated detector types)
  - `--reason` (`VALUE_REQUIRED`, string)
  - `--config` (`VALUE_REQUIRED`, path to `.tq.yaml`. Default: auto-detect)
  - `--no-config` (`VALUE_NONE`, flag. Skip config file auto-detection.)

The command:
1. Loads configuration (defaults → `.tq.yaml` → CLI overrides)
2. Creates visitors with config-injected thresholds
3. Filters by `--only` / config enabled/disabled detectors (see precedence rules below)
4. Runs analysis via `Analyzer`
5. Handles baseline generate/add/filter workflows (same logic as current `bin/analyze`, including resolving baseline paths relative to the scan directory)
6. Outputs results (text or JSON)
7. Returns exit code 0 (clean) or 1 (issues found)

**Create:** `src/TestQualityAnalyzer/Command/ListTypesCommand.php`

- Name: `list-types`
- No arguments or options
- Lists all 14 detector types with their names
- Returns exit code 0

### 2. Configuration system

**Create:** `src/TestQualityAnalyzer/Configuration.php`

Responsible for loading and merging config from three sources (lowest to highest priority):

1. **Built-in defaults** — current hardcoded values
2. **`.tq.yaml` file** — auto-detected in the scan directory only (no parent directory traversal), or specified via `--config`
3. **CLI flags** — override everything

Config file format (`.tq.yaml`):
```yaml
thresholds:
  long_test: 40                    # line count threshold
  magic_number_allowlist:          # REPLACES the default list entirely
    - 0
    - 1
    - -1
    - 200
    - 404
  magic_number_allowlist_extra:    # APPENDS to the default list
    - 42
    - 99

detectors:
  enabled: all                     # 'all' or list of type keys
  disabled: []                     # list of type keys to exclude (applied after enabled)

baseline: .tq-baseline.json       # default baseline path
```

**Allowlist semantics:**
- `magic_number_allowlist` — full replacement. If set, the default list is ignored entirely.
- `magic_number_allowlist_extra` — additive. Merges with whichever list is active (default or custom `magic_number_allowlist`).
- If both are set, `magic_number_allowlist_extra` appends to `magic_number_allowlist`.

**Detector filtering precedence:**
1. Config `detectors.enabled` sets the base set (`all` or explicit list)
2. Config `detectors.disabled` removes from that set
3. CLI `--only` overrides both entirely — if `--only` is provided, config detector settings are ignored

**Config validation:**
- `thresholds.long_test` must be a positive integer. Error if <= 0.
- `detectors.enabled`/`disabled` must contain valid type keys. Warning on stderr for unknown types.
- Unknown top-level keys are ignored (forward compatibility).

**Merge semantics (`merge()`):**
- Scalar values: higher priority wins
- `magic_number_allowlist`: higher priority replaces entirely
- `magic_number_allowlist_extra`: union of both
- `detectors.disabled`: union of both
- `detectors.enabled`: higher priority replaces entirely

The `Configuration` class provides:
- `static fromFile(string $path): self` — parse a `.tq.yaml`
- `static defaults(): self` — built-in defaults
- `merge(self $override): self` — merge two configs (higher priority wins, rules above)
- Getter methods: `getLongTestThreshold(): int`, `getMagicNumberAllowlist(): array`, `getEnabledDetectors(): array|null`, `getDisabledDetectors(): array`, `getBaselinePath(): string`

### 3. Configurable thresholds

**Modify:** `src/TestQualityAnalyzer/Visitor/LongTestVisitor.php`
- Replace `private const LINE_THRESHOLD = 40` with a constructor parameter
- Constructor: `public function __construct(private int $lineThreshold = 40)`
- Use `$this->lineThreshold` instead of `self::LINE_THRESHOLD` (in both the comparison and the message string)

**Modify:** `src/TestQualityAnalyzer/Visitor/MagicNumberTestVisitor.php`
- Rename `TRIVIAL_VALUES` to `DEFAULT_TRIVIAL_VALUES` (public constant, kept as default)
- Add constructor: `public function __construct(private array $trivialValues = self::DEFAULT_TRIVIAL_VALUES)`
- Replace all 4 references to `self::TRIVIAL_VALUES` with `$this->trivialValues`

### 4. Global install support

**Modify:** `composer.json`:
- Add `"bin": ["bin/tq"]`
- Add `symfony/console` and `symfony/yaml` to `require`
- Keep type as `"project"` (changing to `library` has undesirable side effects for Composer scripts)

After this, `composer global require jorj-sh/php-test-quality` makes `tq` available system-wide. The `bin` field is sufficient — Composer symlinks it regardless of package type.

**Modify:** `Dockerfile` — change entrypoint from `bin/analyze` to `bin/tq`

**Modify:** `README.md` — update usage examples from `bin/analyze` to `tq`, add config file documentation with example `.tq.yaml`

### 5. Tests

**Modify:** `tests/CliIntegrationTest.php`
- Update `$this->analyzeScript` to point to `bin/tq`
- All existing CLI test scenarios must continue to pass

**Create:** `tests/ConfigurationTest.php`
- Test loading defaults
- Test loading from YAML file
- Test `magic_number_allowlist` replacement semantics
- Test `magic_number_allowlist_extra` additive semantics
- Test merge precedence (file overrides defaults, CLI overrides file)
- Test validation: negative `long_test` threshold, unknown detector types
- Test invalid YAML handling

**Create:** `tests/Command/AnalyzeCommandTest.php`
- Test Symfony Console integration (using `CommandTester`)
- Test that config file is loaded when present in scan directory
- Test `--config` flag
- Test `--no-config` flag
- Test `--only` overrides config `detectors.enabled`/`disabled`
- Test `--baseline` bare usage defaults to `.tq-baseline.json`
- Test baseline paths resolved relative to scan directory

**Create:** `tests/Command/ListTypesCommandTest.php`
- Test output contains all registered detector type keys (dynamic, not hardcoded count)

**Modify:** `tests/Visitor/LongTestVisitorTest.php`
- Add test for custom threshold via constructor
- Verify message includes the configured threshold value

**Modify:** `tests/Visitor/MagicNumberTestVisitorTest.php`
- Add test for custom allowlist via constructor

## What Stays Unchanged

- All 14 visitors (detection logic untouched, only constructor signatures change for 2 of them)
- `Analyzer`, `AnalysisResult`, `Baseline`, `Issue`, `VisitorInterface`, `AbstractTestVisitor`
- Baseline file format and workflow logic (moved into `AnalyzeCommand` but same behaviour)
- Baseline path resolution: relative paths resolve against the scan directory (not CWD)
- Test fixtures under `var/`

## Verification Criteria

1. `composer install` succeeds with new dependencies
2. `composer test` — all tests pass (existing + new)
3. `composer analyze` — PHPStan passes at level 6
4. `bin/tq var/test-examples/` — produces same output as `bin/analyze` did
5. `bin/tq list-types` — lists all 14 detectors
6. `bin/tq analyze path/ --config=.tq.yaml` — loads config and applies thresholds
7. `bin/tq path/ --baseline` — resolves baseline relative to scan directory (not CWD)
8. `bin/tq path/ --no-config` — ignores any `.tq.yaml` present
9. Config with `long_test: 20` causes a 25-line test to be flagged
10. `bin/analyze` no longer exists
11. Dockerfile entrypoint updated to `bin/tq`
12. README updated with new binary name and config documentation

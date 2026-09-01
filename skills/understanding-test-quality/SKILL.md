---
name: understanding-test-quality
description: Use when analysing PHPUnit test quality, detecting test smells, or running the tq CLI tool. Triggered by mentions of test smells, test quality analysis, PHPUnit code review, assertion roulette, magic numbers in tests, sleepy tests, mystery guest, rotten green tests, or any reference to the tq / php-test-quality tool.
---

# Understanding Test Quality (tq)

## Overview

`tq` is an AST-based test quality analyser for PHPUnit tests. It parses PHP test files, walks the abstract syntax tree, and reports test smells: structural anti-patterns that weaken test suites without necessarily breaking them.

## Installation

```bash
# PHAR (no Composer)
curl -L https://github.com/jorjives/php-test-quality/releases/latest/download/tq.phar -o tq.phar
chmod +x tq.phar

# Docker (no PHP)
docker run --rm -v $(pwd):/code ghcr.io/jorjives/php-test-quality /code/tests/

# Composer (dev dependency)
composer require --dev jorj-sh/php-test-quality
```

## Quick Reference

```bash
tq path/to/tests/                        # Analyse tests (text output)
tq path/to/tests/ --format=json          # JSON output
tq path/to/tests/ --only=no_assertions   # Run specific detectors
tq list-types                            # List all detector types
```

### Baseline Workflow

Baselines let you acknowledge existing issues and only flag new ones:

```bash
# Generate baseline (--reason is required)
tq tests/ --generate-baseline --baseline=.tq-baseline.json --reason="Initial baseline"

# Run with baseline filtering
tq tests/ --baseline=.tq-baseline.json

# Add new issues to existing baseline
tq tests/ --add-to-baseline=.tq-baseline.json --reason="Accepted tech debt"
```

### Configuration (.tq.yaml)

Auto-detected in the scan directory. Override with `--config=path` or skip with `--no-config`.

```yaml
thresholds:
  long_test: 40
  magic_number_allowlist: [0, 1, 200, 404]
  magic_number_allowlist_extra: [42]   # appends to active list

detectors:
  enabled: all
  disabled: [conditional_test_logic]

baseline: .tq-baseline.json
```

## Detectors

| Type slug | What it catches |
|-----------|-----------------|
| `no_assertions` | Test methods with no assertions |
| `assertion_roulette` | Multiple assertions without descriptive messages |
| `empty_test` | Test methods with no executable statements |
| `redundant_assertion` | Tautological assertions like `assertEquals(1, 1)` |
| `rotten_green_test` | Assertions inside conditionals or after return/throw |
| `conditional_test_logic` | `if`/`switch`/ternary in tests |
| `magic_number_test` | Non-trivial numeric literals in assertions |
| `exception_handling` | Try-catch blocks instead of `expectException()` |
| `sleepy_test` | `sleep()`/`usleep()` calls |
| `mystery_guest` | File I/O or database calls in test bodies |
| `redundant_print` | Debug output (`var_dump`, `echo`, etc.) |
| `long_test` | Tests exceeding the line threshold (default 40) |
| `constructor_initialization` | `__construct()` instead of `setUp()` |
| `interface_testing` | Mock-only interface testing |
| `existence_check_assertion` | `assertTrue`/`assertFalse` on `class_exists` etc. |

## JSON Output Shape

```json
{
  "summary": { "files_scanned": 23, "tests_found": 45 },
  "issues": { "no_assertions": 3, "magic_number_test": 7 },
  "details": [
    {
      "file": "UserServiceTest.php",
      "test": "testCreateUser",
      "type": "no_assertions",
      "message": "Test has no assertions"
    }
  ]
}
```

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Treating every issue as a bug | Test smells are signals, not errors. Triage with baselines. |
| Ignoring `assertion_roulette` | Add messages to assertions: `assertEquals($expected, $actual, 'user email should match')` |
| Suppressing `mystery_guest` globally | Prefer data providers or inline fixtures; baseline the exceptions. |
| Running without `--format=json` in CI | JSON is parseable; pipe it to your reporting tool. |

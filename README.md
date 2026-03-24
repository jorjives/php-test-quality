# php-test-quality

AST-based test quality analyser for PHPUnit tests. Detects common test smells by parsing PHP test files and walking the abstract syntax tree.

## Requirements

- PHP >= 8.4

## Installation

**Global (recommended):**

```bash
composer global require jorj-sh/php-test-quality
tq path/to/tests/
```

**Local:**

```bash
git clone https://github.com/jorjives/php-test-quality.git
cd php-test-quality
composer install
bin/tq path/to/tests/
```

## Usage

```bash
# Analyse a test directory
tq path/to/tests/

# JSON output
tq path/to/tests/ --format=json

# Run only specific detectors
tq path/to/tests/ --only=no_assertions,assertion_roulette

# List available detectors
tq list-types

# Generate a baseline (requires --reason)
tq path/to/tests/ --generate-baseline --baseline=.tq-baseline.json --reason="Initial baseline"

# Filter using a baseline
tq path/to/tests/ --baseline=.tq-baseline.json
```

## Configuration

`tq` auto-detects a `.tq.yaml` file in the scan directory. Override with `--config=path/to/.tq.yaml` or skip entirely with `--no-config`. CLI flags always take precedence over config values.

```yaml
thresholds:
  long_test: 40
  magic_number_allowlist:        # replaces default list entirely
    - 0
    - 1
    - 200
    - 404
  magic_number_allowlist_extra:   # appends to active list
    - 42

detectors:
  enabled: all
  disabled:
    - conditional_test_logic

baseline: .tq-baseline.json
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

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

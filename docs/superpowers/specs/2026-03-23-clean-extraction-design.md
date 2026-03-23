# Clean Extraction: php-test-quality

## Goal

Extract the test quality analyser from `services/test-quality-analyzer/` in the basecamp-application repository into a standalone project at `/home/jorjives/dev/tools/php-test-quality/`.

## Decisions

- **Package name:** `jorj-sh/php-test-quality`
- **Licence:** MIT
- **Namespace:** `TestQualityAnalyzer` (unchanged)
- **Git history:** Fresh repo, single initial commit. No attribution to source.
- **Scope:** Clean copy only. No refactoring, no new features, no structural changes.

## What Changes

| Item | From | To |
|---|---|---|
| `composer.json` name | `basecamp/test-quality-analyzer` | `jorj-sh/php-test-quality` |
| `composer.json` licence | `proprietary` | `MIT` |
| `.gitignore` | excludes `/var/test-examples/` | remove that line (test fixtures must be committed) |
| Location | `services/test-quality-analyzer/` | repo root |
| `LICENCE` file | does not exist | add MIT licence file at repo root |

## What Stays Identical

- All PHP source under `src/TestQualityAnalyzer/`
- All tests under `tests/`
- CLI entrypoint `bin/analyze`
- `Dockerfile`, `phpunit.xml`, `phpstan.neon`
- Test fixtures under `var/`
- All 14 visitor implementations
- Core classes: `Analyzer`, `AnalysisResult`, `Baseline`, `Issue`, `VisitorInterface`

## Project Structure

```
php-test-quality/
  bin/analyze
  src/TestQualityAnalyzer/
    Analyzer.php
    AnalysisResult.php
    Baseline.php
    Issue.php
    VisitorInterface.php
    Visitor/
      AssertionCountVisitor.php
      AssertionRouletteVisitor.php
      ConditionalTestLogicVisitor.php
      ConstructorInitializationVisitor.php
      EmptyTestVisitor.php
      ExceptionHandlingVisitor.php
      InterfaceTestingVisitor.php
      LongTestVisitor.php
      MagicNumberTestVisitor.php
      MysteryGuestVisitor.php
      RedundantAssertionVisitor.php
      RedundantPrintVisitor.php
      RottenGreenTestVisitor.php
      SleepyTestVisitor.php
  tests/
    AnalysisResultTest.php
    AnalyzerTest.php
    BaselineTest.php
    CliIntegrationTest.php
    IssueTest.php
    Visitor/
      (14 visitor test files)
  var/
    test-baseline.json
    test-examples/ExampleTest.php
  composer.json
  Dockerfile
  LICENCE
  phpunit.xml
  phpstan.neon
  .gitignore
```

## Verification Criteria

1. `composer install` succeeds
2. `composer test` (PHPUnit) passes — all tests green
3. `composer analyze` (PHPStan) passes — no errors
4. `bin/analyze var/test-examples/` exits with code 1 and reports at least `no_assertions` on `testNoAssertions`
5. `basecamp-application/` subdirectory removed from `/home/jorjives/dev/tools/php-test-quality/`

## Future Phases (out of scope)

- **Phase B:** Tidy-ups (extract shared `isTestMethod()`, address commented-out visitor, align PHPStan level in neon vs composer script, README, etc.)
- **Phase C:** Modernise (Symfony Console CLI, configurable thresholds, global Composer install)

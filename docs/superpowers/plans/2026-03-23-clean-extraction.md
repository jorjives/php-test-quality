# Clean Extraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the test quality analyser from basecamp-application into a standalone project at `/home/jorjives/dev/tools/php-test-quality/`.

**Architecture:** Copy all source, test, and config files from `basecamp-application/services/test-quality-analyzer/` to the repo root. Update `composer.json` (name, licence), fix `.gitignore`, add `LICENCE` file. Initialise fresh git repo.

**Tech Stack:** PHP 8.4, nikic/php-parser 5.x, PHPUnit 11, PHPStan 2

**Spec:** `docs/superpowers/specs/2026-03-23-clean-extraction-design.md`

**Source directory:** `basecamp-application/services/test-quality-analyzer/`

---

## File Structure

All files are copied from source to repo root. The following files are modified or created:

| Action | File | Purpose |
|--------|------|---------|
| Modify | `composer.json` | Rename package, change licence |
| Modify | `.gitignore` | Remove `/var/test-examples/` exclusion |
| Create | `LICENCE` | MIT licence text |
| Copy | `bin/analyze` | CLI entrypoint |
| Copy | `src/TestQualityAnalyzer/**/*.php` | All source (5 core + 14 visitors) |
| Copy | `tests/**/*.php` | All tests (5 core + 14 visitor tests) |
| Copy | `var/test-baseline.json` | Test fixture |
| Copy | `var/test-examples/ExampleTest.php` | Test fixture |
| Copy | `Dockerfile` | Container build |
| Copy | `phpunit.xml` | Test config |
| Copy | `phpstan.neon` | Static analysis config |

---

### Task 1: Initialise the repository

**Files:**
- Create: `.git/` (via `git init`)

- [ ] **Step 1: Initialise git repo**

```bash
cd /home/jorjives/dev/tools/php-test-quality
git init
```

Expected: `Initialized empty Git repository`

---

### Task 2: Copy source files

**Files:**
- Copy: `bin/`, `src/`, `tests/`, `var/`, `Dockerfile`, `phpunit.xml`, `phpstan.neon`, `.gitignore`, `composer.json`

- [ ] **Step 1: Copy all files from source to repo root**

```bash
SRC=/home/jorjives/dev/tools/php-test-quality/basecamp-application/services/test-quality-analyzer
DEST=/home/jorjives/dev/tools/php-test-quality

cp -r "$SRC"/bin "$DEST"/
cp -r "$SRC"/src "$DEST"/
cp -r "$SRC"/tests "$DEST"/
cp -r "$SRC"/var "$DEST"/
cp "$SRC"/Dockerfile "$DEST"/
cp "$SRC"/phpunit.xml "$DEST"/
cp "$SRC"/phpstan.neon "$DEST"/
cp "$SRC"/.gitignore "$DEST"/
cp "$SRC"/composer.json "$DEST"/
```

- [ ] **Step 2: Verify file structure**

```bash
find /home/jorjives/dev/tools/php-test-quality -not -path '*/basecamp-application/*' -not -path '*/.git/*' -not -path '*/docs/*' -type f | sort
```

Expected: all source files present at repo root — `bin/analyze`, `src/TestQualityAnalyzer/*.php`, `src/TestQualityAnalyzer/Visitor/*.php`, `tests/*.php`, `tests/Visitor/*.php`, `var/test-baseline.json`, `var/test-examples/ExampleTest.php`, plus config files.

---

### Task 3: Update composer.json

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Change package name**

In `composer.json`, change:
```json
"name": "basecamp/test-quality-analyzer"
```
to:
```json
"name": "jorj-sh/php-test-quality"
```

- [ ] **Step 2: Change licence**

In `composer.json`, change:
```json
"license": "proprietary"
```
to:
```json
"license": "MIT"
```

- [ ] **Step 3: Verify composer.json changes**

```bash
cd /home/jorjives/dev/tools/php-test-quality
grep '"name": "jorj-sh/php-test-quality"' composer.json && echo "Name OK"
grep '"license": "MIT"' composer.json && echo "Licence OK"
cat composer.json | python3 -m json.tool > /dev/null && echo "Valid JSON"
```

Expected: `Name OK`, `Licence OK`, `Valid JSON`

---

### Task 4: Fix .gitignore

**Files:**
- Modify: `.gitignore`

- [ ] **Step 1: Remove the test-examples exclusion**

Remove the line `/var/test-examples/` from `.gitignore`. The file should contain:

```
/vendor/
/composer.lock
/.phpunit.cache/
/.phpstan/
/var/cache/
```

- [ ] **Step 2: Verify .gitignore**

```bash
cd /home/jorjives/dev/tools/php-test-quality
grep -c 'test-examples' .gitignore
```

Expected: `0` (line removed)

---

### Task 5: Add LICENCE file

**Files:**
- Create: `LICENCE`

- [ ] **Step 1: Create MIT LICENCE file**

Create `LICENCE` at repo root with the standard MIT licence text. Copyright holder: the current year and author name from git config.

---

### Task 6: Install dependencies and verify

**Files:**
- Generated: `vendor/`, `composer.lock`

- [ ] **Step 1: Run composer install**

```bash
cd /home/jorjives/dev/tools/php-test-quality
composer install
```

Expected: installs `nikic/php-parser`, `phpunit/phpunit`, `phpstan/phpstan` and their dependencies without errors.

- [ ] **Step 2: Run tests**

```bash
cd /home/jorjives/dev/tools/php-test-quality
composer test
```

Expected: all tests pass (green).

- [ ] **Step 3: Run static analysis**

```bash
cd /home/jorjives/dev/tools/php-test-quality
composer analyze
```

Expected: no errors.

- [ ] **Step 4: Run CLI on test fixtures**

```bash
cd /home/jorjives/dev/tools/php-test-quality
php bin/analyze var/test-examples/
echo "Exit code: $?"
```

Expected: exits with code 1, output includes `no_assertions` issue for `testNoAssertions`.

---

### Task 7: Clean up and commit

**Files:**
- Remove: `basecamp-application/` directory

- [ ] **Step 1: Remove the cloned source repo**

```bash
rm -rf /home/jorjives/dev/tools/php-test-quality/basecamp-application
```

- [ ] **Step 2: Stage all files**

```bash
cd /home/jorjives/dev/tools/php-test-quality
git add -A
```

- [ ] **Step 3: Review staged files**

```bash
git status
```

Expected: all project files staged, no `basecamp-application/` artefacts.

- [ ] **Step 4: Commit**

```bash
git commit -m "Initial commit: standalone test quality analyser"
```

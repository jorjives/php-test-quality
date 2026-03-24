# Phase D: PHAR + Docker Distribution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and distribute the tool as a PHAR archive and Docker image via GitHub Actions, triggered automatically on release tags.

**Architecture:** Add `box.json` for PHAR compilation, CI workflow for test + build on every push/PR, release workflow for tag-triggered PHAR + Docker distribution. Track `composer.lock` for reproducible builds. Inject version from git tag via box replacements.

**Tech Stack:** humbug/box (PHAR compiler), GitHub Actions, ghcr.io (Docker registry), softprops/action-gh-release

**Spec:** `docs/superpowers/specs/2026-03-24-phase-d-distribution-design.md`

**Important:** PHP is not available on the host. Use Docker for all PHP commands:
- Tests: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit`
- PHPStan: `docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpstan analyse --memory-limit=512M`

---

## File Structure

| Action | File | Purpose |
|--------|------|---------|
| Create | `box.json` | PHAR compiler configuration |
| Create | `.github/workflows/ci.yml` | CI: test + PHAR build on push/PR |
| Create | `.github/workflows/release.yml` | Release: PHAR + Docker on tag push |
| Create | `.dockerignore` | Exclude unnecessary files from Docker image |
| Modify | `bin/tq` | Version injection via box replacement |
| Modify | `composer.json` | Add humbug/box dev dep, build-phar script |
| Modify | `.gitignore` | Add `/*.phar`, remove `/composer.lock` |
| Modify | `Dockerfile` | Add OCI labels |
| Modify | `README.md` | Add PHAR and Docker distribution docs |
| Track | `composer.lock` | Commit for reproducible builds |

---

### Task 1: Track composer.lock and update .gitignore

**Files:**
- Modify: `.gitignore`
- Track: `composer.lock`

- [ ] **Step 1: Update .gitignore**

In `.gitignore`:
1. Remove the line `/composer.lock`
2. Add `/*.phar` at the end

The file should become:
```
/vendor/
/.phpunit.cache/
/.phpstan/
/var/cache/
/*.phar
```

- [ ] **Step 2: Stage and commit composer.lock**

```bash
git add .gitignore composer.lock
git commit -m "chore: track composer.lock for reproducible builds, ignore .phar files"
```

---

### Task 2: Add box dependency and create box.json

**Files:**
- Modify: `composer.json`
- Create: `box.json`

- [ ] **Step 1: Add humbug/box to require-dev**

```bash
docker run --rm -v "$(pwd)":/app -w /app composer:2 require --dev humbug/box --ignore-platform-req=php
```

Note: `--ignore-platform-req=php` is needed because the `composer:2` image may bundle PHP < 8.4, but the project requires `^8.4`.

- [ ] **Step 2: Add build-phar script to composer.json**

In `composer.json`, add to the `scripts` section:
```json
"build-phar": "box compile"
```

- [ ] **Step 3: Create box.json**

Create `box.json` at the repo root with this content:

```json
{
    "directories": [
        "src",
        "vendor"
    ],
    "files": [
        "bin/tq"
    ],
    "main": "bin/tq",
    "output": "tq.phar",
    "stub": true,
    "compactors": [
        "KevinGH\\Box\\Compactor\\Php"
    ],
    "compression": "GZ",
    "chmod": "0755",
    "exclude": [
        "tests",
        "Tests",
        "test",
        "Test",
        "docs",
        "doc"
    ],
    "replacements": {
        "git_tag": "git-version"
    }
}
```

- [ ] **Step 4: Run tests to verify nothing broke**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all 293 tests pass.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock box.json
git commit -m "feat: add box PHAR compiler configuration"
```

---

### Task 3: Add version injection to bin/tq

**Files:**
- Modify: `bin/tq`

- [ ] **Step 1: Update bin/tq with version replacement**

In `bin/tq`, replace:
```php
$app = new Application('tq', '1.0.0');
```
with:
```php
$version = '@git_tag@';
if (str_contains($version, '@')) {
    $version = 'dev';
}
$app = new Application('tq', $version);
```

When box compiles the PHAR, `@git_tag@` is replaced with the git tag (e.g. `1.0.0`). When running from source, the literal `@git_tag@` remains and the fallback to `'dev'` kicks in.

- [ ] **Step 2: Verify it still works**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php bin/tq --version
```

Expected: output shows `tq dev` (since we're running from source, not a PHAR).

- [ ] **Step 3: Run tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add bin/tq
git commit -m "feat: inject version from git tag via box replacements"
```

---

### Task 4: Build and verify PHAR locally

This task verifies the PHAR compiles and works before setting up CI.

**Files:** None created — this is a verification step.

- [ ] **Step 1: Build the PHAR**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine sh -c "php -d phar.readonly=0 vendor/bin/box compile"
```

Expected: output shows `Building the PHAR...` and `tq.phar` is created.

- [ ] **Step 2: Smoke test — list-types**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php tq.phar list-types
```

Expected: lists all 14 detectors.

- [ ] **Step 3: Smoke test — analyze**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine sh -c "php tq.phar var/test-examples/; echo \"Exit: \$?\""
```

Expected: reports issues, exit code 1.

- [ ] **Step 4: Smoke test — version**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine php tq.phar --version
```

Expected: shows `tq dev` (no git tag in this context).

- [ ] **Step 5: Clean up the PHAR (it's gitignored)**

```bash
rm tq.phar
```

---

### Task 5: Create CI workflow

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Create CI workflow file**

Create `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          tools: composer:v2

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Run tests
        run: composer test

      - name: Run static analysis
        run: composer analyze

  phar:
    runs-on: ubuntu-latest
    needs: test
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          ini-values: phar.readonly=0
          tools: composer:v2

      - name: Download box
        run: |
          curl -L https://github.com/box-project/box/releases/latest/download/box.phar -o box.phar
          chmod +x box.phar

      - name: Install production dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction

      - name: Build PHAR
        run: php box.phar compile

      - name: Smoke test
        run: |
          php tq.phar list-types
          php tq.phar --version
          php tq.phar var/test-examples/; test $? -eq 1
```

- [ ] **Step 2: Commit**

```bash
mkdir -p .github/workflows
git add .github/workflows/ci.yml
git commit -m "ci: add CI workflow with test and PHAR build jobs"
```

---

### Task 6: Create release workflow

**Files:**
- Create: `.github/workflows/release.yml`

- [ ] **Step 1: Create release workflow file**

Create `.github/workflows/release.yml`:

```yaml
name: Release

on:
  push:
    tags: ['v*']

permissions:
  contents: write
  packages: write

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          ini-values: phar.readonly=0
          tools: composer:v2

      - name: Download box
        run: |
          curl -L https://github.com/box-project/box/releases/latest/download/box.phar -o box.phar
          chmod +x box.phar

      - name: Install production dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction

      - name: Build PHAR
        run: php box.phar compile

      - name: Smoke test PHAR
        run: |
          php tq.phar list-types
          php tq.phar --version
          php tq.phar var/test-examples/; test $? -eq 1

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract version from tag
        id: version
        run: echo "version=${GITHUB_REF_NAME#v}" >> "$GITHUB_OUTPUT"

      - name: Build and push Docker image
        run: |
          docker build \
            -t ghcr.io/jorjives/php-test-quality:${{ steps.version.outputs.version }} \
            -t ghcr.io/jorjives/php-test-quality:latest \
            .
          docker push ghcr.io/jorjives/php-test-quality:${{ steps.version.outputs.version }}
          docker push ghcr.io/jorjives/php-test-quality:latest

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          files: tq.phar
          generate_release_notes: true
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/release.yml
git commit -m "ci: add release workflow for PHAR and Docker distribution"
```

---

### Task 7: Add Dockerfile labels and .dockerignore

**Files:**
- Modify: `Dockerfile`
- Create: `.dockerignore`

- [ ] **Step 1: Add OCI labels to Dockerfile**

Add these lines before the `ENTRYPOINT` line in `Dockerfile`:

```dockerfile
LABEL org.opencontainers.image.source="https://github.com/jorjives/php-test-quality"
LABEL org.opencontainers.image.description="AST-based test quality analyser for PHPUnit tests"
LABEL org.opencontainers.image.licenses="MIT"
```

- [ ] **Step 2: Create .dockerignore**

Create `.dockerignore` at the repo root:

```
.git
.github
tests
docs
var
vendor
*.phar
.phpunit.cache
.phpstan
README.md
LICENCE
box.json
phpunit.xml
phpstan.neon
```

- [ ] **Step 3: Verify Docker build still works**

```bash
docker build -t tq-test .
docker run --rm -v "$(pwd)":/code tq-test /code/var/test-examples/
```

Expected: reports issues.

- [ ] **Step 4: Commit**

```bash
git add Dockerfile .dockerignore
git commit -m "chore: add Dockerfile labels and .dockerignore"
```

---

### Task 8: Update README with distribution docs

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update README**

Update the Installation section to add PHAR and Docker options before the existing methods. The Installation section should become:

**PHAR (no Composer needed):**
```bash
curl -L https://github.com/jorjives/php-test-quality/releases/latest/download/tq.phar -o tq.phar
chmod +x tq.phar
php tq.phar path/to/tests/
```

**Docker (no PHP needed):**
```bash
docker run --rm -v $(pwd):/code ghcr.io/jorjives/php-test-quality /code/tests/
```

**Composer global:**
```bash
composer global require jorj-sh/php-test-quality
tq path/to/tests/
```

**Composer dev dependency:**
```bash
composer require --dev jorj-sh/php-test-quality
vendor/bin/tq path/to/tests/
```

Also update the Docker section at the bottom to reference the published image:
```bash
# Use the published image
docker run --rm -v $(pwd):/code ghcr.io/jorjives/php-test-quality /code/tests/

# Or build locally
docker build -t php-test-quality .
docker run --rm -v /path/to/tests:/tests php-test-quality /tests
```

- [ ] **Step 2: Run tests**

```bash
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli-alpine vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: add PHAR and Docker distribution installation options"
```

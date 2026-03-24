# Phase D: PHAR + Docker Distribution Design

## Goal

Distribute the tool as a self-contained PHAR archive and a Docker image, both built automatically by GitHub Actions on release tags.

## Changes

### 1. PHAR build configuration

**Create:** `box.json` — box-project configuration at repo root:

```json
{
    "directories": ["src", "vendor"],
    "files": ["bin/tq"],
    "main": "bin/tq",
    "output": "tq.phar",
    "stub": true,
    "compactors": ["KevinGH\\Box\\Compactor\\Php"],
    "compression": "GZ",
    "chmod": "0755",
    "exclude": [
        "tests",
        "Tests",
        "test",
        "Test",
        "docs",
        "doc"
    ]
}
```

Key settings:
- Explicitly includes `src/` and `vendor/` directories
- Excludes test and doc directories from vendor packages (reduces PHAR size)
- GZ compression for smaller file size
- Executable stub (`#!/usr/bin/env php`)

**Modify:** `composer.json`:
- Add `humbug/box` to `require-dev`
- Add script: `"build-phar": "box compile"` (for local dev use only — CI downloads box separately)

**Modify:** `.gitignore`:
- Add `/*.phar` to ignore built PHAR files
- Remove `/composer.lock` — lock file must be tracked for reproducible builds

**Track:** `composer.lock` — commit the existing lock file. This is standard practice for tools/applications (ensures reproducible PHAR builds across different CI runs).

### 2. GitHub Actions CI workflow

**Create:** `.github/workflows/ci.yml` — runs on push and PR to main:

```yaml
name: CI
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
```

**Job 1: test** (PHP 8.4)
1. Checkout
2. Setup PHP 8.4
3. `composer install`
4. `composer test`
5. `composer analyze`

**Job 2: phar** (depends on test)
1. Checkout
2. Setup PHP 8.4 with `phar.readonly=false`
3. Download `box.phar` from box-project releases (not from vendor — avoids needing dev deps)
4. `composer install --no-dev --optimize-autoloader`
5. `php box.phar compile`
6. Smoke test: `php tq.phar list-types` and `php tq.phar --version`

### 3. GitHub Actions release workflow

**Create:** `.github/workflows/release.yml` — triggers on tag push (`v*`):

```yaml
name: Release
on:
  push:
    tags: ['v*']

permissions:
  contents: write   # create GitHub Release + attach assets
  packages: write   # push Docker image to GHCR
```

Steps:
1. Checkout code
2. Setup PHP 8.4 with `phar.readonly=false`
3. Download `box.phar` from box-project releases
4. `composer install --no-dev --optimize-autoloader`
5. `php box.phar compile`
6. Smoke test: `php tq.phar list-types`
7. Login to GHCR: `docker/login-action@v3` with `registry: ghcr.io`, `username: ${{ github.actor }}`, `password: ${{ secrets.GITHUB_TOKEN }}`
8. Build Docker image: `ghcr.io/jorjives/php-test-quality` tagged with version extracted from tag + `latest`
9. Push Docker image to GHCR
10. Create GitHub Release using `softprops/action-gh-release@v2` with `files: tq.phar` and `generate_release_notes: true`

### 4. Version from git tag

**Modify:** `bin/tq` — replace hardcoded `'1.0.0'` with a version that can be injected at build time.

Approach: Use box's `replacement` feature. In `box.json`, add:
```json
"replacements": {
    "git_tag": "git-version"
}
```

In `bin/tq`, change:
```php
$app = new Application('tq', '1.0.0');
```
to:
```php
$app = new Application('tq', '@git_tag@');
```

Box replaces `@git_tag@` with the current git tag during compilation. When running from source (not PHAR), `@git_tag@` remains as a literal string — this is acceptable for dev use. Alternatively, fall back to `'dev'`:
```php
$version = '@git_tag@';
if (str_contains($version, '@')) {
    $version = 'dev';
}
$app = new Application('tq', $version);
```

### 5. Dockerfile labels

**Modify:** `Dockerfile` — add OCI labels for the registry:

```dockerfile
LABEL org.opencontainers.image.source="https://github.com/jorjives/php-test-quality"
LABEL org.opencontainers.image.description="AST-based test quality analyser for PHPUnit tests"
LABEL org.opencontainers.image.licenses="MIT"
```

### 6. .dockerignore

**Create:** `.dockerignore` — prevent unnecessary files from entering the Docker image:

```
.git
.github
tests
docs
var
*.phar
.phpunit.cache
.phpstan
README.md
LICENCE
box.json
phpunit.xml
phpstan.neon
```

### 7. README update

Add distribution options to `README.md`:

**PHAR:**
```bash
curl -L https://github.com/jorjives/php-test-quality/releases/latest/download/tq.phar -o tq.phar
chmod +x tq.phar
php tq.phar path/to/tests/
```

**Docker:**
```bash
docker run --rm -v $(pwd):/code ghcr.io/jorjives/php-test-quality /code/tests/
```

Existing methods stay (Composer global, Composer dev, git clone).

## What Stays Unchanged

- All source code, tests, commands, visitors, configuration
- Existing Composer installation methods
- `bin/tq` entrypoint (used by both direct execution and as PHAR main)

## Verification Criteria

1. `composer build-phar` produces `tq.phar` locally (dev environment with box installed)
2. `php tq.phar list-types` lists all 14 detectors
3. `php tq.phar var/test-examples/` reports issues (exit code 1)
4. `php tq.phar --version` shows the git tag (or `dev` when run from source)
5. `docker build -t tq-test .` succeeds
6. `docker run --rm -v $(pwd):/code tq-test /code/var/test-examples/` reports issues
7. CI workflow passes on push to main (tests + PHAR build)
8. Release workflow triggers on `v*` tag — creates GitHub Release with `tq.phar` + pushes Docker image to GHCR
9. All existing tests continue to pass
10. `composer.lock` is tracked in git

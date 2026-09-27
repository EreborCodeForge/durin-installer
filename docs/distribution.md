# Distribution

## Install

```bash
composer global require ereborcodeforge/durin-installer
```

Ensure Composer's global `bin-dir` is on `PATH`.

## Packagist

Package: `ereborcodeforge/durin-installer`  
Repository: https://github.com/EreborCodeForge/durin-installer

No custom Composer repositories are required or injected.

## Distribution smoke

After `v0.1.0` is on Packagist:

```bash
export COMPOSER_HOME="<temporary-composer-home>"

composer global require \
  ereborcodeforge/durin-installer:^0.1 \
  --no-interaction \
  --prefer-dist

GLOBAL_BIN="$(composer global config bin-dir --absolute)"

"$GLOBAL_BIN/durin" new smoke-app

cd smoke-app

vendor/bin/durin doctor
vendor/bin/durin optimize
```

Assertions:

- installer resolved from Packagist
- global `durin` binary exists
- `composer.json` name is `app/smoke-app`
- `durin.yaml` `application.name` is `smoke-app`
- `APP_NAME=smoke-app`
- `modules: false` preserved
- Doctor and optimize exit `0`

## Pre-release vs post-release

| Stage | Proof |
|-------|-------|
| Before tag | Repository CI, unit tests, fake process integration, CLI help/version |
| After Packagist sync | Real `composer global require` + `durin new` + Doctor + optimize |

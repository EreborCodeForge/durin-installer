# Distribution

## Install

```bash
composer global require ereborcodeforge/durin-installer
```

Ensure Composer's global `bin-dir` is on `PATH`. Do not expect this package to edit `PATH`.

After installation, `durin` and `durins-forge` are different executables in that bin directory. `durins-forge` only launches the CLI that belongs to the current project.

## Packagist

Package: `ereborcodeforge/durin-installer`  
Repository: https://github.com/EreborCodeForge/durin-installer

No custom Composer repositories are required or injected.

## Distribution smoke

After `v0.3.0` is on Packagist:

```bash
export COMPOSER_HOME="<temporary-composer-home>"

composer global require \
  ereborcodeforge/durin-installer:^0.3 \
  --no-interaction \
  --prefer-dist

GLOBAL_BIN="$(composer global config bin-dir --absolute)"

"$GLOBAL_BIN/durin" new smoke-app --preset=minimal

cd smoke-app

"$GLOBAL_BIN/durins-forge" doctor
vendor/bin/durins-forge optimize
```

Assertions:

- installer resolved from Packagist
- global `durin` and `durins-forge` binaries exist and are not the same file
- `composer.json` name is `app/smoke-app`
- `durin.yaml` `application.name` is `smoke-app`
- `APP_NAME=smoke-app`
- `config/app.php` resolves name to `smoke-app`
- `modules: false` preserved
- Doctor and optimize exit `0`

## Publication order

ECO-10 stays unpublished until Packagist has the corresponding versions. Publish in dependency order:

1. `ereborcodeforge/durin-core`
2. `ereborcodeforge/durin-presets` — scaffold text and preset behavior consumed by Forge and the Installer
3. `ereborcodeforge/durin-architecture`
4. `ereborcodeforge/durins-forge` — canonical `durins-forge` bin and consumer context isolation
5. `ereborcodeforge/durin-app`
6. `ereborcodeforge/durin-installer` — distinct global `durin` and `durins-forge`

A lock that still resolves the older Packagist versions is not evidence that this release is published. The published gate remains blocked until those versions exist.

## Pre-release vs post-release

| Stage | Proof |
|-------|-------|
| Before tag | Repository CI, unit tests, fake process integration, CLI help/version |
| After Packagist sync | Real `composer global require` + `durin new` + Doctor + optimize |

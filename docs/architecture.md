# Architecture

`durin-installer` is orchestration only.

```text
durin-installer
      ↓ create-project (Packagist)
durin-app
      ↓
durins-forge
      ├── durin-core
      ├── durin-presets
      ├── durin-architecture
      └── mazarbul
      └── mithrilphp
```

## Ownership

| Package | Owns |
|---------|------|
| `durin-installer` | Global `durin new` UX, Composer create-project, identity customization |
| `durin-app` | Canonical application artifact / skeleton |
| `durins-forge` | Project/framework CLI (`vendor/bin/durin`) |
| `durin-presets` | Preset definitions |
| `durin-core` | Safe mutation primitives |

The installer does **not** depend on Durin internals. It never vendors `durin-app`. Creation resolves entirely through Packagist.

## V1 flow

```text
validate environment
      ↓
validate target
      ↓
resolve Composer
      ↓
composer create-project ereborcodeforge/durin-app:^0.1.2
      ↓
customize project identity
      ↓
create .env
      ↓
vendor/bin/durin doctor
      ↓
print next steps
```

## Public contract

For `0.1.x`:

- CLI = public
- PHP classes = internal

Consumers should not build against installer PHP classes.

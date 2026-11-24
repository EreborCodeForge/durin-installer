# durin-installer

Global project installer for the Durin ecosystem.

```bash
composer global require ereborcodeforge/durin-installer

durin new billing-api --preset=minimal

cd billing-api

durins-forge doctor
durins-forge dev
```

## Global vs project CLI

| Context | Binary | Role |
|---------|--------|------|
| Global | `durin` | Creates new applications and lists presets |
| Global launcher | `durins-forge` | Runs the Forge CLI installed in the current project, or `bin/durins-forge` in a Forge checkout |
| Project / CI | `vendor/bin/durins-forge` | Same local CLI when the global launcher is not on `PATH` |
| Compatibility | `vendor/bin/durin` | Legacy alias of the local CLI. Not recommended |

`durin` does not dispatch project commands. `durins-forge` does not install a global copy of the framework. Both global binaries come from this package and need Composer's global `bin-dir` on `PATH`. This package does not change `PATH`.

If a shell lists `vendor/bin` before the global Composer `bin-dir`, the legacy `durin` alias can shadow the global `durin`. The name contract assumes the recommended `PATH` order.

## Fallback without the installer

```bash
composer create-project ereborcodeforge/durin-app:^0.4 billing-api
```

## What this package is

- A Composer `library` with global `durin` and `durins-forge` binaries
- Orchestration around `composer create-project ereborcodeforge/durin-app`
- A thin launcher that executes the local `durins-forge` entrypoint
- Safe project identity customization (`composer.json`, `durin.yaml`, `.env`)

## What this package is not

- A framework runtime
- A preset or scaffolding engine
- A proxy that reimplements Forge commands
- A global install of the framework

## Documentation

- [Architecture](docs/architecture.md)
- [CLI](docs/cli.md)
- [Distribution](docs/distribution.md)
- [ADR-0001 — Installer role](docs/adr/ADR-0001-installer-role.md)

## License

MIT

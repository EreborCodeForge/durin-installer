# durin-installer

Global project installer for the Durin ecosystem.

```bash
composer global require ereborcodeforge/durin-installer

durin new billing-api

cd billing-api

vendor/bin/durin doctor
vendor/bin/durin dev
```

## Global vs project CLI

| Context | Binary | Role |
|---------|--------|------|
| Global | `durin` | Creates new applications |
| Project | `vendor/bin/durin` | Framework/project DX (doctor, dev, optimize, …) |

V1 of the installer owns only `new`, `--help`, and `--version`. It does **not** proxy project commands.

## Fallback without the installer

```bash
composer create-project ereborcodeforge/durin-app:^0.1.2 billing-api
```

## What this package is

- A Composer `library` with a global `durin` binary
- Orchestration around `composer create-project ereborcodeforge/durin-app`
- Safe project identity customization (`composer.json`, `durin.yaml`, `.env`)

## What this package is not

- A framework runtime
- A preset or scaffolding engine
- A proxy for `vendor/bin/durin` commands

## Documentation

- [Architecture](docs/architecture.md)
- [CLI](docs/cli.md)
- [Distribution](docs/distribution.md)
- [ADR-0001 — Installer role](docs/adr/ADR-0001-installer-role.md)

## License

MIT

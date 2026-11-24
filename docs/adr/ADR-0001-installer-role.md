# ADR-0001 — Installer role

## Status

Accepted

## Context

Durin already has `durin-app` as the canonical application skeleton and Forge as the project CLI. Developers need a short global UX:

```bash
durin new billing
```

without duplicating framework, preset, or skeleton logic.

## Decision

1. Ship `ereborcodeforge/durin-installer` as a separate Composer package and GitHub repository.
2. Package type is `library`.
3. Global binaries are `durin` and the `durins-forge` launcher. The launcher does not install or vendor the framework.
4. `durin` owns `new`, `presets`, help, and version. It does not dispatch Forge commands.
5. Applications are created via Packagist (`ereborcodeforge/durin-app:^0.4`). Init runs only when `--preset` is passed.
6. The installer does not depend directly on `durin-core`, `durin-architecture`, or `durin-app`. ECO-08 adds a read-only dependency on `durin-presets` so preset IDs are not copied. Runtime rules stay in Forge.
7. V1 creates the minimal application only.
8. The installer does not reimplement Forge commands. `durins-forge` only resolves and executes the local entrypoint.
9. Partial create-project failures are reported without automatic recursive cleanup.

## Consequences

- Global `durin` creates projects. Global `durins-forge` runs the local Forge CLI.
- `vendor/bin/durin` remains a compatibility alias. If `vendor/bin` precedes Composer's global `bin-dir` on `PATH`, that alias can shadow global `durin`. The contract assumes the recommended `PATH` and does not change it.
- Preset-aware creation waits for a Forge-owned in-place initialization contract.
- Installer PHP classes remain internal through `0.1.x`.

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
3. Canonical global binary is `durin` only.
4. V1 owns only `new`, help, and version.
5. Applications are created via Packagist (`ereborcodeforge/durin-app:^0.1.2`).
6. The installer does not depend on `durin-core`, `durin-presets`, or `durin-architecture`.
7. V1 creates the minimal application only.
8. V1 does not proxy arbitrary Forge/project commands.
9. Partial create-project failures are reported without automatic recursive cleanup.

## Consequences

- Global `durin` and project `vendor/bin/durin` coexist intentionally.
- Preset-aware creation waits for a Forge-owned in-place initialization contract.
- Installer PHP classes remain internal through `0.1.x`.

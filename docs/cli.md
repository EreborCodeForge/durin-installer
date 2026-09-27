# CLI

## Commands

```bash
durin new <project>
durin --help
durin --version
durin help
```

Optional no-op flag (V1 has no interactive prompts):

```bash
durin new <project> --no-interaction
```

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | Success |
| `1` | Generic creation/runtime failure |
| `2` | Invalid CLI usage |
| `3` | Environment dependency missing (e.g. Composer) |
| `4` | Target path conflict |
| `5` | Composer create-project failure |
| `6` | Post-create validation failure (including Doctor) |

## Examples

```bash
durin new billing-api
durin new ./apps/payments
durin new /home/dev/projects/orders
```

The application slug is derived from the target directory basename.

During `durin new`, the installer prints stage status and streams Composer/Doctor output live so long installs do not look frozen.

Eregion is intentionally **not** installed by `create-project` / `durin new`. Doctor warnings about a missing Eregion binary are expected until you opt in with:

```bash
cd <project>
vendor/bin/forge server:install
```

## Debug

```bash
DURIN_INSTALLER_DEBUG=1 durin new billing-api
```

Prints resolved Composer path, target path, argv, and child exit context. Does not dump secrets or the full environment.

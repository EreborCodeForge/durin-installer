# CLI

## Commands

```bash
durin new <project> [--preset=minimal|service|worker]
durin presets [--format=text|json]
durin --help
durin --version
durin help
```

`durin` only creates projects and lists presets. Framework commands use the global launcher, which executes the Forge version installed in the current project:

```bash
cd <project>
durins-forge doctor
durins-forge dev
```

In CI or a container, call the local binary directly:

```bash
vendor/bin/durins-forge doctor
```

Inside a `durins-forge` checkout the launcher runs `php`-equivalent `bin/durins-forge`. `vendor/bin/durin` remains a compatibility alias. If `vendor/bin` is earlier on `PATH` than Composer's global `bin-dir`, that alias can shadow global `durin`. This package does not change `PATH`.

On Windows, Composer publishes `durins-forge.bat`. The launcher itself executes the PHP script with the current CLI interpreter and does not join a new process group. Ctrl+C in a Windows console is not injected by the launcher; a child still running when the launcher exits is terminated from the shutdown handler. Unix forwards SIGINT and SIGTERM to the child.

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

<!-- Context: operations/navigation.md | Priority: high | Version: 1.0 | Updated: 2026-10-16 -->

# Operations

How this checkout is provisioned, served, stored and reset on this machine.

## Files

| File | Topic |
| ---- | ----- |
| [environment-and-deployment.md](environment-and-deployment.md) | Branch model, Docker container and named volume, port, dev server, secrets, NTFS mount |
| [provisioning.md](provisioning.md) | `scripts/provision-dev.sh` flags and guards, `scripts/create-admin.php`, second install, `APP_KEY` rotation |
| [database.md](database.md) | Live vs test database, migration locations, `db-reset` guards, backup/restore reality |

## Related

- Canonical detail: `scripts/README.md`
- Command surface: `Makefile` (`make`)
- Config: `.env` (gitignored), `.env.example`

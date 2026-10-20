<!-- Context: operations/navigation.md | Priority: high | Version: 1.0 | Updated: 2026-10-20 -->

# Operations

How this checkout is provisioned, served, stored and reset on this machine.

## Files

| File | Topic |
| ---- | ----- |
| [environment-and-deployment.md](environment-and-deployment.md) | Branch model, Docker container and named volume, port, dev server, secrets, NTFS mount |
| [deployment-homelab.md](deployment-homelab.md) | The live demo deployment on the homelab: three containers, loopback port 3102, tunnel route, boot sequence and its idempotency marker, memory budget, open risks |
| [provisioning.md](provisioning.md) | `scripts/provision-dev.sh` flags and guards, `scripts/create-admin.php`, second install, `APP_KEY` rotation |
| [database.md](database.md) | Live vs test database, migration locations, `db-reset` guards, backup/restore reality |
| [brand.md](brand.md) | Product name and Arabic form, domain, approved tagline and its placement, palette roles, logo asset paths, Arabic font and licence |

## Related

- Canonical detail: `scripts/README.md`
- Deployment runbook: `deploy/README.md`
- Command surface: `Makefile` (`make`)
- Config: `.env` (gitignored), `.env.example`
- Brand assets and palette detail: `brand/`

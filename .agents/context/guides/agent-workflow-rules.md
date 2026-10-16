<!-- Context: guides/agent-workflow-rules.md | Priority: critical | Version: 1.0 | Updated: 2026-10-16 -->

# Agent Workflow Rules

Rules that exist because breaking them already caused damage on this checkout.

## One writer at a time

There is **one working tree**. Two agents editing it concurrently have
overwritten each other's work multiple times. Serialize on this directory, or
give parallel lanes separate worktrees or strictly disjoint paths. "Different
files" is not enough when both writers also run migrations, builds or `pint`.

## Beads

`bd` works here with **no flags**. The configuration is fragile and
non-obvious — all three must hold:

- `git config --local beads.role` is `maintainer`.
- `~/.beads` is a symlink to `/home/mohamed/.local/share/beads/crm-platform`
  (the same store `.beads` points at).
- `.beads/config.yaml` contains **no `repos:` block** (all examples commented).

**Never run a bare `bd init`.** It detects the `upstream` remote, restores
`beads.role=contributor`, re-adds a read-only planning repo and appends to
`.gitignore`; every issue mutation then fails with `embeddeddolt: store is
read-only`, which reads like a disk fault. If re-initialising is genuinely
required:

```bash
bd init --non-interactive --prefix crm --role maintainer --skip-agents --skip-hooks
```

then restore `.gitignore` from `HEAD` and delete the `repos:` block.

Use `bd` for durable project work, not markdown TODO files. Do not use
`bd edit` (interactive); use `bd update` flags.

## Hard rules

- **Never** run `migrate:fresh`, `db:wipe` or `migrate --force` by hand against
  the live database. Use `make db-reset`, whose confirmation must name the
  database it destroys (`CONFIRM=krayin`) and which also requires
  `KRAYIN_ADMIN_EMAIL` and `KRAYIN_ADMIN_PASSWORD`, refusing before it destroys
  anything when either is missing. It refuses while
  `bootstrap/cache/config.php` exists (a cached config makes artisan read a
  different database name than the guards do) and it ends fully installed, with
  the administrator recreated. See
  [../operations/database.md](../operations/database.md).
- **Never** test the `db-reset` guard against the live database. `APP_ENV` is
  read from `.env` only, so setting `APP_ENV=production` in the environment does
  not make it refuse — an instance whose `.env` says `local` is still wiped.
  Test against a scratch database (`krayin_scratch_*`).
- **Never** run `docker volume prune` or `docker system prune`.
- **Never** modify core Krayin files; extend through `packages/Webkul/`.
- **Never** commit `.env` or any credential. A credential has already been
  hardcoded in a committable script once.
- **Never** use `pkill -f 'php artisan serve'`; it is unanchored and killed the
  shell that ran it. Use `make stop`.
- **Never** "fix" permissions or line endings on this NTFS mount; see
  [../operations/environment-and-deployment.md](../operations/environment-and-deployment.md).
- **Never** run the installer (`krayin-crm:install`) against an instance holding
  data — it runs `migrate:fresh` and rotates `APP_KEY`.

## How to prove work

"It should work" has repeatedly been wrong here. Every claim in a handoff must
be reproducible by a command:

- HTTP status, not assumption:
  `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/admin/login`
- Log in for real when the change touches auth or the admin UI; a rendered page
  is not a working session.
- Run the suite: `make test`.
- Count rows, not just tables: `make db-shell` then `SELECT COUNT(*) FROM users;`
- `make status` bundles the HTTP, table-count and container checks.

## Related

- Environment and mount caveats → [../operations/environment-and-deployment.md](../operations/environment-and-deployment.md)
- Testing → [testing.md](testing.md)
- Failures behind these rules → [../errors/lands-from-experience.md](../errors/lands-from-experience.md)

<!-- Context: quick-start.md | Priority: critical | Version: 1.0 | Updated: 2026-10-16 -->

# Quick Start

Private commercial fork of Krayin CRM (Laravel 12 + Pest 3). Branch `main` is
the product; branch `2.2` tracks upstream `krayin/laravel-crm`.

## Orient in four commands

```bash
make                 # list every target, with the live port, env and db names
make status          # listener, HTTP codes, db table/row counts, container state
make test            # Pest suite against krayin_test
make serve / make stop
```

## The five things that break this checkout

1. **Never** run `migrate:fresh`, `db:wipe` or `migrate --force` by hand against
   the live database. Use `make db-reset` (guarded; the confirmation must name
   the database, e.g. `CONFIRM=krayin`, and `KRAYIN_ADMIN_EMAIL` plus
   `KRAYIN_ADMIN_PASSWORD` are required, so the reset ends fully installed).
   `APP_ENV` comes from `.env` only — a caller's `APP_ENV` does not make the
   guard refuse — so never test the guard against `krayin`.
2. **Never** run `docker volume prune` or `docker system prune` — the named
   volume `crm-platform-mysql-data` is the only copy of the data.
3. **Never** run a bare `bd init` — it breaks the beads store. See
   [guides/agent-workflow-rules.md](guides/agent-workflow-rules.md).
4. **Never** use `pkill -f 'php artisan serve'`. Use `make stop`.
5. **One writer at a time** on this working tree. Parallel lanes need separate
   worktrees or disjoint paths.

## Map

- Deployment, container, volume, ports → [operations/environment-and-deployment.md](operations/environment-and-deployment.md)
- Install, reinstall, second client instance → [operations/provisioning.md](operations/provisioning.md)
- Live vs test database, backup, reset → [operations/database.md](operations/database.md)
- Pest and the double-env trap → [guides/testing.md](guides/testing.md)
- Beads, workflow rules, proving work → [guides/agent-workflow-rules.md](guides/agent-workflow-rules.md)
- Past failures and their checks → [errors/lands-from-experience.md](errors/lands-from-experience.md)

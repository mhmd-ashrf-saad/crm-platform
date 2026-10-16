<!-- Context: operations/environment-and-deployment.md | Priority: high | Version: 1.0 | Updated: 2026-10-16 -->

# Environment and Deployment

Verified against the running machine on 2026-10-16.

## Repository model

- `origin` = `github.com/mhmd-ashrf-saad/crm-platform.git` (private fork).
- `upstream` = `github.com/krayin/laravel-crm.git` (MIT-licensed source).
- `main` is the product and is **never force-pushed**. `2.2` tracks upstream.
  Upstream updates are merged from `2.2` into `main`, never rebased onto it.

## The runtime

| What | Value |
| ---- | ----- |
| App URL | `http://127.0.0.1:8000` (`APP_URL` in `.env`) |
| Server | `php artisan serve --host=127.0.0.1 --port=8000`, detached, rooted at `public/` |
| Server log | `storage/logs/serve.log` |
| MySQL | Docker container `crm-platform-mysql` (`mysql:8.4`) published on `127.0.0.1:3306` only (loopback) |
| MySQL data | **named volume** `crm-platform-mysql-data` mounted at `/var/lib/mysql` |
| Live database | `krayin` — 61 tables |
| Test database | `krayin_test` — 61 tables, pinned in `phpunit.xml` |

The container reports `MYSQL_DATABASE=krayin` and `MYSQL_USER=krayin`. The
application never runs on the host; only `php artisan` and `curl` do.

The MySQL port is bound to loopback: `-p 127.0.0.1:3306:3306`, never
`-p 3306:3306`. A bare `-p 3306:3306` publishes the server on `0.0.0.0` and
`[::]`, which makes every client database reachable from the LAN; that is the
wrong default for a product meant to be sold and deployed on client machines.
`scripts/provision-dev.sh` binds loopback explicitly for the same reason, and
`docker inspect crm-platform-mysql --format '{{json .HostConfig.PortBindings}}'`
must show `"HostIp":"127.0.0.1"`.

## The volume is the data

The container is disposable; the named volume is not. Rebuilding the container
preserves the data because the volume is reattached. **Never run
`docker volume prune` or `docker system prune`** — either deletes the volume and
every client database inside it, irreversibly. The provisioning script refuses
if the existing container is not backed by this named volume and prints the
rebuild command instead.

## Secrets

All secrets are in the gitignored `.env`: `APP_KEY`, `DB_PASSWORD`,
`DB_ROOT_PASSWORD`, mail/IMAP/S3 credentials. `.env.example` holds placeholders
only. Never print a secret value; refer to keys by name. A development
credential is still a credential — always set a private
`KRAYIN_ADMIN_PASSWORD` for anything a client can reach.

## Filesystem caveats

- The checkout is on an NTFS `fuseblk` mount (`mount | grep /mnt`). Everything
  reports mode `777` and `core.filemode` is deliberately `false`. Do not clean
  permissions or line endings — it produces phantom diffs and changes nothing.
- The same mount can silently turn **read-only** if Windows boots or hibernates
  without Fast Startup disabled. Check `mount | grep /mnt` for `ro` before
  debugging any strange git or write failure.
- `public/storage` is a symlink and is gitignored. It must be **relative**
  (`../storage/app/public`); an absolute target dangles whenever the checkout is
  copied or the mount path changes. See
  [../errors/lands-from-experience.md](../errors/lands-from-experience.md).

## Related

- Provisioning and admin creation → [provisioning.md](provisioning.md)
- Database details → [database.md](database.md)

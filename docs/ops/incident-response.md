# Incident response — data loss, database outage, and the order people are told

> **Status:** draft for operator review. §6 (restore rehearsal) **has been
> performed** on 2026-11-09 against this checkout's live database, and its result
> row is filled in with measured counts and timings.
> **Applies to:** any client instance, and the vendor's own demo instance.
> **Companions:** [support-sla.md](support-sla.md) (severity and hours),
> [security-review.md](security-review.md),
> [../legal/dpa.md](../legal/dpa.md) §7 (breach notification),
> [../legal/data-residency.md](../legal/data-residency.md) §4 (what backups exist).
> **Canonical detail this file does not restate:** `deploy/README.md` (deployment
> runbook, rollback, drills) and `.agents/context/operations/database.md`
> (dump and restore command shapes).

## 1. The first five minutes, in order

1. **Stop writing.** If data loss is suspected, the single most damaging thing is
   continued writes over the thing you will try to recover. Put the app in
   maintenance mode or stop the app container — **not** the database container.
2. **Take a copy of the current state before touching anything.** A snapshot of
   the database volume and of `storage/` (see §3). The "broken" state is evidence,
   and the restore needs a fallback if the first attempt makes it worse.
3. **Do not restart to "see if it helps".** A restart re-runs the container
   entrypoint, which runs migrations and can re-arm the installer (§4).
4. **Start the clock** for the client notification in §2.
5. **Write down the time of every action from here on.** The after-action review
   in §8 is impossible without it, and so is any insurance or legal conversation.

## 2. Who is told, and in what order

| # | Who | When | What they are told |
| --- | --- | --- | --- |
| 1 | **The named client contact who may open a severity 1** ([support-sla.md](support-sla.md) §9) | **Within 1 hour** of a suspected data-loss or outage confirmation, during business hours; at the start of the next business hours otherwise | That something is wrong, whether the instance is down or data may be missing, what is being done, and the time of the next update |
| 2 | **The client's administrator** who grants access | Same call/message, only if access is needed | What access is needed and why |
| 3 | **The rest of the client's affected users** | Only through the client's own administrator. The vendor never mails the client's staff directly | A one-paragraph status written by the vendor, forwarded by the client |
| 4 | **The vendor's own records** (a bead, and the ticket) | Immediately, before the second update | Timeline, decisions, evidence paths |
| 5 | **A lawyer** | On any personal-data breach, or any loss that may be contractual | Facts only; no speculation |
| 6 | **The Egyptian Data Protection Centre / other authority** | **DECISION** — the client is the controller and normally notifies. The vendor does not notify an authority unless instructed in writing ([../legal/dpa.md](../legal/dpa.md) §7.3) | — |
| 7 | **Anyone else** — other clients, a status page, social media | Never before #1 | Nothing is said publicly that has not been said to the affected client first |

**Update cadence:** at minimum every 4 business hours while the incident is open,
even when the update is "no change". The commitment in the first message is a
time, and it is met.

**What is never said:** that the data is safe before it has been verified;
that a cause is known before it is; a blame statement about a named person; a
recovery time that has not been measured.

## 3. Preserve the evidence before restoring (both modes)

Run these against the affected instance's compose project. They copy; they do not
modify.

```bash
# 1. Database volume -> a tar on the host, before any restore attempt.
#    The volume name is ${SIJILNA_DB_VOLUME:-sijilna-db-data}, NOT <project>_<key>:
#    deploy/docker-compose.yml pins `name:` on the volume, so a second instance
#    changes that one key and shares nothing.
docker run --rm \
  -v "${SIJILNA_DB_VOLUME:-sijilna-db-data}:/data:ro" \
  -v /var/backups/sijilna/<date>:/out \
  alpine tar czf /out/db-volume.tgz -C /data .

# 2. Uploaded files and the checkout's .env
tar czf /var/backups/sijilna/<date>/storage.tgz -C /opt/<project>/app storage .env

# 3. Container state and the last 500 log lines of each container
docker compose -f /opt/<project>/app/deploy/docker-compose.yml ps    > /var/backups/sijilna/<date>/ps.txt
docker compose -f /opt/<project>/app/deploy/docker-compose.yml logs --tail=500 > /var/backups/sijilna/<date>/logs.txt
```

**Never** run `docker volume prune` or `docker system prune` at any point in an
incident. Either one deletes the database volume and every other client's
database with it, and there is no undo (AGENTS.md hard rules;
`.agents/context/operations/environment-and-deployment.md`).

## 4. Known total-loss vectors — check these first

Each of these has already happened or is one file away from happening. They are
listed in the order they should be ruled out, because the first two are silent and
look like a database fault.

| # | Vector | How to detect it | Why it is catastrophic |
| --- | --- | --- | --- |
| 1 | **`storage/installed` was deleted** (or `storage/` was wiped — `git clean -xfd` does it) | `ls /opt/<project>/app/storage/installed`; the app container's entrypoint log shows the installer running | With the marker gone the next container start re-arms the installer's internal `migrate:fresh` against the **live** database, with no warning. This is the idempotency marker and the destructive-install kill-switch (`.agents/context/operations/deployment-homelab.md`) |
| 2 | **A reset command was run against the wrong database** | The last shell history / `bd` record; the entrypoint log | `migrate:fresh` / `db:wipe` empties the schema. `make db-reset` exists because the development database was destroyed this way once (AGENTS.md) |
| 3 | **A prune** | `docker volume ls` — the volume is gone | Irreversible. Only the backups in [../legal/data-residency.md](../legal/data-residency.md) §4 remain |
| 4 | **Disk full** | `df -h`, MySQL error log | MySQL stops writing; the app reports 500s that look like a database outage |
| 5 | **A failed deployment rolled back past a migration** | `/opt/<project>/deployed-tag`, `/var/log/<project>-deploy.log`, `docker images` for the pre-deploy image | `deploy/deploy.sh` dumps before deploying and rolls back to the previous tag — check whether the rollback ran and which dump precedes it |
| 6 | **The `.env` `APP_KEY` changed** | `grep APP_KEY /opt/<project>/app/.env`, compare with the dump that will be restored | Encrypted columns become unreadable. The database is intact and the data is gone. This is not a database fault and a restore will not fix it; restore the **matching** `APP_KEY` with the data (`.agents/context/operations/database.md`) |
| 7 | **A client user deleted records** | The deleted record ids and the app's log | Recovery is bounded by the backup window. Say the window, not "we'll try" |

## 5. Restore procedure

The shape of a safe restore: **restore into a scratch database, verify it, then
swap.** Never restore over the live database and hope.

```bash
# 0. You are here only after §3 has copied the current state.

# 1. Find the newest dump that predates the incident, and verify it before you trust it.
ls -lt /opt/<project>/backups/ | head          # pre-deploy dumps, 10 kept
#    (or the nightly set's mysqldump, per ../legal/data-residency.md §4)

# For THIS checkout the nightly set is the one `make backup` writes, and it lives outside the
# repository in $HOME/backups/crm-platform/:
make backup-verify                            # sha256, size, gzip, completeness, table count
bash scripts/backup-db.sh --list              # what retention keeps, and what it would drop
#    Each dump has a sibling `<dump>.manifest` recording the sha256, both byte sizes, the database
#    name, the table count and the row counts of leads, persons, organizations, products, quotes,
#    quote_items, activities, emails and users. `backup-verify` re-checks the file against it; the
#    rehearsal in §6 proves the counts by restoring. Full behavior: scripts/README.md §Backups.

# 2. Restore it into a SCRATCH database first — never into the live one.
docker exec -e MYSQL_PWD="$DB_ROOT_PASSWORD" <project>-db \
  mysql -uroot -e "CREATE DATABASE sijilna_restore_check;"
docker exec -i -e MYSQL_PWD="$DB_ROOT_PASSWORD" <project>-db \
  mysql -uroot sijilna_restore_check < <dump-file>

# 3. Verify contents, not file size. Count rows in the tables that matter.
docker exec -e MYSQL_PWD="$DB_ROOT_PASSWORD" <project>-db mysql -uroot -N -e "
  SELECT 'leads', COUNT(*) FROM sijilna_restore_check.leads
  UNION ALL SELECT 'quotes', COUNT(*) FROM sijilna_restore_check.quotes
  UNION ALL SELECT 'persons', COUNT(*) FROM sijilna_restore_check.persons
  UNION ALL SELECT 'users', COUNT(*) FROM sijilna_restore_check.users
  UNION ALL SELECT 'activities', COUNT(*) FROM sijilna_restore_check.activities;"

# 4. Compare with what the client says they had, or with the previous dump.
#    A dump whose users table is empty is not a usable backup.

# 5. Only now, and only with the client's agreement on the restore point,
#    swap: keep the current database aside, then load the verified dump over it.
```

**Verification is by restoring, never by the file's size.**
`mysqldump` run as the application user prints an "Access denied … PROCESS
privilege" line and **still exits 0** — the dump is complete, the message is
noise (`.agents/context/operations/database.md`). Reading only the exit code or
the byte count is how an empty dump passes for a good one.

**Restore `APP_KEY` with the data.** A restore without the matching key leaves
encrypted columns unreadable (§4 #6).

**After the swap:** confirm `/admin/login` answers 200, log in with a known
administrator, open a lead with an attachment, and export one quote PDF under the
Arabic locale (the mPDF temporary directory is the failure that already happened
once — `.agents/context/operations/deployment-homelab.md`).

## 6. The rehearsal — performed, and repeatable

**Why this section is not a table of good intentions.** On 2026-11-08 the live
database was destroyed by a misaimed installer, and the entire recovery capability
at that moment was one stray dump in `/var/tmp/pi-scratch/<run>/krayin.sql` — a
directory a 48-hour reaper deletes. It was three days old, so three days of writes
were lost. There was no schedule, no retention policy and no rehearsed restore.
All three exist now, and the restore below has been run against the live data.

### The three commands

```bash
make backup            # dump + manifest, retention enforced             (~1 s here)
make backup-verify     # re-check the newest dump against its manifest   (~0.2 s here)
make backup-rehearse   # restore into a throwaway database and prove it (~4.5 s here)
```

What each one is allowed to touch:

| Command | Reads | Writes |
| --- | --- | --- |
| `make backup` | the live database, through `mysqldump` inside `crm-platform-mysql` | `$HOME/backups/crm-platform/<db>-<UTC timestamp>.sql.gz` and its `.manifest` |
| `make backup-verify` | the dump and its manifest | nothing |
| `make backup-rehearse` | the dump, its manifest, the live database (SELECT only) | a throwaway database named `*_rehearsal`, which it drops before it exits |

Neither the verify nor the rehearsal writes to the live database. The rehearsal
re-reads the live row counts at the end and fails if any of them moved, which is
how it proves the import stayed in the throwaway database rather than asserting it.

### Retention, stated and enforced

The dump script keeps the newest dump of each of the last **14 days**, plus the
newest of each of the last **8 ISO weeks**, plus the newest of each of the last
**6 calendar months** — restic's `forget --keep-daily/--keep-weekly/--keep-monthly`
semantics, the same policy the homelab offsite pull uses: a dump kept by *any* of
the three rules is kept. At one dump a day that settles at roughly 20-24 files.
Override with `KEEP_DAILY`, `KEEP_WEEKLY` and `KEEP_MONTHLY`.

Retention only ever deletes files matching `<db>-<YYYYMMDDTHHMMSSZ>.sql.gz` and
their manifests. A hand-taken dump — the emergency copy
`/home/mohamed/backups/crm-platform/krayin-2026-11-05-1422.sql` is one — never
matches and is never deleted by it.

### Where the dumps live, and what protects them

`$HOME/backups/crm-platform/`, outside the checkout and outside `/tmp` and
`/var/tmp/pi-scratch/`, so no reaper can reach them. A dump holds live client CRM
rows and every user's bcrypt password hash, so the directory is created mode 700
and each file 600, and the script **refuses** to write into a `BACKUP_DIR` inside
the checkout unless `git check-ignore` proves the path is ignored
(`storage/backups/` is, at `.gitignore:37`).

### Scheduling

`scripts/systemd/crm-platform-backup.timer` runs it nightly at 02:40 — a systemd
**user** timer, because that is how scheduled work already runs on this host
(`homelab-pull.timer` pulls the offsite restic repository at 03:30, and
`homelab-backup.timer` runs the homelab host's own backup at 03:00). Nothing in
this repository installs Laravel's `schedule:run`, so adding a cron entry or a
Laravel schedule would have been a second mechanism competing with the documented
one. Install it once (the checkout path is substituted in at install time):

```bash
mkdir -p ~/.config/systemd/user
sed "s|@CHECKOUT@|$PWD|g" scripts/systemd/crm-platform-backup.service \
  > ~/.config/systemd/user/crm-platform-backup.service
sed "s|@CHECKOUT@|$PWD|g" scripts/systemd/crm-platform-backup.timer \
  > ~/.config/systemd/user/crm-platform-backup.timer
systemctl --user daemon-reload && systemctl --user enable --now crm-platform-backup.timer
systemctl --user list-timers crm-platform-backup.timer     # next run, last run
journalctl --user -u crm-platform-backup.service           # what each night did
systemctl --user start crm-platform-backup.service         # once, now
```

`Persistent=true` in the timer means a night the machine was off is not a silent
gap: the unit runs as soon as the timer is next active.

### The rehearsal result

Run on this checkout, `2026-11-09`, against the live database `krayin` (61 tables)
and MySQL 8.4.11 in `crm-platform-mysql`:

1. `make backup` — dump of `krayin`, 52 079 bytes gzipped (355 120 uncompressed),
   taken in **0.33 s**. Manifest records sha256, both sizes, 61 tables and the
   nine row counts below, with `counts_stable=yes`.
2. `make backup-verify` — sha256, byte size, gzip integrity, the `-- Dump completed`
   trailer and the `CREATE TABLE` count all matched the manifest (7/7 PASS,
   **0.11 s**).
3. `make backup-rehearse` restored it into `krayin_backup_rehearsal` — never into
   `krayin`, never into `krayin_test` — after stripping the dump's own
   `CREATE DATABASE`/`USE` statements and asserting none were left, because
   importing them unchanged would have written to the live database. Import took
   **2.13 s**.
4. Restored counts matched the manifest exactly (9/9 tables, 61/61 tables) and
   equalled the live values: leads 27, persons 19, organizations 8, products 12,
   quotes 10, quote_items 22, activities 486, emails 9, users 14.
5. A real login against the restored data (**0.69 s**): all 14 restored `users`
   rows carried a 60-character bcrypt cost-10 hash; the restored hash for user id
   1 rejected a wrong password (negative control); `Hash::check()` accepted a
   password read back from the restored row after writing it; `Auth::guard('user')->attempt()`
   authenticated user id 1; and `bouncer()->hasPermission('dashboard')` resolved
   the restored role. **PASS.**
6. The live database's table count and nine row counts were identical before and
   after. **PASS.**
7. `krayin_backup_rehearsal` was dropped and confirmed absent from
   `information_schema`. **PASS.**

End to end: **4.5 seconds** for 61 tables and 14 users. That is the only
truthful restoration figure this runbook can quote, and it is the figure for *this*
instance at *this* size — it is not a promise about a client instance whose
database is a thousand times larger.

| Rehearsal | Date | Dump source | Row counts before/after | Elapsed | Result |
| --- | --- | --- | --- | --- | --- |
| 1 | 2026-11-09 | `make backup` of live `krayin` (61 tables, 52 KB gz) restored into `krayin_backup_rehearsal` | leads 27/27, persons 19/19, organizations 8/8, products 12/12, quotes 10/10, quote_items 22/22, activities 486/486, emails 9/9, users 14/14, tables 61/61 | 4.5 s wall clock (dump 0.3 s, verify 0.1 s, restore 2.1 s, login 0.7 s) | **PASS** — manifest verified, counts equal, administrator signed in, live database unchanged, scratch database dropped |

**How to re-run it** (do this before quoting a restore time to anyone, and after
any change to the schema or the container): `make backup && make backup-rehearse`.
A rehearsal that fails names the check it failed and drops the throwaway database
anyway. To leave it in place for inspection, run the script directly:
`bash scripts/restore-rehearsal.sh --keep`.

**What is still not rehearsed**, and must not be claimed: a restore into a *client*
instance, a restore that has to also replace `APP_KEY` and `storage/`, and a
restore from **off this host**. Every dump this repository produces sits on the
same disk as the database it came from; the offsite leg is still open, and until
it exists a fire, a stolen laptop or a dead disk still takes the database and its
backups together. The dump's manifest records `app_key_sha256` so that an operator
can prove they hold the matching `APP_KEY` (which is what decrypts encrypted
columns) without the key being written next to the data.

## 7. Deletion on termination — the executable version

[../legal/dpa.md](../legal/dpa.md) §8 promises destruction within 30 days. This is
the procedure that keeps that promise. It is destructive by design and is run only
for a client whose contract has ended, with the termination date written down.

1. **Export first, and confirm the client received it** — a dump, `storage/`, and
   `APP_KEY` (§5 command shapes).
2. **Write the delete date and the reason** in the ticket.
3. Stop and remove **this project's** containers and its named volume by name:
   `docker compose -f /opt/<project>/app/deploy/docker-compose.yml down -v`.
   **Never** a global prune.
4. Delete the checkout: `rm -rf /opt/<project>`.
5. Delete this instance's pre-deploy dumps and any staging copy.
6. Delete this instance's snapshots from the backup repository
   (`restic forget --prune` with the snapshot tags or paths for this instance
   only), **including the laptop copy** — a copy on removable or secondary storage
   is exactly the one that is forgotten.
7. Confirm the volume is gone: `docker volume ls | grep "${SIJILNA_DB_VOLUME:-sijilna-db-data}"`
   returns nothing. `docker compose down -v` already removed it — the check is the
   evidence for the certificate, not the belief that `-v` worked.
8. Send the client a one-page certificate: what was deleted, the date, the
   instance, and who did it.

**What is not deleted:** contract and invoice records, kept as long as Egyptian
law requires ([../legal/dpa.md](../legal/dpa.md) §8.4).

## 8. After-action review — within 5 business days

Written and sent to the client, five sections, no more:

1. **What happened**, in plain language, with times.
2. **Impact** — who was affected, from when to when, what data (counts, not
   adjectives).
3. **Cause**, one sentence.
4. **What was done**, in order, including what did not work.
5. **What changes** — each item with an owner and a date, recorded as a bead.

**DECISION** — whether the review is shared with the client in full or summarised.
Propose full, minus anything that would disclose another client's data or a
credential.

## 9. Security incidents

A suspected compromise has its own first step: **preserve, do not clean.** Rotate
`APP_KEY` only after a database and file copy exists, because rotation makes the
encrypted columns unreadable without the old key. Rotating `APP_KEY` is documented
in [secrets-handling.md](secrets-handling.md) §3c; the dependency side of a
compromise starts at [security-review.md](security-review.md) §4. The
personal-data notification clock in [../legal/dpa.md](../legal/dpa.md) §7.1 — 72
hours — starts when the vendor becomes aware, not when the cause is known.

## Sources

- `.agents/context/operations/database.md` — dumps, `--no-tablespaces`, verify by
  restoring, keep `APP_KEY` with the dump.
- `scripts/backup-db.sh` — the dump, the manifest it writes and the retention it
  enforces; `scripts/restore-rehearsal.sh` and `scripts/verify-restored-login.php`
  — the rehearsal and the login proof; `scripts/systemd/crm-platform-backup.timer`
  — the nightly schedule. Behaviour and flags: `scripts/README.md` §Backups.
- `.agents/context/operations/deployment-homelab.md` — `storage/installed`
  kill-switch, deploy/rollback, health gate, mPDF failure.
- `.agents/context/operations/environment-and-deployment.md` — prune rules,
  volume-is-the-data.
- `docs/client-guide/ar/16-limits-and-provider.md` — provider services, handover.
- `AGENTS.md` — hard rules (`db-reset`, prune, `pkill`).
- `deploy/README.md` — deployment runbook, rollback detail, drills.

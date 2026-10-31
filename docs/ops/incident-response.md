# Incident response — data loss, database outage, and the order people are told

> **Status:** draft for operator review. §6 (restore rehearsal) has **not been
> performed** — the procedure is written and the result field is empty on purpose.
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

# 1. Find the newest dump that predates the incident.
ls -lt /opt/<project>/backups/ | head          # pre-deploy dumps, 10 kept
#    (or the nightly set's mysqldump, per ../legal/data-residency.md §4)

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

## 6. The rehearsal — required, and not yet done

The acceptance criterion for this runbook is **a restore rehearsed following these
steps**. It has not been performed. What follows is the exact script, so that it
is a rehearsal and not an improvisation.

**Rules for the rehearsal:** a scratch database on the development host, never the
live one; never on a client instance; the dump can be synthetic.

1. Take a dump of a disposable database with the command shape in
   `.agents/context/operations/database.md` (with `--no-tablespaces`).
2. Note the row counts of `leads`, `quotes`, `persons`, `users`, `activities`.
3. Drop the table set, or create a fresh scratch database.
4. Restore the dump into it following §5 step 2.
5. Re-count the same five tables and compare with step 2. Equal counts = pass.
6. Record the elapsed time from "dump exists" to "verified restore" — that number
   is the only honest restoration time the operator can quote, and it belongs in
   [support-sla.md](support-sla.md) §3 as the basis for a severity-1 target.
7. Write the result in the table below, with the date and the counts.

| Rehearsal | Date | Dump source | Row counts before/after | Elapsed | Result |
| --- | --- | --- | --- | --- | --- |
| 1 | **not performed** | — | — | — | — |

**Until row 1 is filled:** no restore time is quoted to a client, and
[../legal/terms-of-service.md](../legal/terms-of-service.md) §9.3 stays as written
— recovery is limited to the practiced procedure, and there is no practiced
procedure yet.

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
- `.agents/context/operations/deployment-homelab.md` — `storage/installed`
  kill-switch, deploy/rollback, health gate, mPDF failure.
- `.agents/context/operations/environment-and-deployment.md` — prune rules,
  volume-is-the-data.
- `docs/client-guide/ar/16-limits-and-provider.md` — provider services, handover.
- `AGENTS.md` — hard rules (`db-reset`, prune, `pkill`).
- `deploy/README.md` — deployment runbook, rollback detail, drills.

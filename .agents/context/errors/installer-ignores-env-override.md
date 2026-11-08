<!-- Context: errors/installer-ignores-env-override.md | Priority: high | Version: 1.0 | Updated: 2026-11-08 -->

# The installer ignores the environment override and wipes the live database

On 2026-11-08 a scratch-database install ran `migrate:fresh` against the live
`krayin` database. The command discards the environment override one line before
it migrates, so a pre-flight check that reads the environment proves nothing.
The database was restored from a three-day-old dump; three days of writes are
gone for good.

## 1. What happened

- At ~20:31 a lane was asked to verify the demo dataset on a fresh install.
- It printed the target first —
  `php artisan tinker --execute="echo DB::connection()->getDatabaseName();"` —
  which returned `krayin_demo_verify`, and then ran `php artisan
  krayin-crm:install` with `DB_DATABASE=krayin_demo_verify` set.
- `migrate:fresh` ran against `krayin` instead.
- `krayin_demo_verify` was left with **0 tables**. That empty scratch database is
  the proof of where the migrate actually landed.

## 2. Why the guard failed

- The pre-flight check answers a different question than the one asked. It proves
  what `DB::connection()` resolves to *at that moment*; it cannot see a command
  that later re-derives the connection from the `.env` **file**.
- `Installer::loadEnvConfigAtRuntime()`
  (`packages/Webkul/Installer/src/Console/Commands/Installer.php`, ~line 413)
  calls `file(base_path('.env'))`, sets
  `config(["database.connections.mysql.database" => …])` from the file's value,
  then `DB::purge()`.
- Its sub-call to `migrate:fresh` (~line 140) then uses that purged connection.
  The environment override is already gone.
- Verify the behaviour in the source, not with a connection check:
  `grep -n 'loadEnvConfigAtRuntime\|migrate:fresh\|DB::purge' packages/Webkul/Installer/src/Console/Commands/Installer.php`.
  Do not verify it by running the installer.

## 3. Blast radius

- **Destroyed:** 27 leads, 19 persons, 8 organizations, 10 quotes, 22 quote
  items, 486 activities, 12 products, 9 emails, 566 attribute values, and all 14
  user rows.
- **Rotated:** `APP_KEY`, by the installer's `key:generate`. Restored from a
  backup taken minutes earlier, so encrypted data was not lost to it.

## 4. Detection and recovery

- **Detection:** the lane noticed within the same run and stopped. Nothing else
  ran against the database afterwards.
- **Recovery**, staged rather than direct:
  1. Imported the Nov 5 14:22 dump into a scratch database.
  2. Verified nine table counts against it.
  3. Verified `APP_KEY` compatibility with a negative control: zero encrypted
     values existed in the data, so a different key could not break it.
  4. Ran 18/18 login assertions against the restored copy on port 8002.
  5. Only then dropped and recreated `krayin` and imported wholesale.
  6. Applied the two post-dump migrations additively, counts unchanged.
  7. Re-set the three demo passwords and rewrote `credentials-krayin.txt`.

## 5. What is permanently lost

- Everything written to `krayin` between **2026-11-05 14:22** and **2026-11-08
  20:31**: any record, edit or config changed in that window. The dump is the
  ceiling of what survives.
- The binlogs could not close the gap — `mysqlbinlog` is absent from the
  container, so they could not be replayed.

## Related

- The rule this produced → `AGENTS.md`, "Never aim
  `php artisan krayin-crm:install` at any database".
- `bd show crm-7ov` — the hazard itself: patch `loadEnvConfigAtRuntime()` to
  honour the resolved config, or wrap the installer in a guard.
- `bd show crm-avm` — the backup gap this exposed (see also `crm-7rj`).
- `bd show crm-zhp` — the fresh-install demo render check that caused this is
  still unverified.
- Other failures on this checkout → [lands-from-experience.md](lands-from-experience.md)

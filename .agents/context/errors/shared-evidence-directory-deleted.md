<!-- Context: errors/shared-evidence-directory-deleted.md | Priority: high | Version: 1.0 | Updated: 2026-11-09 -->

# A lane deleted a shared evidence directory while cleaning its own scratch

On 2026-11-09 a wrap-up lane ran `rm -rf storage/backups` to clear the test
artefacts its own run had written. `storage/backups/` was not that lane's
scratch: it held several lanes' preserved screenshot evidence and the only
in-checkout copy of the last full-cluster recovery dump. Everything was
recovered by sha256 from the scratch originals that had not yet been reaped —
which was luck about timing, not a property of the deletion.

## 1. What happened

- The lane reasoned "my output lives under `storage/`", found a directory whose
  name matched the kind of thing it had been writing, and removed the whole
  path rather than the files it had created.
- Nothing in the repository marks that directory as shared. It is gitignored
  (`.gitignore:37`), so `git status` did not show it as up for deletion either
  before or after the command.
- The removal succeeded silently. No tool or guard objected, because `rm -rf`
  on a gitignored path has nothing to object to.

## 2. Blast radius

- `demo-evidence/fresh-verify/` — 14 files.
- `demo-evidence/egp/` — 7 files.
- `demo-evidence/dash-window/` — 5 files.
- `demo-evidence/money-format/` — 8 files.
- `demo-evidence/README.md`.
- `krayin-2026-11-05-1422.sql` — the only copy inside the checkout of the last
  full-cluster recovery dump, the artefact the 2026-11-08 recovery ran from
  (see [installer-ignores-env-override.md](installer-ignores-env-override.md)).

That directory is what four closed beads cite as their evidence. Had it stayed
deleted, their claims would have had no artefact behind them and the
`2026-11-05` dump's in-checkout copy would have been gone — the off-host copy
in `$HOME/backups/crm-platform/` is a different file, not a duplicate of this
one.

## 3. Why it was recoverable

- The scratch originals under `/var/tmp/pi-scratch/` still existed; the reaper
  (`reap-pi-scratch.timer`) only removes them after 48 hours.
- Every file was matched to its original by **sha256** — 13 of 13 matched, and
  the dump matched `be488f1a…` — so the restore was a byte-identical copy, not a
  reconstruction.
- This was timing, not design. Had the reaper run first, or had any lane written
  the evidence straight into `storage/backups/` without a scratch original, the
  evidence behind four closed beads would have been gone permanently.

## 4. The rule

- **Delete only paths you created, by explicit name.** A directory you wrote a
  file into is not a directory you may remove. `rm -rf <dir>` claims every file
  any other run put there too.
- **Never `rm -rf` a directory you did not create in this run.** If a shared
  directory needs cleaning, delete the named files and leave the directory.
- **Scratch lives under `/var/tmp/pi-scratch/<runId>-<purpose>/` and is removed
  by that directory name**, which carries the run id. A `<runId>`-named path is
  provably yours; a shared path under the checkout is not.
- Check before removing anything wider than one file:
  `ls -la <dir>` shows whose files are there, and `git check-ignore -v <dir>`
  says whether git would have warned you. A gitignored path is the most
  dangerous to delete, because git is silent about it in both directions.

## Related

- The evidence recovered here was cited by `crm-7ov` and the demo lanes.
- The scratch and deletion rules → `AGENTS.md`, "Temporary storage policy
  (mandatory)".
- The incident this directory's dump came from →
  [installer-ignores-env-override.md](installer-ignores-env-override.md).
- Other failures on this checkout → [lands-from-experience.md](lands-from-experience.md)

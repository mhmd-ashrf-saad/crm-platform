# GitHub Actions — what each workflow costs and why it survives

**Status:** decided 2026-10. This repository is a **private** fork, so every
Actions minute is billed. The rule applied here: a workflow that is the only
guard on something is **kept and its trigger narrowed**; a workflow that guards
nothing is **removed**. Nothing was removed, because each of the four named
below is the only check of its kind — the money was in the triggers.

`Deploy Tag` (`deploy-tag.yml`) names these four workflows in its
`workflow_run` list. **Renaming any of them silently stops the deploy gate
waking**; the tag then only appears when `Reconcile Deploy Tags` sweeps, up to
30 minutes later. Rename them together or not at all.

## The four workflows

| Workflow | Only real guard on | Cost problem before | Decision | New trigger |
| --- | --- | --- | --- | --- |
| `CI` (`ci.yml`) | The **Pest suite**. Nothing else runs PHP tests. | A MySQL service, a composer install and a full `pest --parallel` for every push and every PR, including a commit that only touched Markdown. | **Kept, narrowed** | `push` / `pull_request` on `main` and `2.*`, with `paths-ignore` for `**/*.md`, `docs/**`, `brand/**`, `.agents/**`, `LICENSE`, `.github/skills/**`; plus `concurrency` with `cancel-in-progress` per ref. |
| `Linting Tests` (`auto_commits.yml`) | **Pint formatting**. `make lint-check` is manual; nothing else formats. | Ran on PRs too, where its commit lands in a head branch nobody deploys; and its commit re-runs the whole fleet, 15 Playwright shards included. | **Kept, narrowed** | `push` on `main` and `2.*` only, `paths` limited to `**/*.php`, `pint.json` and the workflow itself; `concurrency` with `cancel-in-progress`. |
| `Admin \| Playwright Tests` (`admin_playwright_tests.yml`) | The **admin UI in a browser**. Nothing else runs the e2e suite. | 15 shards, each paying composer install + Krayin install + `npm install` + browser download, on every push and every PR — a docs-only commit bought all fifteen. | **Kept, narrowed** | `push` / `pull_request` on `main` and `2.*`, allow-listed to paths that can change the admin UI (`app/**`, `bootstrap/**`, `config/**`, `database/**`, `lang/**`, `packages/**`, `public/**`, `resources/**`, `routes/**`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `pint.json`, `vite.config.js`, the workflow itself); `concurrency` with `cancel-in-progress`. |
| `Skills Consistency` (`skills-consistency.yml`) | The **skill symlink layout** (`.github/skills` as source of truth, every runtime linked). | Already path-filtered to `.github/skills/**`, `.agents/**`, the runtime links and `bin/validate-skills.sh`, and the job is a shell script. Seconds. | **Kept unchanged** | none needed. |

Cost after the change: a documentation, brand or `.agents/`-only push runs **no
workflow at all**. Such a commit therefore has no check-runs, so `Deploy Tag`
has nothing to wait for and never wakes; `Reconcile Deploy Tags` backfills the
`deploy-*` tag on its 30-minute sweep, which is exactly the gap it was written
for. A commit that touches real code still runs everything, and the cancelled
runs left behind by a fast follow-up push do not wake the gate either
(`Deploy Tag` requires `success`, and the replaced commit is not the one that
should be deployed).

## Reviewed and deliberately not changed

| Workflow | What it is | Why it stays as it is |
| --- | --- | --- |
| `Deploy Tag` (`deploy-tag.yml`) | The **only** thing that decides what is deployable: it tags `deploy-<short sha>` once every check-run on a `main` commit is completed and green. | Deleting it means the homelab deploys nothing. Its own bound is `timeout-minutes: 10` and a no-op wake exits in seconds. Its `on: workflow_run` list is the maintenance hazard described above. |
| `Reconcile Deploy Tags` (`reconcile-deploy-tags.yml`) | The **only** guard against a green `main` that never got a tag — the failure that measured itself on commit `9b95d8e2`, where the homelab sat two commits behind for two days with nothing red anywhere. | A clock-triggered sweep is the point of it. At `*/30 * * * *` it is up to 48 billed runs a day, but a no-op run is seconds and only the sweep step's 4-minute bound is ever approached when there is work to backfill. Widening the cron to two hours would cut that cost 4× and push the worst-case alarm latency to about four hours — an operator decision, not a cleanup, so it was left alone. |

## What is still expensive, and what was not done about it

- **Shard count.** 15 shards each pay the same fixed cost (composer install,
  Krayin install, `npm install`) before a shard runs. Fewer shards would buy
  fewer fixed costs and a longer wall clock. There is no measured wall-clock
  figure for the whole suite in this repository, so the shard count was left
  alone rather than guessed at.
- **`[skip ci]` on the Pint commit.** It would stop the formatting commit from
  re-running the fleet, and it is rejected on purpose: a commit with no
  check-runs satisfies "every check-run is green" vacuously, so an untested
  commit would be tagged and deployed.
- **Pinning actions to commit SHAs.** Every workflow here still uses moving
  tags (`actions/checkout@v4`, `shivammathur/setup-php@v2`). That is a supply
  chain question, not a minutes question, and it was out of scope here.

## Workflow-inline steps cannot be replayed from a local shell

The sweep and escalate steps of `Reconcile Deploy Tags` are inline in the
workflow, not scripts in the checkout, and every input they read arrives from
the job's `env:` block — `WINDOW`, `THRESHOLD_SECONDS`, `REPO`,
`RUNNER_TEMP`, `ESCALATE`, `TIP_SHA`, plus the runner's own
`GITHUB_SERVER_URL` and `GITHUB_RUN_ID`. Each step opens with
`set -euo pipefail`, so the first variable a local shell does not supply aborts
it before any of the logic runs:

```text
step0-sweep.sh: line 159: WINDOW: unbound variable
step1-escalate.sh: line 7: GITHUB_SERVER_URL: unbound variable
```

So a local replay of that step proves nothing either way: it dies at the first
unset input, and a shell that defines the variables by hand exercises that
hand-built environment rather than the job's. The manual path is a
`workflow_dispatch` run, which the workflow exposes for exactly this reason.


## A keyword quoted in a commit message suppresses the whole push

Measured 2026-10-29, and it cost a release push. GitHub reads its skip keywords
out of the commit message as a **substring**, anywhere in the message, quoted or
not. This file and the workflows in it name that keyword in prose, because the
`Linting Tests` commit must not carry it — and when the resulting commit message
contained the keyword inside backticks, GitHub treated the sentence as an
instruction. The push landed (`pushed_at` moved, and the third-party check apps
received the push event) and **no workflow was created at all**: no `CI`, no
`Linting Tests`, no `Admin | Playwright Tests`, therefore no `workflow_run` and
no deploy tag.

The dangerous part is what happens next. `Reconcile Deploy Tags` tags a commit
whose check-runs are all green — and a commit with **no** check-runs satisfies
that vacuously, which is deliberate, because a docs-only push legitimately
produces none. So a push silenced this way is still tagged and still pulled by
the homelab: an untested tree becomes production, with nothing red to look at.

Two rules follow, and neither is optional:

- **Never write the keyword in a commit message**, not even inside backticks, not
  even to say the message does not contain it. Refer to "the skip marker".
- **`workflow_dispatch` on every gate** is the only lever that does not require a
  new commit. It was added to all three gates after this incident; before that,
  the sole remedy for a silent push was another push, which is a different tree.

Diagnose it by absence, not by error: `gh run list --branch main` showing nothing
for a sha that `git log origin/main` already has, while
`gh api repos/<owner>/<repo>/commits/<sha>/check-suites` shows the third-party
apps and no Actions suite.

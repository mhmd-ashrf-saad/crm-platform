# Ops — runbooks and reviews

Status: **internal working documents**, except
[`support-sla.md`](support-sla.md), which is the one file in this folder written to
be shared with a client — and only after its `DECISION` blocks are filled in
([`decisions-to-confirm.md`](decisions-to-confirm.md)).

## What is in this folder

| File | What it is | Share with a client? |
| --- | --- | --- |
| [`support-sla.md`](support-sla.md) | The support channel, hours, severity table with response and resolution targets, in/out of scope, the report template the client fills, the per-client handover sheet | **Yes**, once D5–D9 are closed |
| [`incident-response.md`](incident-response.md) | What happens in the first five minutes, who is told in what order, the known total-loss vectors, the restore procedure with its verification, the restore rehearsal, the deletion-on-termination procedure, the after-action review | **Partly** — §2 (who is told, within what time) is quotable; the rest is ours |
| [`security-review.md`](security-review.md) | Source review plus `composer audit --locked` results: 16 advisories across 9 packages (1 critical, 6 high, 7 medium, 2 low), the config and authorisation findings, and the fix list with exact commands | **Yes, in summary** — a security questionnaire is answered from §2 and §3. Do not hand over the full file without a fix date |
| [`secrets-handling.md`](secrets-handling.md) | Every secret the product holds, per-instance provisioning, per-secret rotation with its blast radius, access control, leak response, the operator checklist | **No** — it names every key and what rotating it breaks |
| [`decisions-to-confirm.md`](decisions-to-confirm.md) | The one checklist of commitments the owner has not made, with what closing each unlocks | No |
| [`ci-workflows.md`](ci-workflows.md) | The CI workflow reference | No |

Companion documents: [`../legal/`](../legal/) for the privacy notice, DPA, terms
and data-residency statement.

## The rule these documents are held to

**Nothing may be claimed that is not traceable to a file in this repository.** In
practice that means `docs/client-guide/_internal/facts.md`, `README.md`, the
`.agents/context/` knowledge base, or the code and config being cited. Each
document ends with a `Sources` section listing what it was built from.

A statement that is a target rather than a fact is written as a target, and a
commitment the owner has not made is written as a **DECISION** block, never as a
default. Two examples that must not be softened:

- **No restore from backup has been rehearsed yet**, so no restore time is quoted
  ([`incident-response.md`](incident-response.md) §6).
- **This is a one-operator business.** No document here promises 24/7 cover, an
  uptime percentage, or a fix time the operator cannot control
  ([`support-sla.md`](support-sla.md) §5).

## The two failures that produce no error

Worth repeating here because both look like a working system: **scheduled tasks**
and **the queue**. Without `schedule:run` no inbound mail arrives and no campaign
sends, and every screen stays healthy
(`docs/client-guide/ar/16-limits-and-provider.md`). And **deleting
`storage/installed`** re-arms the installer's `migrate:fresh` against the live
database on the next container start, silently
(`.agents/context/operations/deployment-homelab.md`).

## Related runbooks outside this folder

- `scripts/README.md` — provisioning.
- `deploy/README.md` — deployment, rollback, drills.
- `Makefile` — the sanctioned command surface (`make` with no arguments lists it).
- `AGENTS.md` — hard rules; `.agents/context/operations/` — the operation of this
  checkout and the homelab demo.

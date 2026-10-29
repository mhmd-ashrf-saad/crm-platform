# Data processing agreement (DPA)

> **Status:** draft for operator review — **not legal advice**, not reviewed by a
> lawyer, and not signed with anyone.
> **Claims rule:** statements about the system cite a repository file. Blocks
> marked **DECISION** are commitments the operator has not made. Do not send this
> to a buyer with a `DECISION` block unresolved — an unsigned assumption in a DPA
> is a warranty you did not price.
> **Companion documents:** [privacy-notice.md](privacy-notice.md),
> [data-residency.md](data-residency.md), [terms-of-service.md](terms-of-service.md),
> [../ops/security-review.md](../ops/security-review.md).

This agreement applies when the vendor processes personal data on the client's
behalf. In the **default client-hosted deployment** the vendor processes nothing
except during an agreed support session — [data-residency.md](data-residency.md)
§1, §3 — so this DPA is short in practice. In **managed hosting** it applies in
full.

## 1. Roles

1.1 The **client** is the controller of the personal data in its instance: it
decides what is entered and why.

1.2 The **vendor** is a processor, and processes that data only on the client's
documented instructions — which are, at signature: operating the instance (in
managed hosting), taking the backups in §5, and providing the support in
[../ops/support-sla.md](../ops/support-sla.md).

1.3 The vendor processes one category of data for its own purposes: the business
contact details of the client's buying and support contacts, and the contract and
invoice record — [privacy-notice.md](privacy-notice.md) §1.

## 2. Scope and subject matter

| Item | Content |
| --- | --- |
| **Subject matter** | Hosting and supporting a self-hosted CRM instance |
| **Duration** | The term of the contract, plus the deletion window in §8 |
| **Categories of data subject** | The client's leads, customers, contacts, and the client's own staff who use the system |
| **Categories of personal data** | Names, business and personal contact details, job titles, notes, activity history, quote contents, and the contents of a shared business mailbox where the client connects one. **The product has no field that asks for sensitive data** — no health, religion, biometric or financial-account field exists in the schema; if the client enters such data into a free-text field, that is the client's decision and it is outside what this DPA contemplates |
| **Special-category data** | Not intended. The client is asked not to enter it |

## 3. The client's instructions

3.1 The vendor acts on the client's instructions only, and tells the client
immediately if an instruction appears to breach applicable law.

3.2 The vendor does not: sell the data, use it for its own marketing, mine it,
train models on it, or disclose it to anyone outside §6.

3.3 **Support access is an instruction.** In client-hosted mode the vendor has no
standing access: it reaches the instance only through a named account the client
creates, or through the client's own server, for the duration of a request. The
control is described as a **DECISION** in
[data-residency.md](data-residency.md) §3 because it is a commitment, not yet a
mechanism.

## 4. Security measures — what is true today

| Measure | State |
| --- | --- |
| Named accounts, hashed passwords | Yes — framework default hashing |
| Role-based authorisation on the sidebar, plus a per-user data-visibility scope (`global` / `group` / `individual`) | Yes — `docs/client-guide/_internal/facts.md` §2 |
| Login rate limiting | Yes — 5 failures per credential set, 20 per address per minute (`docs/client-guide/_internal/facts.md` §7.1) |
| Multi-factor authentication | **No.** None exists in the product |
| Encryption in transit | TLS at the ingress; on the demo the ingress is a Cloudflare Tunnel (.agents/context/operations/deployment-homelab.md) |
| Encryption at rest of the database volume | **No** — the MySQL volume is not encrypted by the product. Disk-level encryption is the host operator's choice: **DECISION** |
| Encryption of selected columns | Yes, with `APP_KEY` — stored in `.env` on the same disk as the data ([data-residency.md](data-residency.md) §2) |
| Patch currency | **Not currently current**: 16 open advisories across 9 packages, one critical — [../ops/security-review.md](../ops/security-review.md) §2–§4. The annual maintenance line in `docs/sales/pricing.md` §2 is what pays for closing them |
| Backups, and whether a restore has been rehearsed | Pre-deploy dumps (10 kept); a restore **has not been rehearsed** — [../ops/incident-response.md](../ops/incident-response.md) §6 |
| Audit log of vendor access | **No.** Server and application logs exist; there is no vendor-access trail |

4.2 The vendor applies the fix list in
[../ops/security-review.md](../ops/security-review.md) with the maintenance
cadence, and notifies the client of any advisory rated **critical** or **high**
within the window in §7.

### DECISION — the security annex

Not decided, and required before a large client signs: MFA (or a documented
compensating control such as an IP allow-list), disk encryption at the host, a
written patch-SLA in days, and whether a penetration test is purchased. Each of
those changes the price, so none of them may be promised in a DPA first.

## 5. Backups

5.1 Backups are taken as described in [data-residency.md](data-residency.md) §4:
a pre-deploy dump before every deployed release (10 kept) and, where the client
buys it, a nightly off-host snapshot.

5.2 A backup that contains the checkout contains `.env`, and therefore `APP_KEY`.
**A backup set is therefore a credential**, not merely a copy of data. It is
handled, stored and destroyed as one.

5.3 Restores are verified by restoring, never by file size
(`.agents/context/operations/database.md`).

5.4 **DECISION** — where the off-host backup lives (country), whether it is
encrypted with a key the client holds, and its retention window.

## 6. Sub-processors

Authorised today, and only when the corresponding switch is on:

| Sub-processor | Purpose | Enabled by |
| --- | --- | --- |
| The hosting provider of the instance | Runs the machine | Client-hosted: the client's choice, not a sub-processor of the vendor. Managed hosting: **DECISION** — unnamed, because no server is committed ([data-residency.md](data-residency.md) §1) |
| Cloudflare | Public ingress for an instance published by tunnel | Vendor configuration; used by the demo |
| The client's mail provider (IMAP / Sendgrid) | Receives mail into the instance | Client configuration |
| Google | Contacts sync | Client configuration |
| The Magic AI provider configured in the UI | Generates text from a document or note the user submits | Client configuration; off by default |
| The backup target | Stores the backup set | [data-residency.md](data-residency.md) §4 |

6.2 The vendor gives **30 days' notice** before adding a sub-processor that
processes client personal data, and the client may object on data-protection
grounds. **DECISION** — confirm 30 days is the intended notice period.

6.3 The vendor remains responsible for its sub-processors' performance of this
DPA.

## 7. Personal-data breach notification

7.1 The vendor notifies the client **without undue delay, and in any event within
72 hours** of becoming aware of a breach affecting the client's data. That clock
matches [../ops/incident-response.md](../ops/incident-response.md) §2, which is
the procedure that starts it.

7.2 The notification states: what happened, what data and how many records are
affected (as known at the time), what has been done to contain it, what the
client must do, and a named contact. Updates follow as facts are established — a
first notification that says "we do not yet know the scope" is honest and
expected; silence is not.

7.3 The vendor does not notify the client's data subjects or any authority on the
client's behalf unless instructed in writing. **DECISION** — who notifies the
Egyptian Data Protection Centre, and within what period, given the client is the
controller.

## 8. Return and deletion on termination

8.1 **On request, at any time, and free of charge:** the vendor provides the
client's data in a machine-readable form — a `mysqldump` of the database and the
`storage/app/public` directory, together with the instance's `APP_KEY`, because
without that key encrypted values are unreadable
(`.agents/context/operations/database.md`).

8.2 **Within 30 days of termination**, the vendor destroys every copy it holds:
the instance, its volumes, its checkout including `.env`, the pre-deploy dumps,
its snapshots in the backup set, and any copy on a laptop or staging area — and
confirms in writing. The procedure is written as executable steps in
[../ops/incident-response.md](../ops/incident-response.md) §7. **DECISION** —
confirm 30 days.

8.3 In the **default client-hosted mode** the vendor holds no copy of the CRM
data, so there is nothing to destroy beyond the backups it took and the support
correspondence. The client deletes its own instance.

8.4 Retention that survives termination: contract and invoice records, for as
long as Egyptian law requires. **DECISION** — the operator's accountant sets this.

## 9. Audit and cooperation

9.1 The vendor answers a written security questionnaire once per year, and
provides the current [../ops/security-review.md](../ops/security-review.md) on
request.

9.2 An on-site audit is not offered as a right. **DECISION** — if a large buyer
demands one, it is a priced engagement, and that must be decided before it is
conceded in a DPA.

9.3 The vendor assists the client with data-subject requests and with impact
assessments to the extent the system allows. The honest limits are in
[privacy-notice.md](privacy-notice.md) §5: exports exist for leads and persons
only; mail and activity history have no export.

## 10. Liability

Governed by [terms-of-service.md](terms-of-service.md) §9. A DPA liability cap
that is lower than the contract's cap is unenforceable in practice — the two must
be decided together.

## Annex — the filled-in values

| Field | Value |
| --- | --- |
| Client legal name | *(filled at signature — never pre-filled)* |
| Vendor legal name and registration | **DECISION** — not recorded in this repository |
| Instance hostname | *(filled at handover)* |
| Support contact (vendor) | *(filled at signature — see [../ops/support-sla.md](../ops/support-sla.md) §1)* |
| Support contact (client) | *(filled at signature)* |
| Mode bought | client-hosted / managed hosting |
| Backups in scope | pre-deploy dumps only / plus nightly off-host |
| Backup location and retention | **DECISION** |
| Effective date, term | *(filled at signature)* |

## Sources

- `docs/client-guide/_internal/facts.md` §2, §4, §5, §7
- `docs/client-guide/ar/16-limits-and-provider.md`
- `.agents/context/operations/deployment-homelab.md`,
  `.agents/context/operations/database.md`
- `docs/sales/pricing.md` §2 (maintenance covers updates and security patches)
- `docs/ops/security-review.md`, `docs/ops/incident-response.md` (this change)

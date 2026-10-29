# Privacy notice

> **Status:** draft for operator review — **not legal advice**, not yet reviewed
> by a lawyer, and not sent to any buyer.
> **Claims rule:** every statement about the system is traceable to a repository
> file, cited inline. Blocks marked **DECISION** are commitments the operator has
> not made; they must be resolved before this notice is published or attached to
> an offer.
> **Scope:** this notice is the *operator's* (the vendor's) notice. The client's
> own notice to its customers is a different document, and in the default
> deployment mode the client — not the operator — is the party responsible for
> the personal data inside the instance.

## Who is who

| Role | Who | What it means |
| --- | --- | --- |
| **The vendor** | The operator of Sijilna (the business selling and installing it) | Sees business contact details of the client's buying team, and — only if the client buys managed hosting or asks for support — whatever access the engagement grants |
| **The client** | The business that buys an installation | Decides what goes into the CRM and why. In the default client-hosted mode it is the party responsible for the personal data in its instance |
| **Data subjects** | The client's customers, leads, contacts, and its own staff who use the CRM | The people whose names, phone numbers, emails and message contents end up in the database |

In the default deployment the vendor **does not hold a copy of the client's CRM
data**. The database, the attachments and the `APP_KEY` live on the client's
server — see [data-residency.md](data-residency.md) §2. That single fact is what
makes this notice short.

## 1. What the vendor itself collects

| What | Why | Where it lives |
| --- | --- | --- |
| Business contact details of the people we talk to during a sale or a support engagement — name, role, business email, business phone | To answer, to quote, to deliver and to support | **DECISION** — no CRM of our own is recorded in this repository. Until one is chosen, say "in our own records" and name the tool when asked |
| Contract and invoice records | Legal and tax obligations in Egypt | **DECISION** — accounting records; the tool is not recorded here |
| Support correspondence — the problem reports and our replies | To fix the problem and to keep a shared record | The support mailbox in [../ops/support-sla.md](../ops/support-sla.md) §1 |

**The vendor does not collect:** analytics, advertising identifiers, browsing
behaviour on the client's instance, or usage telemetry. The product contains no
analytics or call-home code (§3 of the residency file).

## 2. What sits inside the client's instance

Everything the client enters: leads and their pipeline history, quotes and line
items, persons and organizations, products and stock, activities and notes, the
contents of the shared mailbox, uploaded files, users, roles and configuration.
Plus the server's own logs.

**The client decides what is entered, who can see it, and how long it stays.**
The product supplies the controls: ACL roles, and a data-visibility scope of
`global`, `group` or `individual` per user
(`docs/client-guide/_internal/facts.md` §2). It does **not** supply a retention
policy — nothing is deleted automatically, ever
(`docs/client-guide/ar/16-limits-and-provider.md`, "لا احتفاظ ولا حذف تلقائي").

## 3. Third parties that can see data

Each of these is a switch the client controls, and each one is off by default:

| Third party | What they see | When |
| --- | --- | --- |
| **Magic AI provider** | The text or document being processed | Only if the client enters an API key and enables it (`docs/client-guide/_internal/facts.md` §4) |
| **The client's mail provider** (IMAP / Sendgrid) | The shared mailbox's messages | As soon as the mailbox is connected (§4 of the same file) |
| **Google** | Contacts the client chooses to sync | Only if Google Contacts is connected |
| **Cloudflare** | Requests and responses in transit | Only if the instance is published through a Cloudflare Tunnel, as the vendor's demo is (`.agents/context/operations/deployment-homelab.md`, "Public ingress") |
| **The hosting provider** | The machine, and therefore the disks | Client-hosted: the client's own provider. Managed hosting: **DECISION**, see [data-residency.md](data-residency.md) §1 |
| **The backup target** | A full copy of the database, and (in the nightly set) `.env` with `APP_KEY` | Per the backup decision in [data-residency.md](data-residency.md) §4 |

There is no other recipient. Personal data is not sold and is not shared with
advertisers.

## 4. How long the vendor keeps things

| Record | Retention | Status |
| --- | --- | --- |
| Sales and support correspondence | **DECISION** — propose 24 months after the last contact, then deleted | Not decided |
| Contract and accounting records | **DECISION** — set by Egyptian tax and company-law requirements, not by us | Not decided |
| The client's CRM data | The client's decision, for as long as they run the instance | True today: nothing is auto-deleted |
| Backups | Per [data-residency.md](data-residency.md) §4 — pre-deploy dumps keep the 10 most recent; the nightly set's window is not recorded in this repository | Partly decided |
| Instance logs | Container log driver's window | Not recorded |

## 5. The client's rights, and who answers them

- **A person whose data is in the CRM** asks the client, not the vendor. The
  client holds the data and the administrator account. The vendor cannot read,
  correct or delete a record in a client-hosted instance without being let in.
- **The client asking the vendor** — for the vendor's own records (§1), or for
  help exercising a right — uses the support channel in
  [../ops/support-sla.md](../ops/support-sla.md) §1.
- **What the product can actually do for a request:** a record can be deleted or
  edited from the UI by an administrator, and a person cannot be deleted while
  linked to leads — the product refuses and says so
  (`docs/client-guide/_internal/facts.md` §3). An export exists for leads and
  persons only, not for mail or activities (same section). A deletion request
  against a mail archive or an activity history is therefore manual work, and it
  is done by the client's administrator on their own instance.

### DECISION — response time for rights requests

**Not decided.** Propose: acknowledge in 5 business days, act within 30 days,
both inside the business hours in [../ops/support-sla.md](../ops/support-sla.md)
§2. This becomes a contractual commitment in
[terms-of-service.md](terms-of-service.md), so it is the operator's call, not a
document's.

## 6. Security, in one honest paragraph

Passwords are stored hashed by the framework's default hashing; access to the
instance is by named account with ACL; login attempts are rate-limited to 5
failures per credential set and 20 per address per minute
(`docs/client-guide/_internal/facts.md` §7.1). There is **no multi-factor
authentication** in the product — a search of `packages/Webkul/`, `config/` and
`app/` finds none — no IP allow-list, and no session-device management. The
dependency audit of 2026-10-29 found 16 open advisories across 9 packages, one of
them critical, with the fix list in [../ops/security-review.md](../ops/security-review.md)
§4. Claiming "enterprise-grade security" would be false, and it is not claimed
here or anywhere else.

## 7. What is not claimed

- No certification: no ISO 27001, no SOC 2, no PCI DSS.
- No penetration test has been performed.
- No claim of compliance with Egypt's Personal Data Protection Law (Law 151 of
  2020). Legal review is an open item — [data-residency.md](data-residency.md) §5.
- No guarantee of a hosting location for managed hosting —
  [data-residency.md](data-residency.md) §1.

## Sources

- `docs/client-guide/_internal/facts.md` §2, §3, §4, §5, §7
- `docs/client-guide/ar/16-limits-and-provider.md`
- `.agents/context/operations/deployment-homelab.md`
- `.agents/context/operations/database.md`
- `docs/ops/security-review.md` (this change), `docs/legal/data-residency.md`

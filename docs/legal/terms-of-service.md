# Terms of service

> **Status:** draft for operator review — **not legal advice**, not reviewed by a
> lawyer, not signed with anyone.
> **Claims rule:** every statement about the product is traceable to a repository
> file, cited inline. Blocks marked **DECISION** are commercial or legal
> commitments only the operator can make. **Prices never appear here** — they
> appear in the signed order form, and `docs/sales/pricing.md` is the internal
> single source for them. A price quoted in this document would be a second
> source of truth and would drift.
> **Companion documents:** [dpa.md](dpa.md),
> [privacy-notice.md](privacy-notice.md), [data-residency.md](data-residency.md),
> [../ops/support-sla.md](../ops/support-sla.md).

## 1. What is being provided

1.1 The vendor provides **Sijilna**, a self-hosted, Arabic-first CRM and sales
register built on Krayin CRM (MIT licensed), installed on a server for the client.

1.2 **The licence is perpetual.** On payment of the licence fee the client owns
its installation and may keep running it indefinitely. What ends if maintenance
lapses is updates and support — **not the software**
(`docs/sales/pricing.md` §2).

1.3 **Setup and training are a one-time service per installation**: installation,
configuration, Arabic locale and currency, mail configuration, and initial
training of the client's team (same section).

1.4 **Annual maintenance is mandatory and year 1 is inside the first invoice** —
it is not free, it is included in what is invoiced. It covers updates, security
patches, upgrade assistance and support (same section).

1.5 **Managed hosting is optional and priced separately.** When bought, the
vendor operates the instance and the data sits on the vendor's server — with the
consequences in [data-residency.md](data-residency.md) §1.

1.6 **What the product is not.** It is a sales register: leads, pipeline, quotes
and follow-up. It has **no accounting, no general ledger, no payroll, no purchase
cycle, no financial statements, no e-invoicing and no connection to the Egyptian
Tax Authority.** That code does not exist in this product and no statement in any
meeting or message adds it (`docs/sales/pricing.md` §1;
`docs/client-guide/ar/16-limits-and-provider.md`).

## 2. The order form decides the commercial terms

2.1 The signed order form states: the tier, the number of users, the licence fee,
the setup fee, the annual maintenance fee, whether managed hosting is bought, the
add-ons, the payment schedule and the term dates.

2.2 The tier caps users — up to 5, up to 15, up to 40 multi-branch — and an extra
user above a cap is a one-time add-on that does not move the client into another
tier and does not change the maintenance fee
(`docs/sales/pricing.md` §3, §4).

2.3 **DECISION** — payment terms: deposit percentage, invoice date, due date, and
the consequence of late payment. Not recorded in this repository and not to be
invented in a contract. `docs/sales/pricing.md` §5 also records an open owner
decision on what happens to the data if a monthly-fallback client stops paying
after month 12 — resolve it here before offering that model.

## 3. The client's obligations

3.1 Provide a server that meets the published requirements, or buy managed
hosting. **DECISION** — the requirement list (vCPU, RAM, disk, PHP 8.3+, MySQL
8.4) exists in the deployment material but is not stated here as a contractual
minimum until the operator confirms the numbers.

3.2 Keep the instance patched — or keep maintenance current, which is how it gets
patched.

3.3 **Own the data decision.** The client decides what is entered and who may see
it. The product gives ACL roles and a per-user visibility scope
(`docs/client-guide/_internal/facts.md` §2) and **no automatic deletion of any
kind** (`docs/client-guide/ar/16-limits-and-provider.md`).

3.4 Keep its own backups if it does not buy them, and test one restore.

3.5 Provide its own outgoing mail server, inbound mailbox access, TLS
certificate and domain — these are provider services in the deployment
(§"ما يفعله المزوّد" in `docs/client-guide/ar/16-limits-and-provider.md`), not
product features.

## 4. What the client is told before signing — the honest limits

These are contractual disclosures, not marketing caveats. Each is true of the
product as delivered:

| Limit | Consequence |
| --- | --- |
| Quotes have **no status** | Acceptance is recorded by moving the lead to the won stage, not on the quote |
| **No scheduled reminders** | Nothing fires at an activity's scheduled time. Creating one adds in-app bell notifications for the users who can see it, editing one adds none, and an email goes out only if an administrator attached a mail action to an activity workflow |
| Quotes do **not touch inventory** | Stock is deducted manually at the actual sale |
| **One currency, one language** per installation | Not per user. The language is one for everyone, changed in Configuration → General; the currency is set at install and has no screen |
| **No tax configuration** | Tax is an amount typed into each quote line |
| **No SMTP screen** and **no invitation or set-password email** | The provider sets outgoing mail; the administrator sets each user's password. Adding a user mails only a one-line welcome notice |
| **No retention or auto-deletion** | Data grows until a human deletes it |
| **No multi-factor authentication** | [privacy-notice.md](privacy-notice.md) §6 |
| **Two shipped screens are not in the left menu** — Google Contacts and Web Forms | Both work; they are reached from **Settings → Other Settings**, or by direct URL |

The full enumeration is `docs/client-guide/ar/16-limits-and-provider.md`, which
is part of the delivery, and the client confirms having read it at signature.
**No verbal promise overrides it** — a feature that is not in that chapter is not
in the product, and a request for one is a priced add-on (see §5).

## 5. Changes and customisation

5.1 A custom module, a data migration from another system, an on-site training
day and a major-version upgrade are **priced add-ons**, quoted in writing before
work starts, never agreed verbally in a meeting
(`docs/sales/pricing.md` §4).

5.2 A custom module is a separate engagement from maintenance. It does not become
part of the maintained product unless that is written into the add-on.

5.3 Upstream updates: the fork tracks Krayin `2.2` and merges it into the product
line. The client is told when an upgrade needs a maintenance window, and agrees
the window (`docs/client-guide/ar/16-limits-and-provider.md`, "ورقة التسليم").

## 6. Support

6.1 The support channel, the hours, the severity definitions and the response
targets are exactly those in [../ops/support-sla.md](../ops/support-sla.md). That
document is incorporated by reference.

6.2 **Support is not 24/7 and this contract does not pretend otherwise.** It is
one operator, business hours, with the limits stated in that document.

6.3 The vendor is not responsible for a third-party service — the client's
hosting, mail provider, domain, TLS certificate or network.

## 7. Data, ownership and confidentiality

7.1 **The client owns its data.** The vendor claims no licence over it and uses
it for nothing except delivering the service.

7.2 On request the vendor provides a machine-readable export — a database dump
and the uploaded files, with the `APP_KEY` needed to read encrypted values
([dpa.md](dpa.md) §8.1).

7.3 Each party keeps the other's confidential information confidential, and uses
it only to perform this contract.

7.4 Personal data is handled per [dpa.md](dpa.md) and
[privacy-notice.md](privacy-notice.md).

## 8. Warranties and their limits

8.1 The vendor warrants that the software will perform materially as described in
`docs/client-guide/ar/16-limits-and-provider.md` and that the services are
delivered with reasonable skill and care.

8.2 The vendor does **not** warrant uninterrupted or error-free operation, and
does **not** warrant that the product meets a requirement that was not written
down (§4, §5).

8.3 Open-source components are provided under their own licences (MIT and
others). The vendor passes through no warranty it does not have.

8.4 **DECISION** — the warranty period (propose 30 days after go-live during
which defects in the delivered installation are fixed at no charge).

## 9. Liability

9.1 **DECISION — none of this may be signed until the operator decides it:**
the liability cap (a multiple of fees paid? a fixed figure?), whether
consequential and indirect loss is excluded, whether lost profits and lost data
are excluded, whether the cap is per-year or aggregate, and what is carved out as
non-excludable under Egyptian law.

9.2 What the document can already say: liability is not unlimited, it is capped
at a figure stated in the order form, and the DPA cap matches it
([dpa.md](dpa.md) §10).

9.3 Data loss: the client is told plainly that the vendor's recovery ability is
the backup set in [data-residency.md](data-residency.md) §4, and that a restore
from it had not been rehearsed as of this draft
([../ops/incident-response.md](../ops/incident-response.md) §6). Promising
recovery beyond the practised procedure is not offered.

## 10. Term, termination and what happens to the data

10.1 The licence is perpetual (§1.2). Maintenance and managed hosting run for the
term in the order form and renew annually unless either party gives notice —
**DECISION**: the notice period (propose 30 days before the renewal date).

10.2 Either party may terminate for material breach that is not cured within 30
days of written notice.

10.3 **On termination the client gets its data back and the vendor destroys its
copies** within 30 days, per [dpa.md](dpa.md) §8. In the default client-hosted
mode the client simply keeps its own instance; there is nothing to return and
nothing to destroy but backups.

10.4 **What is not promised:** that a lapsed maintenance agreement is
retroactively cheap to resume, or that the vendor will support a version the
client froze years earlier. **DECISION** — state the reinstatement rule, or state
plainly that there is none.

## 11. Governing law and disputes

11.1 **DECISION** — governing law (propose the laws of the Arab Republic of
Egypt), the courts or arbitration forum, the language of the contract (propose
Arabic as authoritative, English as a courtesy translation), and the escalation
step before litigation.

11.2 Nothing in this draft is a legal opinion. Both documents and the DPA need a
lawyer's review before first signature —
[data-residency.md](data-residency.md) §5.

## Annex A — the filled-in values

| Field | Value |
| --- | --- |
| Client legal name, address, registration | *(at signature)* |
| Vendor legal name, address, registration | **DECISION** |
| Tier and user count | *(at signature, per `docs/sales/pricing.md` §3)* |
| Fees, payment schedule | *(at signature — order form only)* |
| Managed hosting bought | yes / no |
| Support contact both ways | *(at signature, per [../ops/support-sla.md](../ops/support-sla.md) §1)* |
| Liability cap | **DECISION** |
| Governing law and forum | **DECISION** |
| Start date, term, notice period | **DECISION** |

## Sources

- `docs/sales/pricing.md` §1–§5 — model, tiers, add-ons, maintenance, opening
  decisions.
- `docs/client-guide/ar/16-limits-and-provider.md` — product limits, provider
  services, the handover sheet.
- `docs/client-guide/_internal/facts.md` §2, §5, §7.
- `docs/legal/dpa.md`, `docs/legal/data-residency.md`,
  `docs/ops/support-sla.md`, `docs/ops/incident-response.md`.

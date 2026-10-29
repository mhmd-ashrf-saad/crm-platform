# Legal and compliance — the documents a buyer asks for

Status: **drafts**. None of these has been reviewed by a lawyer, none has been
signed, and none may be sent to a client with an unresolved **DECISION** block
inside it. The checklist of those blocks is
[`../ops/decisions-to-confirm.md`](../ops/decisions-to-confirm.md).

## The five questions these documents answer

An Egyptian business buyer asks five things, in this order. One file answers each:

| The question | Document | The one-sentence answer |
| --- | --- | --- |
| **1. Where does our data live?** | [`data-residency.md`](data-residency.md) | On a server the client controls, in a named MySQL volume and a `storage/` directory on that machine — the vendor holds no copy outside the agreed backups |
| **2. Who can see it?** | [`data-residency.md`](data-residency.md) §3 | The client's own staff under ACL and per-user visibility scope, and the operator only through access the client grants; no telemetry, and the third-party paths (AI, mail, Google, Cloudflare) are switches the client controls |
| **3. How long do you keep it?** | [`privacy-notice.md`](privacy-notice.md) §4, [`dpa.md`](dpa.md) §5 | **Nothing is deleted automatically** — the product has no retention window, so retention is a written decision per record type plus the backup window |
| **4. What happens when the contract ends?** | [`dpa.md`](dpa.md) §8, [`../ops/incident-response.md`](../ops/incident-response.md) §7 | A machine-readable export on request (dump, files and the `APP_KEY` needed to read them), and destruction of every copy within the agreed window with a written certificate |
| **5. On what terms is this provided?** | [`terms-of-service.md`](terms-of-service.md) | Perpetual licence, paid setup, mandatory annual maintenance with year 1 inside the first invoice, hosting optional and separate — with the product's limits disclosed as contractual fact, not caveat |

## What is in this folder

| File | What it is |
| --- | --- |
| [`data-residency.md`](data-residency.md) | Where every category of data physically sits, both deployment modes, who can reach it, the three ways data can still leave, the backups, and what is genuinely unestablished about cross-border transfer |
| [`privacy-notice.md`](privacy-notice.md) | The vendor's notice: what the vendor collects itself, what sits in the client's instance, which third parties can see data and when, retention, data-subject rights and who answers them, and one honest security paragraph |
| [`dpa.md`](dpa.md) | Controller/processor split, instructions, security measures **as they actually are** (including what is absent), backups, sub-processors, 72-hour breach notification, return and deletion, audit, liability hook |
| [`terms-of-service.md`](terms-of-service.md) | Licence, setup, maintenance, what the product is not, the honest-limits disclosure table, customisation, support by reference, data ownership, warranties, liability, termination, and the annex of values filled at signature |

## Ground rules for anything written here

1. **A claim that is not traceable to the repository does not go in.** Every file
   ends with a `Sources` section. The primary sources are
   `docs/client-guide/_internal/facts.md`, `.agents/context/operations/`, and the
   code and config cited inline.
2. **A commitment the operator has not made is a `DECISION` block**, never a
   default and never a plausible-sounding sentence. Twenty-six of them are listed
   in [`../ops/decisions-to-confirm.md`](../ops/decisions-to-confirm.md).
3. **No price ever appears here.** `docs/sales/pricing.md` is the single source for
   money; contracts carry the numbers in a signed order form. A price restated in
   a legal document is a second source of truth that will drift.
4. **No client name, no credential, no real domain password** is written into any
   of these files. The annexes are blank by design and are filled at signature.
5. **No compliance claim.** No ISO 27001, no SOC 2, no penetration test, and no
   assertion of compliance with Egypt's PDPL (Law 151 of 2020) — that review is an
   open decision (D4).
6. **Ship the limits with the contract.** The disclosure table in
   [`terms-of-service.md`](terms-of-service.md) §4 exists because the alternative
   is a client discovering them in month two. Chapter 16 of the Arabic manual
   (`docs/client-guide/ar/16-limits-and-provider.md`) is the full list and it is
   part of the delivery.

## The two sentences that are true today

- "Your data is on your server, and we hold no copy of it outside the backups you
  agree to."
- "We have not committed to a hosting location for a server we operate, so we will
  not sign a residency clause until we have."

## Related

- [`../ops/support-sla.md`](../ops/support-sla.md) — the support commitment the
  terms incorporate by reference.
- [`../ops/security-review.md`](../ops/security-review.md) — the evidence behind
  every security sentence here.
- `docs/sales/pricing.md` — the commercial model these terms describe.
- `AGENTS.md` — the hard rules on secrets, databases and destructive commands.

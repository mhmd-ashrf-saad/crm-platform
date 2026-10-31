# Decisions to confirm before anything is sent to a client

Every document in `docs/legal/` and `docs/ops/` marks commitments the operator has
not made with a **DECISION** block. This is the single checklist of them, with
what closing each one unlocks. **None of these may be filled in by an agent** —
each is a legal, commercial or operational commitment that only the owner can
make.

Status column: `open` until the owner decides, then the date and where it was
written.

| # | Decision | Where it is stated | What it blocks | Status |
| --- | --- | --- | --- | --- |
| D1 | **Physical location of the server that will hold client data** in managed-hosting mode — provider, city, country | `legal/data-residency.md` §1 | Any managed-hosting contract and any residency clause. Also the DPA sub-processor table | open |
| D2 | **Vendor access control** — a named support administrator per client, disclosed use, disabled at the end | `legal/data-residency.md` §3 | The DPA's "support access is an instruction" clause being true rather than aspirational | open |
| D3 | **Backup policy per client** — frequency, off-host location and country, retention, whether the client holds the encryption key, who may restore | `legal/data-residency.md` §4, `legal/dpa.md` §5.4 | Every backup promise in the DPA and the terms; the nightly set's window is not even recorded in the repo today | open |
| D4 | **Legal review and controller/processor registration** under Egyptian PDPL (Law 151/2020) | `legal/data-residency.md` §5 | Any compliance statement. Until it exists the only true sentence is "legal review in progress" | open |
| D5 | **Support mailbox and severity-1 phone number**, plus what a client does if nobody answers for a business day | `ops/support-sla.md` §1 | Sharing the SLA at all — a blank contact field reads as a promise | open |
| D6 | **Support hours** — working days, start/end, Ramadan schedule, how holidays are announced | `ops/support-sla.md` §2 | The response targets, which are measured inside these hours | open |
| D7 | **Whether an out-of-hours option exists** and what it costs | `ops/support-sla.md` §2 | Any answer to "what if it breaks at night" | open |
| D8 | **SLA posture** — targets without credits, or a priced SLA with credits | `ops/support-sla.md` §3 | The severity table's contractual standing | open |
| D9 | **Ticketing system** — `bd`, or a shared mailbox with a fixed subject convention | `ops/support-sla.md` §7 | Giving a client a ticket id to quote | open |
| D10 | **Warranty period** after go-live (propose 30 days) | `legal/terms-of-service.md` §8.4 | Signing the terms | open |
| D11 | **Liability cap**, exclusions, per-year vs aggregate, non-excludable carve-outs | `legal/terms-of-service.md` §9.1, `legal/dpa.md` §10 | Signing anything. The DPA cap and the contract cap must be the same number | open |
| D12 | **Payment terms** — deposit, invoice date, due date, late-payment consequence | `legal/terms-of-service.md` §2.3 | The order form | open |
| D13 | **What happens to the data of a monthly-fallback client who stops paying after month 12** | `docs/sales/pricing.md` §5 (existing owner TODO), `legal/terms-of-service.md` §2.3 | Offering the monthly model in writing | open |
| D14 | **Notice period** for non-renewal of maintenance or managed hosting | `legal/terms-of-service.md` §10.1 | The renewal clause | open |
| D15 | **Reinstatement rule** after lapsed maintenance, if any | `legal/terms-of-service.md` §10.4 | The honest answer to "we'll come back next year" | open |
| D16 | **Governing law, forum, contract language, escalation step** | `legal/terms-of-service.md` §11.1 | Signing | open |
| D17 | **Vendor legal name, registration and address** | `legal/dpa.md` Annex, `legal/terms-of-service.md` Annex A | Any signature block | open |
| D18 | **Whether an on-site audit right is conceded** to a large buyer, and at what price | `legal/dpa.md` §9.2 | Enterprise DPA negotiation | open |
| D19 | **Who notifies the Egyptian Data Protection Centre** on a breach, and in what period | `legal/dpa.md` §7.3 | The breach clause | open |
| D20 | **Retention of sales/support correspondence and of accounting records** | `legal/privacy-notice.md` §4 | The privacy notice | open |
| D21 | **Rights-request response times** (propose: acknowledge 5 business days, act within 30) | `legal/privacy-notice.md` §5 | The privacy notice and the terms | open |
| D22 | **Sub-processor change notice period** (propose 30 days) | `legal/dpa.md` §6.2 | The sub-processor clause | open |
| D23 | **Deletion window on termination** (propose 30 days) and the certificate format | `legal/dpa.md` §8.2, `ops/incident-response.md` §7 | The termination clause | open |
| D24 | **Whether disk-level encryption is applied at the host**, and by whom | `legal/dpa.md` §4 | The security annex | open |
| D25 | **Security annex for large clients** — MFA or a compensating control, patch SLA in days, whether a penetration test is bought | `legal/dpa.md` §4 | Any buyer with a security questionnaire. Each item changes the price | open |
| D26 | **The server requirement list as a contractual minimum** — vCPU, RAM, disk, PHP 8.3+, MySQL 8.4 | `legal/terms-of-service.md` §3.1 | A client-hosted contract; the numbers exist in the deployment material but are not stated as a minimum | open |
| D27 | **Where does the off-host recovery copy of each client instance's `.env` and `APP_KEY` live, and is it encrypted?** Getting this wrong is the one loss with no recovery: a dump restored without the matching `APP_KEY` is a site that boots and shows garbage. Custody of the key is a different decision from the backup set that carries it | [`secrets-handling.md`](secrets-handling.md) §1e (DECISION) | D3, which is not honest if nobody holds the key that makes the backup readable | open |
| D28 | **Is `APP_KEY` ever rotated on a client instance, and on what trigger — a leak only, or a schedule?** There is no re-encryption command in this repository, so a rotation destroys every encrypted column unless the old key is kept: the Google OAuth tokens survive as rows and are unrecoverable, and every user is logged out. A scheduled policy without a re-encryption step is a promise the code cannot keep; the honest choice today is leak-only | [`secrets-handling.md`](secrets-handling.md) §3c (DECISION) | Any written rotation schedule, and the incident-response answer to "was the key rotated?" | open |
| D29 | **Does a client instance keep a usable `DB_ROOT_PASSWORD` after initialisation, or is it rotated away to a value held only in the operator's store rather than in `.env`?** Both are defensible and the repository records neither. Rotating it off the instance removes a standing privilege from a web-facing host; keeping it is what the documented restore path actually needs | [`secrets-handling.md`](secrets-handling.md) §3d (DECISION) | The restore procedure (`incident-response.md` §5) and the backup runbook, which both read that password | open |
| D30 | **Which SMTP provider and which sending domain does each client instance use, and who owns the account — the vendor or the client?** The deliverability of every lead notification rides on that domain's reputation, and an account owned by the wrong party is one a client cannot revoke and the vendor cannot hand over | [`secrets-handling.md`](secrets-handling.md) §3e (DECISION) | The mail section of the handover sheet, and the claim that the client's accounts are the client's | open |
| D31 | **What monitored inbound mailbox address does each client instance use?** Inbound mail fails silently when the credential stops working: with no named address there is no answer to "where did the email go" and nobody to notice it stopped | [`secrets-handling.md`](secrets-handling.md) §3f (DECISION) | Any inbound-mail statement in the client manual, and the per-client handover sheet | open |
| D32 | **Does the operator retain a client's administrator password after handover, or is it changed to one only the client holds?** Retaining it is remote access nobody granted; surrendering it means the vendor cannot support the instance without being given access each time. Whatever is chosen must be the same answer in the DPA's support-access clause | [`secrets-handling.md`](secrets-handling.md) §3h (DECISION) | `legal/data-residency.md` §3 and the DPA clause that says support access is an instruction, not a default | open |
| D33 | **Where are each client's secret values stored at all** — a named password manager, an encrypted file, a hardware token? Nothing in this repository records a store, so every rotation instruction ends at "put it somewhere safe", which is not an instruction | [`secrets-handling.md`](secrets-handling.md) §4c (DECISION) | Every rotation and leak-response procedure; D27, D29, D30 and D32 all assume an answer | open |
| D34 | **Which `.env` mode and ownership is authoritative for a new instance, and which is the existing live instance meant to be on — `640` owner `deploy-user` group `33`, or `600` owner `uid 33`?** Two sets of documents describe the one file that holds every secret and both cannot be right: `deploy/README.md` (steps 3–4) and `deploy/provision-instance.sh` (`harden_env_file`) provision mode `640` owner `deploy-user` group `33`, while `.agents/context/operations/deployment-homelab.md` (the env-file row and its one-time fix) and `ops/security-review.md` F15 record the live instance as mode `600` owned by uid `33`. The contradiction is recorded here, not resolved: an instance provisioned to the wrong one either restart-loops because PHP cannot read the file, or leaves it readable by a group that does not need it | `deploy/README.md`, `deploy/provision-instance.sh`, `.agents/context/operations/deployment-homelab.md`, `ops/security-review.md` F15 | Provisioning any second client instance, and any security questionnaire that asks how the secret file is protected | open |

D27–D34 were added with the secrets-handling runbook and are each awaiting an
operator decision — no agent may answer them. D34 is a documented contradiction
rather than a missing value: resolving it needs the operator's choice of
authoritative document, after which the four documents have to be reconciled by
hand.

## Decisions that are *not* the owner's — they are engineering findings

These need a bead, not a signature. They are listed in
[security-review.md](security-review.md) §4 items 6–11: security headers, the
session cookie flag, the `.env.example` debug trap, the unvalidated `file`
attribute upload, the missing ACL guards on mass delete and export, and the
unused Sanctum endpoint.

## Already decided — do not re-litigate

Recorded so this file does not reopen settled ground:

- The commercial model: perpetual licence + paid setup + **mandatory** annual
  maintenance, managed hosting optional (`docs/sales/pricing.md` §2).
- Hosting is **not** the default: it was rejected because it contradicts the claim
  that the database sits on the client's server.
- The product is a sales register, not accounting: no e-invoicing, no tax
  authority integration (`docs/sales/pricing.md` §1).
- The restore rehearsal is **outstanding**, and until it is done no restore time is
  quoted to a client (`ops/incident-response.md` §6).

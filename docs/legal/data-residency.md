# Where the client's data actually sits

> **Status:** draft for operator review — **not legal advice**, and not yet sent
> to any buyer.
> **Claims rule:** every statement below about the system is traceable to a file
> in this repository, cited inline (paths relative to the repo root). Anything
> the operator has not decided is marked **DECISION** — those blocks are the
> difference between a document we can hand over and one we cannot.
> **Audience:** the Egyptian business buyer who asks, in this order: where does
> our data live, who can see it, how long do you keep it, what happens when the
> contract ends, and on what terms. This file answers the first two; the other
> three are in [privacy-notice.md](privacy-notice.md),
> [dpa.md](dpa.md) and [terms-of-service.md](terms-of-service.md).

## The one sentence that is true

**Sijilna is installed on a server the client controls; the CRM database, the
uploaded files and the `APP_KEY` that decrypts them live on that server's disk,
and the only copies that leave it are the backups described in §4.**

That sentence is the product claim, and it is also the reason the hosted
per-seat subscription model was rejected: it would have made us the operator of
every client's server, which contradicts the claim
(`docs/sales/pricing.md` §2, the rejected-models table).

It is true **only in the default deployment mode**. The two modes are not the
same product from a data-protection point of view, so they are separated below.

## 1. Two deployment modes, two answers

| Mode | Who holds the data | What the buyer is told |
| --- | --- | --- |
| **Client-hosted (default)** | The client. The instance runs on a server the client pays for and controls; we install it, hand over the administrator account and leave. | "Your data is on your server. We hold no copy of it except the backups you agree to in §4, and you can revoke our access at any time." |
| **Managed hosting (optional add-on)** | The operator. The identical stack runs on a server the operator pays for, and the client reaches it over HTTPS. | "Your data is on a server we operate in `<country/region>`; here is who can reach it and here is what happens if you leave." |

Managed hosting is an optional line in the commercial model
(`docs/sales/pricing.md` §2), not the default. **A buyer's residency question
must be answered for the mode they are actually buying** — answering the
client-hosted answer while selling managed hosting is a misrepresentation.

### DECISION — the only server that currently runs this stack

The only instance of this product that exists today is the operator's **demo**
at `https://crm.mohamedlabs.com` (`deploy/.env.deploy.example`), and it is not a
hosting offer:

- it runs on the operator's **desktop**, under WSL2 Ubuntu with RAM capped at
  4 GB, reached as `ssh homelab`
  (`.agents/context/operations/deployment-homelab.md`);
- its three containers use about 470 MB, leaving ~1.9 GB
  (same file, "Memory budget");
- a **nightly copy leaves the machine** at 03:30 to a laptop (same file, "Open
  risks" #1).

**Not decided:** the physical location, hosting provider and country of the
server(s) that will hold *client* data in managed-hosting mode. A desktop in a
home or office is not a data-centre commitment and must not be presented as one.

**What would make it true:** one named server (provider, city, country), written
into this section, with the same for the backup target in §4. Until then, the
managed-hosting answer to "where does our data live" is *"we have not committed
to a location, and we will not sign a residency clause until we have."*

## 2. Exactly what is stored, and where

Per instance, in both modes. All paths are inside the instance's checkout
(`/opt/<project>/app` on the demo,
`.agents/context/operations/deployment-homelab.md`).

| Data | Physical location | Notes |
| --- | --- | --- |
| **All CRM records** — leads, pipeline stages, quotes and their line items, persons, organizations, products, warehouses, activities, tags, users, roles, ACL, configuration | MySQL 8.4, in the **named Docker volume** `sijilna-db-data` mounted at `/var/lib/mysql` on the host disk | A named volume, never a bind mount to `storage/` — `deploy/docker-compose.yml`, recorded in `.agents/context/operations/deployment-homelab.md`. 61 tables on a completed install |
| **Mail bodies and imported mail** | Same database | The product has no retention or pruning on mail (`docs/client-guide/ar/16-limits-and-provider.md`, "لا احتفاظ ولا حذف تلقائي") |
| **Attachments, uploaded files, image attributes** | `storage/app/public/` on the host filesystem, served as `/storage/` | Served by nginx through an alias (`deploy/docker/nginx.conf`, `location ^~ /storage/`) |
| **`APP_KEY`, database passwords, SMTP/IMAP credentials** | The instance's `.env` in the checkout root, mode `600`, owned by uid 33 (`www-data`) | `.agents/context/operations/deployment-homelab.md`, "First-boot failure that actually happened". Never committed: `.env` is gitignored |
| **Application logs** | `storage/logs/` and the containers' stdout | |
| **Request traffic** | nginx access/error logs to stdout (`deploy/docker/nginx.conf`) | Only as long as the container's log driver keeps it |

Two consequences that belong in any honest privacy conversation:

1. **The key sits next to the data.** Encrypted columns are only readable with
   `APP_KEY`, and `APP_KEY` lives in the same checkout on the same disk. A
   stolen database dump alone does not reveal the encrypted values — but a copy
   of the checkout plus the dump does. This is why the backup set in §4 is
   treated as a credential, not as a file.
2. **Nothing is deleted for you.** The product has no automatic deletion, no
   archive, no retention window (`docs/client-guide/ar/16-limits-and-provider.md`).
   What exists is what was entered; growth is unbounded until a human deletes it.

## 3. Who can reach it

| Who | How, and under what limits |
| --- | --- |
| **The client's own staff** | Administrator accounts on the instance. Every sidebar item is filtered by ACL, so a role without the key does not see the item; data visibility is a separate axis — `global`, `group` or `individual` — and a user can be limited to records they created (`docs/client-guide/_internal/facts.md` §2). The primary administrator (id 1) cannot be demoted or disabled from the UI (same section) |
| **The operator** | Only with a route into the machine: root/SSH on the client's server (client-hosted), or administrative operation of it (managed hosting). **There is no separate, restricted, audited vendor account.** Day-to-day support is done either through a client administrator account the client creates, or through the host shell |
| **The hosting provider / whoever owns the physical machine** | Client-hosted: the client's own hosting relationship. Managed hosting: the operator's — **DECISION**, see §1 |
| **Cloudflare, if a tunnel is used** | On the demo, the public hostname is a **Cloudflare Tunnel** route and Cloudflare terminates TLS; the tunnel forwards plain HTTP to the container, which is why `APP_FORCE_HTTPS=true` exists (`.agents/context/operations/deployment-homelab.md`, "Public ingress"). Request and response bodies therefore pass through Cloudflare's network |
| **Nobody else** | There is no product telemetry, no analytics SDK and no call-home. `composer.json` contains no analytics package, and no code in `packages/Webkul/**` or `app/` posts usage data anywhere |

### The three ways data can still leave the instance

These are configuration switches, not defaults, and each one is the client's
decision to make with open eyes:

1. **Magic AI** — enabling it stores an API key and model in the UI and sends
   the document or text being processed to that provider to generate a lead
   description (`docs/client-guide/_internal/facts.md` §4, "الذكاء الاصطناعي
   السحري"). **If it is enabled, content leaves to a third-party AI provider.**
   It is off until someone turns it on.
2. **IMAP mailbox** — the inbound mailbox is an external mail provider's server;
   the instance pulls from it (same section, "حساب IMAP"). Google Contacts sync
   is the same shape, and needs the client's Google authorisation
   (`docs/client-guide/_internal/facts.md` §2, Google Contacts screen).
3. **Web forms** — a public endpoint accepts submissions from anyone with the
   form URL (`packages/Webkul/WebForm/src/Routes/routes.php`), and the product
   ships **no spam protection** (`docs/client-guide/_internal/facts.md` §4).

### DECISION — vendor access needs a control that does not exist yet

Today the operator's only access path is "be root on the box" or "hold a client
administrator login". Neither is logged as vendor access, and neither can be
time-limited by the client. **Proposed default for the terms:** the client
creates a named administrator account for support, the client is told whenever it
is used, and the client disables it when the engagement ends.
**What would make it real:** that account exists per client, and the support
contact is named in the handover sheet
(`docs/client-guide/ar/16-limits-and-provider.md`, "ورقة التسليم").

## 4. Backups — where the second copy goes

| Backup | Where it lands | How long it is kept | Evidence |
| --- | --- | --- | --- |
| **Pre-deploy dump** — a `mysqldump` taken before every deployed release, with rollback if the health gate fails | On the instance's host | **10 most recent** | `.agents/context/operations/deployment-homelab.md`, "Deploying"; `docs/client-guide/_internal/facts.md` §5 |
| **Nightly restic snapshot** — the checkout (including `.env`) **and** a `mysqldump`, staged at `/var/lib/homelab-staging/sijilna/` | The operator's homelab staging area, the restic repository, and an **03:30 copy to a laptop** | Governed by the restic policy on that host — **not recorded in this repository** | `.agents/context/operations/deployment-homelab.md`, "Open risks" #1 |
| **Client-hosted instances** | Only the pre-deploy dump exists by default. There is **no documented nightly off-host backup for a client instance** in this repository | 10 dumps | `deploy/deploy.sh` |

Three statements that must be made to every buyer, because each one is a real
limitation rather than a caveat:

- **A Docker volume is not a backup.** It lives on the same disk as the
  container and disappears with it (`.agents/context/operations/database.md`).
- **A snapshot that holds `.env` is both the data and the key.** Treat a lost or
  leaked repository copy as a credential compromise, not just a data leak
  (`.agents/context/operations/deployment-homelab.md`, "Open risks" #1).
- **A backup is verified by restoring it, never by its file size**
  (`.agents/context/operations/database.md`, "Backup and restore").

### DECISION — backup policy per client instance

Not decided, and not decidable from this repository: backup frequency, where the
off-host copy lives (which country), how long it is retained, whether it is
encrypted at rest with a key the client holds, and who can restore it.

**What would make it true:** one row per client in
`docs/ops/support-sla.md` §"Per-client commitments", filled at handover, plus a
successful restore rehearsal as required by
[../ops/incident-response.md](../ops/incident-response.md) §6. Until a restore
has been rehearsed, no backup claim is made to a client.

## 5. Cross-border transfer — the part that is genuinely unestablished

The product itself moves nothing across a border. The paths that *can* are
Cloudflare (tunnel ingress), an external AI provider (Magic AI), an external mail
provider (IMAP/Sendgrid), Google (Contacts), and the backup target in §4 — each
listed above with the switch that enables it.

Whether the operator must register as a data controller or processor, whether a
managed-hosting offer needs a cross-border transfer approval, and what the
contracts must say are **legal questions this repository cannot answer**. Egypt's
Personal Data Protection Law (Law 151 of 2020) is the frame the buyer's
procurement question comes from; nothing in this repository has been reviewed
against it.

### DECISION — legal review before the first contract

**Needed:** a lawyer's review of [privacy-notice.md](privacy-notice.md),
[dpa.md](dpa.md) and [terms-of-service.md](terms-of-service.md), and a written
answer on controller/processor registration. **No claim of PDPL compliance may be
made until that review exists.** The correct sentence to a buyer today is: "here
is what the system does with your data, here is what we have decided, and legal
review is in progress" — never "we are compliant".

## 6. What this file does not claim

- It does not claim a hosting location. §1 is a decision, not a fact.
- It does not claim a retention period, an encryption-at-rest guarantee, a
  certification (no ISO 27001, no SOC 2), or a penetration test.
- It does not claim PDPL compliance or a legal opinion.
- It does not claim the demo instance is representative of what a client buys.

## Sources

- `.agents/context/operations/deployment-homelab.md` — containers, named volume,
  published port, `.env` ownership, tunnel ingress, backups, open risks.
- `.agents/context/operations/database.md` — volume, dumps, restore verification.
- `.agents/context/operations/environment-and-deployment.md` — secrets policy.
- `docs/client-guide/_internal/facts.md` §2, §4, §5 — ACL and data scope, Magic
  AI and IMAP, retention and scheduled tasks.
- `docs/client-guide/ar/16-limits-and-provider.md` — what the product does not
  do, and what stays with the provider.
- `docs/sales/pricing.md` §2 — why hosting is not the default model.
- `deploy/docker/nginx.conf`, `deploy/docker-compose.yml`,
  `deploy/.env.deploy.example`, `deploy/deploy.sh`.

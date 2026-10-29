# Support, SLA and how to ask for help

> **Status:** draft for operator review. Shareable with a client **only after the
> `DECISION` blocks are filled in** — an SLA with a blank in it reads as a
> promise the operator cannot keep.
> **The one design constraint:** this is a **one-operator business**. Every target
> below is chosen so that one person can actually meet it, in business hours, on
> a normal week. Nothing here is 24/7 and nothing here promises a fix time it
> cannot control.
> **Companions:** [incident-response.md](incident-response.md) (what happens when
> it is bad), [security-review.md](security-review.md),
> [../legal/terms-of-service.md](../legal/terms-of-service.md) §6.

## 1. How to reach support

| Channel | Address | Hours |
| --- | --- | --- |
| **Email — the primary channel** | **DECISION** — the support mailbox address is not recorded anywhere in this repository and must not be invented here | Read on business days |
| **Phone / WhatsApp — for a severity 1 only** | **DECISION** — number is not recorded in this repository | Business hours |
| Anything else (a colleague's personal number, a group chat) | Not a support channel. A request sent there is not tracked and has no clock | — |

**Why email is primary:** it is the only channel that leaves a timestamped record
both sides can read. A phone call is how a severity 1 starts; the ticket is
opened by the email that follows it.

### DECISION — the two contact values, and the fallback

Fill in: (a) the support mailbox, (b) the severity-1 phone number, (c) what a
client does when the operator does not answer for more than one business day —
propose a named second contact, or an explicit written statement that there is
none. **A client must never be left believing there is coverage that does not
exist.**

## 2. Hours

**Business hours: Sunday to Thursday, 09:00–17:00 Africa/Cairo**, excluding
Egyptian public holidays. Friday and Saturday are not covered.

This is the Egyptian working week and it is the only window in which a response
target is measured. A message that arrives on Thursday at 18:00 has its clock
started on Sunday at 09:00 — that is stated to the client in writing rather than
discovered during an incident.

### DECISION — confirm the hours

Not recorded anywhere in the repository. Confirm: the actual working days, the
actual start and end, whether a Ramadan schedule applies, and how holidays are
announced to clients (propose: a one-line email at least 7 days ahead).

### What "hours" does not mean

- It does not mean the instance is monitored only in those hours. Uptime
  monitoring runs continuously and alerts the operator — on the vendor's own
  instance, Uptime Kuma checks `https://crm.mohamedlabs.com/admin/login` every
  60 seconds with zero retries
  (`.agents/context/operations/deployment-homelab.md`, "Open risks" #2). A client
  instance gets the same treatment only if it is written into the handover sheet.
- It does not mean the operator is awake at 03:00. A severity 1 at 03:00 is
  handled at the start of business hours unless the client bought the out-of-hours
  option — **DECISION**: whether such an option exists and what it costs. Do not
  offer it informally.

## 3. Severity, response and resolution targets

The distinction that matters: **response** is when a human acknowledges and takes
ownership. **Resolution** is when the client's work is possible again. They are
separate columns because conflating them is how SLAs get broken.

| Sev | Definition | Response target | Workaround or resolution target |
| --- | --- | --- | --- |
| **1 — Critical** | The instance is down, unusable for everyone, or data loss is suspected or observed. Nobody can log in; the database is unreachable; records have disappeared | **4 business hours** from the client's email (1 hour if the number is called during business hours) | **1 business day** to a working state or a documented workaround. **No fix-time guarantee** — see §5 |
| **2 — Major** | A core workflow is broken for many users: quotes cannot be created or sent, the pipeline cannot be moved, mail does not arrive, imports fail | **1 business day** | **3 business days** to a fix or a workaround |
| **3 — Normal** | One user, one screen, one report; a wrong number; a permission that will not stick; a question about how something works | **2 business days** | Best effort, in the order received; typically the next maintenance window |
| **4 — Change request** | Not a defect: a configuration change, a new field, a custom module, a data migration, a training session | **3 business days** to a written answer with a price and a date, not a fix | Scheduled and priced per [../legal/terms-of-service.md](../legal/terms-of-service.md) §5 |

**These targets are targets, not guarantees, and no credit or penalty attaches to
missing one.** A one-operator business that writes service credits into a contract
has sold insurance it cannot underwrite. **DECISION** — confirm this posture, or
price an SLA that carries credits.

### How the clock stops

The clock pauses while: the vendor is waiting on the client for information,
access or a decision; the cause is a third-party service (hosting, mail provider,
domain, TLS, network); or the client's own environment prevents work. The pause is
stated in the reply, with what is needed to restart it.

## 4. What is in scope, and what is not

### In scope, under annual maintenance

Whatever is in the maintenance line and nothing beyond it — updates, security
patches, upgrade assistance and support (`docs/sales/pricing.md` §2):

- Diagnosing and fixing defects in the delivered installation.
- Restoring service after an outage caused by the software or by our operation.
- Restoring from a backup we hold, per
  [incident-response.md](incident-response.md) §5.
- Answering "how does this work" questions with a pointer to the relevant chapter
  of the Arabic manual (`docs/client-guide/ar/`).
- Applying security updates for the components in
  [security-review.md](security-review.md), on the maintenance cadence.

### Not in scope — say so in one sentence and stop

| Out of scope | Why, and what is offered instead |
| --- | --- |
| **The client's own network, devices, browsers, printers** | Not ours. Offer a 15-minute call to confirm it is not our side, then hand it back |
| **Third-party services**: hosting, IMAP/SMTP provider, domain, TLS certificate, Google, Cloudflare | We can see the error and name it; we cannot change their system. §4a |
| **The client's mail deliverability** | Spam filtering, SPF/DKIM/DMARC at the client's provider, and "why did my email go to junk" belong to whoever runs the mail service |
| **Data entry correction in bulk** | Manual work becomes a priced engagement |
| **Training beyond what the setup fee included** | An on-site training day is an add-on (`docs/sales/pricing.md` §4) |
| **Custom features, changes to the shipped screens, new reports** | Add-on, quoted in writing (§4 of the pricing file) |
| **Reading the client's business data for them** — "how many deals did we close in Q3" | The client has the dashboard; we do not act as their analyst |
| **Recovering data that was deleted by a user and is older than the retained backups** | The recovery window equals the backup retention in [../legal/data-residency.md](../legal/data-residency.md) §4. Say the window, not "we'll try" |
| **Anything requiring access the client will not grant** | Access is granted per request — [../legal/data-residency.md](../legal/data-residency.md) §3 |

### 4a — The two failures that look like nothing

State these to every client at handover, because they produce **no error
anywhere**: **scheduled tasks** and **the queue**. Without `schedule:run`
running, no inbound mail arrives and no campaign ever sends, and every screen
still looks healthy (`docs/client-guide/ar/16-limits-and-provider.md`, the
warning in "ما يفعله المزوّد"). The first support question for "we get no email"
is "is the scheduler running", not "is IMAP configured".

## 5. What the SLA deliberately does not promise

- **No 24/7 cover.** Not on any tier. Stated in §2.
- **No uptime percentage.** Uptime depends on the client's hosting, network and
  the operator being one person. If a buyer needs a number with credits attached,
  that is a decision to price, not to sign.
- **No fix-time guarantee on software defects.** The targets are for a working
  state or a documented workaround; a defect that needs a code change is a
  release, and releases go through the pipeline in
  `.agents/context/operations/deployment-homelab.md` ("Deploying").
- **No guarantee of recovery beyond the retained backups.** A backup retention
  window is a promise; "we will find a way" is not one.
- **No promise about a version the client froze.** If maintenance lapsed and the
  client kept running an older release, the fix arrives in a supported release.

## 6. How a client reports a problem (the template)

A report that contains all eight lines gets a diagnosis on the first reply. A
report that says "it doesn't work" costs a day of questions.

```text
SUBJECT: [Sev?] Short description — screen — who

1. Instance URL            : https://...
2. Who is affected         : one user (email) / several users / everyone
3. Screen and URL          : e.g. Quotes → view, /admin/quotes/view/12
4. What you did            : the clicks that led to it, in order
5. What you expected       : one sentence
6. What happened instead   : the exact message on screen, or "nothing"
7. When it started         : date and local time, and whether it ever worked
8. Anything you attached   : a screenshot of the whole screen, the record id,
                             the email you tried to send
```

Three questions the client can answer that save the most time:

- **Is it one person or everyone?** One person is usually permissions or their own
  browser; everyone is usually the service.
- **Did it ever work?** "It worked yesterday" and "it has never worked" are
  different bugs.
- **Is data missing, or is the screen empty?** An empty list and a lost record look
  the same and are not. If data may be missing, say **"possible data loss"** in
  the subject and it is treated as severity 1 immediately.

**Do not send credentials, `APP_KEY`, `.env` contents or a database dump** through
the support channel. Refer to a key by name and the operator will retrieve it.

## 7. What the operator does on receipt

1. **Classify** with §3, and reply with the severity and the target in the first
   line of the reply. Re-classifying later is normal; silent re-classifying is not.
2. **Open a ticket** (bead) so the request has an id both sides can quote.
3. **Reproduce** on a copy, not on the client's instance. §8.
4. **Tell the client what happens next and when**, even when the answer is "I do
   not know yet, I will know by Thursday 12:00".
5. **Close with the cause in one sentence**, in plain Arabic or English — not
   "resolved".

### DECISION — ticketing

The repository has no ticketing system. Propose `bd` (already used for this
project's own work) or a shared mailbox with a fixed subject convention, and write
the choice here. A client should be told which one, and given the id format.

## 8. Working on a client instance without breaking it

- **Never reproduce on live data.** Use a copy: a dump restored into a scratch
  database, never the production one.
- **Never run `migrate:fresh`, `db:wipe` or a prune** on a client instance.
  `docker volume prune` and `docker system prune` destroy the client's database
  and there is no undo — AGENTS.md hard rules, and
  `.agents/context/operations/environment-and-deployment.md`.
- **Never delete `storage/installed`.** It is the kill-switch: with it gone, the
  next container start re-arms the installer's internal `migrate:fresh` against
  the live database, silently
  (`.agents/context/operations/deployment-homelab.md`).
- **Snapshot before you change anything** — see
  [incident-response.md](incident-response.md) §3.

## 9. Per-client commitments — the handover sheet

Filled at delivery, signed by both sides, and it is the record the SLA is measured
against. The blank version with its columns is
`docs/client-guide/ar/16-limits-and-provider.md` ("ورقة التسليم"); this is the
support-side view of the same sheet.

| Item | Value |
| --- | --- |
| Instance URL, admin path | |
| Vendor support contact (email, phone) | **DECISION** |
| Client contact who may open a severity 1 | |
| Client administrator who grants support access | |
| Support access method granted | named admin account / host access / none |
| Hours confirmed | Sun–Thu 09:00–17:00 Africa/Cairo (**DECISION**) |
| Monitoring in place for this instance | yes / no — and where the alert goes |
| Backups in place | pre-deploy dumps / plus nightly off-host |
| Backup retention and location | per [../legal/data-residency.md](../legal/data-residency.md) §4 |
| **Restore rehearsed on this instance** | date, or "not yet" — see [incident-response.md](incident-response.md) §6 |
| Version deployed, and the upgrade window | |
| Out-of-hours option bought | yes / no |

## 10. The one line to say when nothing else is true

> "I have your report. I do not know the cause yet. Here is what I am doing, and
> here is when I will tell you more."

That is a complete, honest answer under this SLA. Silence is not.

## Sources

- `.agents/context/operations/deployment-homelab.md` — monitoring, deploy flow,
  the `storage/installed` kill-switch.
- `.agents/context/operations/environment-and-deployment.md` — prune rules.
- `docs/client-guide/_internal/facts.md` §5 — the two silent failures.
- `docs/client-guide/ar/16-limits-and-provider.md` — provider services, handover
  sheet, product limits.
- `docs/sales/pricing.md` §2, §4 — what maintenance covers and what is an add-on.
- `AGENTS.md` — hard rules.

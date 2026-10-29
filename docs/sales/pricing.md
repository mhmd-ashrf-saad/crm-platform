# Pricing and packaging — single source of truth

**Status:** internal. These are the decided numbers for the Egyptian market as of
2026-10. If a price changes, it changes **here first**, then in
[`offer-sheet-ar.md`](offer-sheet-ar.md). Any other document that quotes a price
is quoting this file.

All prices are in **Egyptian pounds (EGP)**. A Sijilna installation holds exactly
one currency (`APP_CURRENCY`, set at install), so a client who bills in another
currency is a separate installation, not a setting.

## 1. The positioning rule — read this before quoting anything

**Sijilna is a sales register: leads, pipeline, quotes and follow-up. It is not
accounting and not ERP.**

There is no e-invoicing integration, no Egyptian Tax Authority connection, no
chart of accounts, no general ledger, no payroll, no purchase cycle and no
financial statements in this codebase. There is nothing to configure, enable or
"add later" — the code does not exist.

What this means when a buyer asks:

- If they need invoices, tax filing or books → that is an accounting system, and
  Sijilna is not it. Say so in one sentence and move on to their sales problem.
- If they ask "does it work with the tax authority?" → **"No."** Then: it records
  sales activity and produces Arabic quotes as PDF. That is the whole claim.
- Never soften this into "we're working on it", "in the next version" or "we can
  customise it". A custom module is a paid engagement (see §4) and even then it is
  a separate contract, not a promise made in a sales meeting.

The product's honest limits are enumerated in chapter 16 of the Arabic manual
(`docs/client-guide/ar/16-limits-and-provider.md`). Every sales conversation
must agree with it.

## 2. The model

| Element | Rule |
| --- | --- |
| **Licence** | Perpetual, one-time, per tier. The client owns the installation. If maintenance lapses the software keeps running; what stops is updates and support. |
| **Setup and training** | One-time, per tier. Installation, configuration, Arabic locale and currency, mail, initial training of the team. |
| **Annual maintenance** | **Mandatory**, and **year 1 is inside the original contract** — it is not free, it is part of the first invoice. Covers updates, security patches, upgrade assistance and support. |
| **Managed hosting** | Optional, monthly. Only if the client does not want to run the server themselves. |
| **Monthly fallback** | For buyers who refuse capital expenditure. See §5. |

Maintenance is mandatory because a self-hosted product with no maintenance path
becomes an unpatched server within a year. It is not a bundle discount and it is
not negotiable — the founding-client offer gives away the licence and the setup,
never the maintenance.

### Why this model, and not the other two

The three commercial models were compared before the numbers in §3 were set. The
choice is recorded here so it is not re-litigated in a meeting.

| Model | Verdict | Why |
| --- | --- | --- |
| **Hosted subscription, per user** | Rejected | It makes us the party operating every client's server, which contradicts the one thing the product claims (the database is on the client's server and nobody else touches it), and it puts us on the same per-seat ladder as Zoho (§7.1) as the newest and smallest vendor on it. It also hands us the hosting cost, the uptime obligation and the data-residency liability of every account. |
| **Implementation plus support, no licence** | Rejected | There is nothing left to sell once the install is finished: the product is already written, so pricing it as work prices hours, revenue stops at go-live while the support obligation continues, and the sale is anchored in the ERP project market at 50,000–250,000 EGP (§7.3) where the buyer expects customisation of an accounting system — which §1 says we are not. |
| **Perpetual licence + paid setup + mandatory annual maintenance** | **Chosen** | The licence is what the buyer is buying (ownership of the installation), the setup fee pays for a person present at install and training, and the maintenance keeps a self-hosted server patched at ≈18% of the licence, which is the market norm (§7.5). Managed hosting is optional and sold separately for the buyer who will not run a server. |

The 4,000/month fallback (§5) is not a fourth model: it is the same product sold
monthly to a buyer who refuses capital expenditure, and §5 shows it is
deliberately the more expensive route past month 10.

## 3. Tiers

| | Starter | Growth | Scale |
| --- | --- | --- | --- |
| Users | up to 5 | up to 15 | up to 40, multi-branch |
| Licence (one-time) | 28,000 | 55,000 | 110,000 |
| Setup and training (one-time) | 6,000 | 10,000 | 20,000 |
| Annual maintenance (mandatory) | 5,000 | 10,000 | 20,000 |
| Managed hosting (optional) | 1,800 / month | 2,500 / month | 4,000 / month |
| **Total at signature** (licence + setup + year-1 maintenance) | **39,000** | **75,000** | **150,000** |

The "total at signature" row is arithmetic, not a separate price: it is the sum of
the three one-time and first-year lines above it.

More than 40 users is not a tier — it is a quoted engagement built from the Scale
tier plus extra users, and it needs a decision before a number is given.

## 4. Add-ons

| Add-on | Price |
| --- | --- |
| Extra user (above the tier's cap) | 3,000 EGP, one-time |
| Custom module | from 12,000 EGP |
| Data migration from a legacy system | from 8,000 EGP |
| On-site training day | 3,500 EGP |
| Major version upgrade | 5,000 EGP |

"From" is literal: scope is written and priced before work starts, and a custom
module is never agreed verbally in a meeting.

The extra user is **paid once**. It adds a seat above the tier's cap; it does not
move the client into another tier, it does not change the tier's user ceiling, and
it does not change the annual maintenance, which follows the tier and nothing else.

## 5. Monthly fallback

For a buyer who will not pay capital expenditure:

- **0 upfront + 4,000 EGP per month, 12-month minimum.**

The arithmetic now points the other way, and that is deliberate: 4,000 × 12 =
**48,000 in year one**, against **39,000** at signature on the perpetual Starter
tier. Cumulative payments equal the Starter signature total at 39,000 ÷ 4,000 =
**9.75 months**, so from **month 10** the perpetual route is the cheaper one and
the fallback never catches up again. Commitment is now the cheaper path; the
fallback is the price of not committing.

<!-- TODO(المالك): the monthly fallback says nothing about which tier it covers,
     whether maintenance and updates are included while it is paid, whether setup
     is really zero (installation and training cost real hours), and what happens
     to the data if the client stops paying after month 12. Decide before it is
     offered in writing. -->

## 6. Founding clients — the first three only

| Given | Required |
| --- | --- |
| 50% off the licence | A signed contract |
| Setup fee waived | One written case study after 60 days |
| — | Three named introductions to peers |
| — | A monthly feedback call for 6 months |
| — | Permission to be named as a reference site |

**Maintenance is still paid in full.** The discount never touches it.

What the founding client pays at signature (half licence + zero setup + year-1
maintenance):

| | Starter | Growth | Scale |
| --- | --- | --- | --- |
| Founding total at signature | 19,000 | 37,500 | 75,000 |

The three introductions are the reason this exists. A founding client that will
not name three peers is not a founding client — it is a discount.

There are **zero clients today**. No reference site, testimonial, logo or case
study exists, and none may be implied in a sales document. This section is the
mechanism that creates the first three.

## 7. Why these numbers

Each anchor below is a fact about the market, not a competitor's secret. The
numbers in §3 were chosen against them.

**1. A SaaS CRM at list price is a recurring cost the client cannot stop paying.**
Zoho CRM Egypt list prices (pulled 25 Oct 2026 through a Zoho implementation
partner): Standard 700 EGP/user/month monthly or 490 annual, Professional
1,225 / 805, Enterprise 1,750 / 1,400, Ultimate 2,275 / 1,820.

- 8 users on Professional, billed annually: 8 × 805 × 12 ≈ **77,000 EGP per year,
  every year.**
- 15 users on Professional, billed annually: ≈ **145,000 EGP per year.**

Growth (up to 15 users) is 55,000 one-time — about **4.5 months** of Zoho
Professional at 15 seats, and then it is owned.

**2. The local all-in-one floor is roughly 733 EGP/month.** Daftra, an Egyptian
SaaS, starts around 733 EGP/month all-in-one — but its pitch is Egyptian
e-invoicing, which is precisely what Sijilna does not do. We are not competing on
that axis; a buyer whose need is invoicing belongs on Daftra and should be told so.

**3. Implementation-heavy ERPs price the project, not the seats.** Odoo
implementation in Egypt starts from 50,000 EGP with starter packages at
60,000–80,000 EGP; ERPNext implementation runs 75,000–250,000 EGP; support
retainers start from 5,000 EGP/month. This is why our setup fee exists and why it
is small: 6,000–20,000 buys installation and training on a system that is
already built, not a six-month project.

**4. Self-hosted software has a price floor, and we are above it.** Perfex CRM, a
self-hosted analogue, sells for 65–89 USD one-time. That is the floor of the
category, and it is a script with no Arabic-first interface, no local setup and
nobody to call. We are not selling a script; the licence pays for the product,
the setup fee pays for a human, and the maintenance pays for the phone being
answered.

**5. The on-premise norm: perpetual licence ≈ 3× the annual SaaS fee, and annual
maintenance ≈ 20% of the licence.** Our maintenance sits at **≈18%** of the
licence (5,000/28,000 = 18%; 10,000/55,000 = 18%; 20,000/110,000 = 18%), i.e. at
about the norm. Our licence does not — it sits far below 3× the SaaS equivalent
(see §8).

**6. The market is concentrated, so the cost of a visit is low and the cost of a
free trial is high.** Sadat City has roughly 2,000 operating factories across
seven industrial zones and produces about 15% of Egypt's non-oil exports, with an
active Sadat City Investors Association and a public industrial portal carrying a
business directory. That is why the cadence in [`README.md`](README.md) is ten
first meetings a week — visits are cheap here. It is also why
`offer-sheet-ar.md` carries no free trial or pilot: with a self-hosted product a
trial means a real installation and real training hours, and the 4,000/month
fallback is the cheapest way to let a hesitant buyer touch the system. If a trial
is ever wanted, it is a decision made here, not in a meeting.

## 8. What is probably wrong with this table

Kept here so it is fixed deliberately rather than discovered in a negotiation.

1. **The Starter licence is under-priced against every comparison in §7.** 28,000
   one-time for 5 users is ~7 months of Zoho Professional at the same seat count
   (5 × 805 × 12 = 48,000/year), where the on-prem norm says a perpetual licence
   should be ~3× the annual fee (3 × 48,000 ≈ 144,000, not the 15-seat
   ≈ 145,000 figure). That gap is a deliberate entry decision, but it has a cost:
   it anchors the product at "cheap", and the price cannot be raised
   later while reference clients are on the old contract. If it is raised, it is
   raised for new clients only, and `offer-sheet-ar.md` changes with it.
2. **The 6,000 setup fee does not cover the work.** Installation, Arabic locale
   and currency, mail, an admin account, training a team and staying reachable in
   week one is a day plus follow-up. It is defensible only because it is capped by
   "no data migration and no on-site training" — both of which are paid add-ons.
   Say that out loud in the meeting.
3. **The monthly fallback is not priced as a tier.** 4,000/month with no licence
   is a decision, not a discount: it makes ownership unattractive and cash flow
   attractive, which is the opposite of the model in §2. See the TODO in §5.
4. **Multi-branch is a tier feature with no price of its own.** Scale says
   "multi-branch" but nothing in the table charges for it, and it changes the
   deployment (one installation, several locations, more data). Price it or drop
   the phrase.
5. **The extra-user price used to be an arbitrage, and it is now closed.** At
   2,000 an extra user it was cheaper to buy seats than to climb a tier: the
   marginal licence rates were Starter→Growth 27,000 for 10 extra seats =
   **2,700/seat**, and Growth→Scale 55,000 for 25 extra seats = **2,200/seat**.
   At 3,000 one-time the add-on no longer undercuts the tier it sits next to, and
   a buyer who genuinely needs 16–40 seats can still construct the tier path that
   suits them best — Growth plus extra users, or Scale — and the arithmetic no
   longer pushes them into the wrong one.
6. **No VAT statement anywhere.** Egyptian prices are normally quoted exclusive
   of VAT. <!-- TODO(المالك): decide whether the published prices are VAT-inclusive,
   and if not, put one line in the offer sheet. Do not let a client discover it on
   the invoice. -->

## 9. How to quote

- Quote from this file, never from memory, and never from a previous email.
- **No discounting.** There is exactly one discount in this product: the founding
  offer, and it is closed at three clients. Everything else is scope, not price.
- Add-ons are quoted in writing with scope before work starts.
- Do not bundle the managed hosting into a discount to close a deal; it is a
  recurring service, and once given away it cannot be charged for.
- When the client asks for something the product does not do, the answer is in §1
  or §4 — not a promise.

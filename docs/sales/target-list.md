# Target list — a framework for 40 accounts

This file is a **method, not a list**. It contains no company names and no
contacts, on purpose: a list of 40 accounts is built from the sources named here,
by a person, in Sadat City, over three working days. Anything written down here
that looks like a lead would be invented.

The target is **40 qualified accounts**, held at exactly 40. An account enters
when another leaves — by closing, or by being disqualified. The list is not a
funnel that only grows; a list of 200 half-checked rows is a list of nobody.

## 1. What the account must look like

Four facts must be true before an account earns a row. If any is false, it is not
a target no matter how big it is.

1. **They sell business-to-business, and they quote before they sell.** A price is
   discussed, written down, sent, negotiated, revised. No quotes, no register.
2. **A sales process exists that more than one person touches.** An owner who
   sells alone keeps everything in his head and does not need software — yet.
3. **The record lives in a spreadsheet, in WhatsApp, in a notebook, or in one
   employee's memory.** The pipeline is not already owned by an ERP.
4. **A decision maker is identifiable by name.** "The company" does not take a
   meeting; a person does.

## 2. Segments to include

| Segment | Why it fits | What to listen for |
| --- | --- | --- |
| **Mid-size manufacturers** (roughly 50–300 staff) in the zones: plastics, packaging, chemicals, building materials, metals and engineering, food and beverage contract manufacturing | A factory sells to distributors and industrial buyers through quotes, with repeat orders and long cycles — the exact shape of a lead → quote → follow-up register | "العميل بيطلب عرض سعر وبيعدل فيه" · several reps per product line |
| **Importers and distributors with field sales reps** | Several reps covering areas, and the owner cannot see who followed up on what | "كل مندوب عنده ملفه" · the owner asks for a weekly report and gets WhatsApp |
| **Companies with an export line** | Quotes go out in writing, in more than one round, and the buyer compares revisions | "بنعدل العرض تلات وأربع مرات" · a paper trail matters |
| **Contract manufacturers / job-work shops** | Every job is a quoted job, priced per order | "كل شغلانة ليها عرض" |
| **Companies where the owner has been asked for data control** (medical supplies, anything with client confidentiality clauses, or an owner who has already refused a cloud tool once) | Self-hosting is the reason they are not on a SaaS CRM today | "البيانات دي متطلعش من الشركة" · "المدير رفض كلاود قبل كده" |

Note the corridor: the segment that fits is *not* "the biggest factories". It is
the mid-size B2B seller whose sales team is bigger than the owner's memory and
smaller than an ERP project.

## 3. Segments to exclude

| Segment | Why excluded |
| --- | --- |
| **Retail, showrooms, walk-in trade** | Sells over a counter; no quote, no pipeline. Nothing in the product helps. |
| **Micro workshops (fewer than ~10 staff, owner sells alone)** | The owner is the CRM. The licence cannot be justified yet, and a bad first reference is worse than no reference. |
| **Companies already running a real CRM or an ERP with a sales module** (SAP Business One, Odoo, Dynamics, a custom system) | Replacing a working system is a migration project, not a first sale. Exclude until the founding clients exist. |
| **Public sector, government bodies, tender-only buyers** | Procurement cycles measured in quarters, and a reference we cannot name. |
| **Anyone whose stated need is invoicing or tax** | Sijilna has no e-invoicing and no tax-authority integration. Disqualify in the first minute, honestly, and refer them to an accounting system. |
| **Anyone who only wants a price and no meeting** | Not a buyer; a price shopper. Put them on a monthly newsletter if one exists, not on the list. |

The public-sector and accounting-need exclusions are not snobbery. They are the
two places where a good meeting turns into a contract we cannot deliver.

## 4. The columns the list must have

Every row carries all 20 columns. A blank is a reason to go and find out, not a
reason to guess — leave it blank rather than writing an assumption.

| # | Column | What goes in it | Where it comes from |
| --- | --- | --- | --- |
| 1 | `id` | Sequence number, never reused | You |
| 2 | `account_ar` | Company name as written on the gate sign, in Arabic | Zone walk, portal, association list |
| 3 | `account_legal` | Registered name if it differs from the sign | Portal directory, the quote they send you |
| 4 | `sector` | One of the included segments in §2 | Portal activity field, the product on the sign |
| 5 | `product_line` | What they actually make or trade, in five words | Gate sign, their website, their quote sheet |
| 6 | `zone` | Which of the seven industrial zones, and the street | Zone walk (verified on foot — zones are mislabelled online) |
| 7 | `address` | Full address as you would give it to a driver | Zone walk |
| 8 | `staff_estimate` | A range: `<10`, `10–50`, `50–150`, `150–300`, `300+` | Gate/reception, accounting office, customs broker |
| 9 | `sales_team_size` | Number of people who sell, not the number of phones | The first meeting. Never infer it |
| 10 | `decision_name` | Full name of the owner, partner or sales director | Association list, referral, the meeting |
| 11 | `decision_role` | Owner / managing partner / sales director / export manager | Same |
| 12 | `decision_phone` | Mobile if you have it, never the switchboard | Referral, association list, an existing client |
| 13 | `switchboard` | The gate or reception number | Zone walk, portal |
| 14 | `current_tool` | `excel` · `paper` · `whatsapp` · `legacy_crm` · `erp_crm` · `unknown` | The meeting. This is the single most important field |
| 15 | `quotes_per_month` | A bracket: `<10`, `10–40`, `40–100`, `100+` | The meeting; before it, from the customs broker or the accounting office |
| 16 | `source` | `portal` · `association` · `zone_walk` · `referral` · `it_shop` · `accountant` · `broker` | Always one of these, never "the internet" |
| 17 | `source_date` | The date the row was created | You |
| 18 | `evidence` | `E1` met them on site · `E2` spoke to the decision maker · `E3` spoke to someone inside · `E4` desk research only | You |
| 19 | `score` | The 0–12 score from §6, with the date it was last scored | You |
| 20 | `next_action` + date | One action, one date. "Follow up" is not an action | You |

A row whose `evidence` is `E4` may not be counted in the 40. Desk research is how
a row starts, never how it qualifies.

## 5. Where each row comes from

| Source | What it gives you | Its limit | How to work it |
| --- | --- | --- | --- |
| **Sadat City industrial portal — business directory** | Legal name, activity, zone, switchboard number, sometimes a website. The fastest way to get from zero to 100 candidate rows | The activity text is generic ("industries"), the phone is a switchboard, and the directory is not verified against what is actually operating today | Bulk source, columns 2–5, 7, 13. Expect to discard a third on the first call |
| **Sadat City Investors Association — membership and events** | Member names, sectors, and often the owner's own name and mobile. Events are the cheapest way to meet several owners in one afternoon with no cold call | Gated: you need membership or an invitation, and you are one of several people with something to sell | The highest-value source for columns 10–12. Attend, listen, ask for one introduction at the end, leave |
| **Walking the seven industrial zones** | Whether the plant is operating, what it makes, the sign in Arabic, the gate staff, and whether sales sits inside the building | Slow: 6–8 gates in a half-day, and the gate is a gatekeeper, not a buyer | Two half-days a week. Columns 2–4, 6–9, 13, and `evidence E3`. The visit that produces the first meeting is worth more than twenty calls |
| **Inbound referrals** | Named decision maker, warm opening, shortest cycle, zero cost | Volume depends on asking, and asking is the part people skip | Three named introductions per closed client, asked for at signature and at handover. Log the referrer in `source` |
| **The local IT shop** | Who buys servers, who runs a NAS, who asked about hosting or backup — i.e. who already has the infrastructure to self-host | Second-hand and self-interested; they will also sell you their own preference | One relationship, quarterly. Ask which of their clients asked about a system this year |
| **Accounting offices** | Turnover band, headcount, whether the owner is expanding, and whether a system already exists — accountants see the shape of the business | Confidentiality: ask about the business, never about the numbers, and never ask them to share a client's financials | Two or three offices, one lunch each. They are also the fastest disqualifier: they know who already has an ERP |
| **Customs brokers** | Importers, traders moving volume, and companies whose quoting is per shipment | Their client book is their asset; nothing is free | Reciprocity first: bring them something (a referral, a contact) before asking |

The ordering matters. Walking zones and attending association events produce the
rows that convert; the portal and the brokered sources produce the volume that
keeps the pipeline from running dry in week three.

## 6. Lead scoring — is this account worth a visit?

Six criteria, two points each, **12 maximum**. Score in the meeting or right
after it; never from a directory row.

| Criterion | 0 points | 1 point | 2 points |
| --- | --- | --- | --- |
| **Sales team size** | Owner sells alone | 2–4 sellers | 5 or more sellers |
| **Quote volume** | Under 10 a month, or unknown | 10–40 a month | Over 40 a month |
| **Current tool** | A real CRM or an ERP with sales already | WhatsApp plus one shared sheet | Excel only, or paper, or each rep has his own file |
| **Decision maker** | Unknown, only a gate | Named, not yet met | Met, and engaged |
| **Fit with what we do** | Retail, walk-in, or invoice-driven | Partly: B2B but quotes are rare | B2B, quote-driven, repeat customers, a cycle of weeks |
| **Self-hosting pull** | None mentioned | The owner asked a question about hosting or data | A hard requirement: data must not leave the company |

| Score | Action |
| --- | --- |
| **9–12** | Book a visit this week. This is a target account. |
| **6–8** | Keep in nurture: one contact every two weeks, and never more than eight such accounts on the list |
| **5 or less** | Drop it. Say no on paper, so nobody re-adds it in a month |

**Automatic disqualifiers, whatever the score:** already mid-migration to an ERP;
the stated need is invoicing or tax; fewer than three people in the company;
public sector or tender-only. A disqualifier is recorded with the date and the
reason, and the row is removed from the 40.

## 7. The weekly routine

Sunday to Thursday. Roughly four hours a week, and it is the first thing that
disappears when a week gets busy — which is why it has a fixed day.

| Day | Block | What happens |
| --- | --- | --- |
| **Sunday** | 90 minutes | Source sweep: portal and association list, add 10 new candidate rows (`E4`). Fill columns 2–7 and 13 only |
| **Monday** | 60 minutes | Enrichment: for the 10 new rows, find a name (association list, referral, or an accounting office), fill 10–12. Anything still nameless after this week is dropped |
| **Tuesday** | Half-day, in the field | Walk one zone. 6–8 gates, `evidence E3`, and every row you pass gets a verified address and a real product line |
| **Wednesday** | 60 minutes | Verification calls: confirm name and role on 5 rows, and ask one question — "بتطلعوا عروض سعر إزاي؟". This is what moves a row from `E3` to `E2` |
| **Thursday** | 45 minutes | Score every changed row, write one `next_action` with a date per live row, and prune back to exactly 40 — drop the lowest score to make room for anything new |

Weekly check on the routine itself: **10 new first meetings booked** is the output
this routine is built to produce. If the routine ran and bookings did not happen,
the numbers in §6 are wrong, not the routine.

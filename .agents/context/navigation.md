<!-- Context: navigation.md | Priority: critical | Version: 1.0 | Updated: 2026-10-16 -->

# Context Navigation

This is the project knowledge base for the crm-platform Krayin CRM fork. Read
`.agents/context/quick-start.md` first, then the 1–3 category files your task
touches. The category list below is the whole map; nothing outside it is
authoritative.

## Categories

| Category | Purpose | Files |
| -------- | ------- | ----- |
| [operations/](operations/navigation.md) | How this checkout is provisioned, served, stored and reset | environment-and-deployment, provisioning, database |
| [guides/](guides/navigation.md) | Rules an agent must follow while working here | testing, agent-workflow-rules |
| [errors/](errors/navigation.md) | Failures that actually happened here, and how to detect each | lands-from-experience |

## Entry points

- [quick-start.md](quick-start.md) — 60-second orientation for a fresh session.
- `scripts/README.md` — the full operational runbook (canonical detail).
- `Makefile` — the only sanctioned command surface; `make` with no arguments lists it.
- `AGENTS.md` — upstream conventions plus the fork-specific project section.

## Rules for this knowledge base

- Files are MVI: one topic, under 200 lines, 3–5 key points.
- Reference files and commands; do not restate their content.
- When the running system disagrees with a file here, fix the file and note it.
- Run `context-validate` after any change to this tree.

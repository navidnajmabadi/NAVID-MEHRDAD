# SIPAW: software development life cycle (SDLC) pack

Private project documents for **SIPAW** (SI PAW by NAKILAB): the pet-care companion at https://sipaw.nakilab.com.
Owner: Navid. Co-founder: Mehrdad. Written 2026-11-07 from the code as it is on branch `PetFinder`.

| # | Document | Answers |
|---|----------|---------|
| 1 | [01-intent.md](01-intent.md) | **Project definition.** What problem, for whom, what success looks like, what is out of scope |
| 2 | [02-spec-features.md](02-spec-features.md) | **Features.** Everything the product does, by area, with status |
| 3 | [03-architecture.md](03-architecture.md) | **Architecture.** Stack, code layout, how a request and SIPO work, data, integrations, security |
| 4 | [04-operations.md](04-operations.md) | **Operations.** Environments, config, deploy, migrations, cron, monitoring, runbooks |
| 5 | [05-plan.md](05-plan.md) | **Plan.** Roadmap, backlog, decisions, open questions, risks, documentation debt |

Also in the repo root: [`CLAUDE.md`](../../CLAUDE.md) (rules for the AI coding assistant) and
[`docs/tasks/`](../tasks/) (one task table per working chat, the running change log).

## How work flows here

This is a small team (Navid and Mehrdad plus an AI coding assistant), so the lightweight SDLC is used.

| Stage | What happens | Where it is recorded |
|-------|--------------|----------------------|
| **Plan** | Navid describes a problem, usually with a screenshot. The assistant restates it in one line and writes it as a numbered row | `docs/tasks/<chat>.md` |
| **Design** | For anything over one file: the approach is written in the chat (and in `docs/claude/prN-*.md` for big features) before code | `docs/claude/`, this folder |
| **Build** | One branch per working chat, named `cursor/claude-<topic>`, cut from `PetFinder`. Small commits | git |
| **Test** | The assistant runs the change on a local copy (PHP built-in server, local MariaDB, a browser at phone width, stand-ins for Square and mail) and looks at it before saying done. No automated test suite exists yet (see the plan) | task table "Notes" column |
| **Review and release** | When Navid says "finished", the assistant opens **one PR** into `PetFinder` with a Done / Check live / Pending table. **Navid merges**; nobody else does | the PR |
| **Deploy** | Merge to `PetFinder` triggers a GitHub Action: SSH to Hostinger, `git pull --ff-only`, `php scripts/migrate.php` | `.github/workflows/deploy-sipaw.yml` |
| **Maintain** | Admin → Errors (every crash/warning/browser error with a code), Site health, NAKI (SIPO quality red team). Each real incident becomes a new task row | Admin console, `docs/tasks/` |

Status words used in task tables: **Done** (built and tested locally), **Check live** (built; needs one look on the live site),
**Pending** (needs a person or a decision).

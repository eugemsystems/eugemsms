# sERP Build Specification

The complete build specification: Volume 1 (architecture) plus Volume 2, Books A–K (detailed module specs). 78 of 78 modules from the original architecture blueprint. **~168,000 words total — do not read this folder start to finish in one session; read the file for the book you're currently building.**

| File | Contents |
|---|---|
| `00-how-to-use-this-specification.md` | Start here, once. The 20 cross-cutting rules, tech stack, full module → book index. |
| `01-volume1-architecture.md` | Tenancy model, session/period engine, configuration engine, module catalogue, financial integrity doctrine, roadmap. Read in full before any code. |
| `02-book-a-platform-foundation.md` | `CORE-01`–`CORE-13` — everything else depends on this. Build first. |
| `03-book-b-financial-core.md` | `FIN-01`, `02`, `03`, `04`, `05`, `06` — General Ledger, billing, invoicing, receipting, gateways, FX. |
| `04-book-c-people-organisation.md` | `PPL-01`–`04` — students, admissions, guardians, staff. |
| `05-book-d-academic-core.md` | `ACA-01`, `02`, `04`, `05` — curriculum, enrolment, attendance, grading. |
| `06-book-e-academic-depth.md` | `ACA-03`, `06`, `07` — timetabling, SBP, examinations. |
| `07-book-f-boarding-welfare.md` | `BRD-01`–`05` — hostels, roll call, exeats, catering, laundry. |
| `08-book-g-welfare-pastoral.md` | `BRD-06`–`08` — health, discipline, safeguarding. |
| `09-book-h1-procurement-stores-assets-budgets.md` | `FIN-08`–`11`. |
| `10-book-h2-operations-estates.md` | `OPS-01`–`07`. |
| `11-book-h3-payroll-fiscalisation-compliance.md` | `PPL-05`, `FIN-12`–`14`, `CMP-01`–`04`. |
| `12-book-i-communication-portals.md` | `COM-01`–`08`. |
| `13-book-j-intelligence-saas-control.md` | `INT-01`–`04`, `SAA-01`–`03`. |
| `14-book-k-closing-the-catalogue.md` | `FIN-07`, `PPL-06`, `ACA-08`–`11` — modules Volume 1 promised that an earlier pass missed. Read `PPL-03`/`FIN-02` alongside `FIN-07` specifically; they assume it exists. |

## How this folder is meant to be read

Not auto-loaded. `CLAUDE.md` at the project root carries the handful of rules that must hold everywhere, and `.claude/rules/` auto-loads a book's sharpest constraints the moment you touch that domain's module directory (see `.claude/rules/README.md`). This folder is what you open deliberately — with the Read tool, or `grep -r` across it for a specific rule ID or table name — when you need the full detail behind a constraint, or before starting a book you haven't built from yet.

Every book ends with its own **Build Sequence** and **Acceptance Gate**. Treat the gate as a hard stop, not a checklist to eyeball.

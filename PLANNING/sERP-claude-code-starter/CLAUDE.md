# sERP — School Management ERP (Zimbabwe)

Multi-tenant Laravel + Livewire + Next.js + Flutter school ERP for large Zimbabwean schools (ECD A–Upper 6, day + boarding). Full specification lives in `docs/specification/` — **78 modules across 11 books, ~168,000 words.** This file is deliberately short. Read it in full every session; read the specification on demand.

## Before you write any code

1. Read `docs/specification/00-how-to-use-this-specification.md` in full, once.
2. Read `docs/specification/01-volume1-architecture.md` in full, once — the tenancy model, session engine, and financial doctrine everything else assumes.
3. Do not open a module's detail until you've read the book it's in. Books are numbered `02`–`14` for reading order; the module → book index is in file `00`.
4. Path-scoped rules in `.claude/rules/` auto-load the right book's key constraints when you edit files under a matching `Modules/*` directory — see `.claude/rules/README.md`.

## Build order — do not skip a gate

Book A → B → C → D → E → F → G → H1 → H2 → H3 → I → J → K, in that order. Every book ends with a **Build Sequence** and an **Acceptance Gate**. Do not start a later book's module until the current book's gate is green — later modules assume earlier ones' guarantees hold (tenancy isolation, the Action pattern's CI rule, the roll-over invariant) and will silently misbehave if they don't.

**First sprint, specifically:** Book A (`02-book-a-platform-foundation.md`) Part 1 — the `Action` base class + its CI enforcement, `BelongsToSchool` + its isolation-test generator, the `Money` value object, context singletons, the middleware stack. Every one of the 78 modules assumes these exist exactly as specified before it does.

## Non-negotiable rules (full detail in file `00`, section "The twenty rules")

- **All business logic lives in Action classes.** Livewire components and API controllers validate input, call exactly one Action, format the result. Never a database write outside an Action. CI-enforced — build that check early.
- **Every tenant table has `school_id`, scoped by `BelongsToSchool`'s global scope.** A query without school context is a security defect, not an oversight.
- **Every academic/financial table has `academic_year_id`/`term_id`, scoped by `BelongsToSession`**, writes refused in `LOCKED` periods via `PeriodGuard`.
- **Money is an integer in minor units with an explicit currency — never a float, never bare in an API response.** Use the `Money` value object everywhere.
- **The General Ledger (`journal_lines`) is the only source of financial truth.** No other stored balance is authoritative; caches are verified against it nightly.
- **Financial and safeguarding tables are append-only at the database grant level** (`UPDATE`/`DELETE` revoked for the app DB user), not by convention. Corrections are new, linked, reversing records.
- **The part-time subject count has exactly one source: `learner_subject_enrolments`.** No denormalised count column, anywhere, ever.
- **Every statutory/regulatory figure (tax bands, NSSA rates, ZIMSEC subject limits) is versioned, effective-dated configuration — never a hard-coded constant.** Where the spec flags `requires_confirmation`, that's deliberate: a well-sourced figure subject to periodic external revision. Don't "correct" it from your own training data, which may be older or wrong on a Zimbabwe-specific figure. Two such figures (NSSA rate, A-Level subject ceiling) were re-researched and corrected during spec-writing with sources cited inline — that's the standard to hold new figures to as well.
- **Gender segregation in boarding allocation has no override path.** Safeguarding (`BRD-08`) access is inverted from the rest of the system — not even the platform's own Super Admin reads a case by default. Do not "simplify" either for convenience.
- **Tiered data visibility (medical, safeguarding, compensation) is enforced server-side, field by field.** A sensitive field is *absent* from an unauthorised response, never merely hidden client-side.
- **Fiscalisation, gateway payments, and messaging never block the operation they attach to.** A receipt posts and a parent is credited whether or not ZIMRA's FDMS or an SMS provider is reachable right now. Everything downstream queues and retries.
- **Historical documents/reports regenerate byte-identically from an archived period, forever.** Templates are versioned; journals separate `posted_at` from `effective_at`.
- **Every parent-, teacher-, or field-facing mobile workflow is offline-first with idempotent sync.** Load shedding and intermittent connectivity are permanent constraints of this market, not edge cases.
- **Before extending a module, check whether an owning module already exists for what you need.** Books I and J in particular flag modules that are easy to accidentally half-rebuild (a second notification bus, a second widget registry, a second anomaly detector). Check the book's own "don't rebuild it" section first.

## Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.4+, Laravel 13.x, modular via `nwidart/laravel-modules` (`Modules/{Name}/`) |
| Admin UI | Livewire 4 + Alpine.js + Tailwind CSS 4 — internal staff only |
| API | Laravel Sanctum, REST, `/api/v1/`, consumed by Next.js + Flutter |
| Web (staff/teacher/parent/student) | Next.js |
| Mobile | Flutter |
| Tenancy | Shared database, `school_id` row-scoping — not database-per-tenant |
| Database | PostgreSQL 16 or MySQL 8.4 |
| Queue/Cache | Redis + Laravel Horizon |
| Permissions | `spatie/laravel-permission`, extended with school + session scope, four reach levels (`own`/`assigned`/`section`/`school`) |
| Testing | Pest, PHPStan level 8. 80% coverage floor; 90%+ for Finance and safeguarding-adjacent code. |

Full rationale for every choice (the ADRs) is in `01-volume1-architecture.md` Part 2.

## Commands

_Fill this in as the project is scaffolded — build, test, lint, and migration commands belong here once they exist, so every session runs them the same way without re-deriving them._

```
# composer install
# php artisan migrate
# php artisan test
# ./vendor/bin/pint
# ./vendor/bin/phpstan analyse
```

## When the specification is silent

Extend the closest existing pattern consistently and update the specification file itself — don't quietly diverge in code. This is a living reference for the life of the product, not a one-time brief. If you correct or clarify something in `docs/specification/`, note it inline the way the two research corrections in file `00` are noted, with a reason and, where relevant, a source.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

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

Verified against this checkout: PHP 8.4.22 (Herd), Composer 2.9.8, Laravel 13.30.1, Node v24.6.0, npm 11.0.0, MySQL (`DB_DATABASE=school`).

```
# Setup
composer install
npm install
php artisan key:generate      # only if APP_KEY is unset
php artisan migrate

# Modules (nwidart/laravel-modules)
php artisan module:list
php artisan module:make {Name}

# Dev servers
composer run dev              # php artisan dev: serve + queue + logs + vite together
npm run dev                   # vite-plus dev server only

# Build
npm run build

# Test
php artisan test --compact
vendor/bin/pest --compact
vendor/bin/pest --filter=testName

# Lint / static analysis
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
```

## When the specification is silent

Extend the closest existing pattern consistently and update the specification file itself — don't quietly diverge in code. This is a living reference for the life of the product, not a one-time brief. If you correct or clarify something in `docs/specification/`, note it inline the way the two research corrections in file `00` are noted, with a reason and, where relevant, a source.

## Admin UI — no Flux

The Livewire admin panel does not use Livewire Flux. Admin components are
hand-built from the existing HTML template at `TEMPLATE/html-laravel/` in
the project root — this is the "in-house component library" option from
the tech stack table, not a deviation from it. Full rule and rationale:
`.claude/rules/livewire-admin-ui.md` (auto-loads whenever a Livewire view
or component is touched). `TEMPLATE/` also holds the Next.js template for
later — leave it untouched until that phase.

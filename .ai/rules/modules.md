---
paths:
  - 'Modules/*/**'
  - 'Modules/*/composer.json'
---

# Modules

## Module directory layout — no app/ prefix, matches ADR-002 exactly
Every sERP module (`Modules/{Name}/`) uses the exact tree from Volume 1 ADR-002 / Book A §0: `Domain/{Actions,Concerns,Scopes,Support,Exceptions,Contracts,Registry}`, `Http/{Controllers,Middleware,Requests,Resources}`, `Livewire/`, `Models/`, `Providers/` — all directly under the module root, NOT under `app/` (this deliberately departs from nwidart/laravel-modules v13's default `app/`-rooted generator). `database/`, `routes/`, `config/`, `tests/{Unit,Feature}` stay lowercase (Laravel/nwidart convention) so `php artisan module:*` tooling keeps working.

PSR-4: each module's own `composer.json` maps `Modules\{Name}\` to `""` (module root) plus `Modules\{Name}\Database\Factories\` -> `database/factories/` and `Modules\{Name}\Database\Seeders\` -> `database/seeders/` (same pattern as root composer.json's `Database\Factories\` -> `database/factories/`). Run `composer dump-autoload` after adding a new module's composer.json — the merge-plugin only picks it up then.

`config/modules.php`'s `generator` paths are already updated to match this layout — `php artisan module:make:*` commands will scaffold into the right folders.

Every model needing `HasFactory` must override `newFactory(): Factory` returning `『X』Factory::new()` explicitly — Laravel's default factory-name resolver only guesses `Database\Factories\{Model}Factory` for classes under the app namespace, which `Modules\{Name}\Models\X` never matches.

`Modules/Core` (Book A Part 1) is built first and owns: the `Action` base class, `BelongsToSchool`/`BelongsToSession` traits + `SchoolScope`/`SessionScope`, the `Money` value object, `SchoolContext`/`SessionContext` facades, the exception hierarchy, and the 8-step `serp.web`/`serp.api` middleware groups (registered in `CoreServiceProvider`, not Laravel's default `web`/`api` groups — modules opt in explicitly). `TenantModelRegistry` is where every module registers its `BelongsToSchool` models for the generated tenancy-isolation test suite (`Modules/Core/tests/Feature/TenancyIsolationTest.php`).

## Carbon 3's diffInX() defaults to signed, not absolute
This app runs nesbot/carbon 3.13. Unlike Carbon 2, `diffInSeconds()`/`diffInMinutes()`/`diffInMilliseconds()` etc. default `$absolute` to `false` — `$later->diffInSeconds($earlier)` returns a *negative* number when `$earlier` is in the past relative to `$later`. Any "how long ago" / "how stale" calculation (age checks, freshness checks, duration calculations) must pass `absolute: true` explicitly, or wrap in `abs()`. Found while building CORE-12's scheduled-task freshness and health-check age checks — silently inverted the stale/fresh determination until fixed.

## Fix psr-4 mapping and strip scaffold cruft after `module:make`
`php artisan module:make <Name>` generates `composer.json` with `"Modules\\<Name>\\": "app/"` and a full app scaffold (Http/, routes/, resources/, config/, database/seeders/, vite.config.js, package.json) — but every existing module in this codebase (Academic, People, etc.) maps `"Modules\\<Name>\\": ""` (the module root) and keeps only Domain/, Models/, Providers/, database/{migrations,factories}, and tests/{Feature,Unit}. After scaffolding a new module: (1) rewrite composer.json's psr-4 mapping to `""` and drop the generated `EventServiceProvider`/`RouteServiceProvider` from the main ServiceProvider's `$providers` array (delete those two files), (2) delete the unused Http/routes/resources/config/seeders/vite/package.json scaffold, (3) run `composer dump-autoload`, (4) register the module's `tests/Feature` and `tests/Unit` (and `Domain`/`Models`/`Providers` for coverage) in both `phpunit.xml` and `tests/Pest.php` — a new module's tests are silently skipped ("No tests found") until this is done. Also: new migrations must be numbered to run AFTER every existing module's migrations (check the highest existing `NNNN_01_01_...` prefix across all `Modules/*/database/migrations` first) — `module:make` does not know about the other modules' migration timestamps.

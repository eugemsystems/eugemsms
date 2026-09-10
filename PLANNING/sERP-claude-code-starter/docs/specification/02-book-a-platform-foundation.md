# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book A — Domain A: Platform & Foundation (`CORE-01` → `CORE-13`)

| Field | Value |
|---|---|
| Document | Volume 2, Book A of 10 |
| Covers | The 13 platform modules every other domain depends on |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisite | Volume 1 approved and signed off |
| Next book | Book B — Domain D Financial Core (`FIN-01` → `FIN-06`) |

---

## Part 0 — How To Build From This Document

### 0.1 The contract

Volume 1 told you *what* and *why*. This book tells you *exactly what to type*. Every table, every column, every method signature, every endpoint, every rule.

**Rules of engagement:**

1. **Do not deviate silently.** If a specification here is wrong or impossible, raise it, amend the document, bump the version, then build. A change made in code but not here creates a permanent divergence between the spec and reality, and within three months nobody trusts the document.
2. **Business rules are numbered and testable.** Every rule `BR-CORE-XX-NNN` maps to at least one automated test. A rule without a test is not implemented.
3. **Build in the stated order.** The dependency graph is real. `CORE-02` before `CORE-03` before `CORE-04` before everything.
4. **The acceptance gate in Part 4 is binding.** Domain B does not start until every gate test is green.

### 0.2 Identifier schemes

| Prefix | Meaning | Example |
|---|---|---|
| `BR-` | Business rule | `BR-CORE-03-014` |
| `AC-` | Acceptance criterion | `AC-CORE-05-007` |
| `TBL-` | Table | `TBL-schools` |
| `ACT-` | Domain action | `ACT-CloseFinancialPeriod` |
| `EVT-` | Domain event | `EVT-PeriodClosed` |
| `PERM-` | Permission string | `core.school.create` |
| `SET-` | Setting key | `academic.terms_per_year` |
| `JOB-` | Queued job | `JOB-RunPeriodRollover` |

### 0.3 Database conventions

| Convention | Rule |
|---|---|
| Table names | `snake_case`, plural. Pivots are singular-singular alphabetical: `school_user`. |
| Primary keys | `id`, `BIGINT UNSIGNED AUTO_INCREMENT`. Never UUID as PK (index bloat at this row count). |
| Public identifiers | Where a record is exposed in a URL or API, add `ulid CHAR(26) UNIQUE` alongside the PK. Expose the ULID, never the integer ID. |
| Foreign keys | `{singular_table}_id`. Always indexed. Always constrained. |
| Timestamps | `created_at`, `updated_at` on every table. `deleted_at` where soft-deletable. |
| Attribution | `created_by`, `updated_by`, `deleted_by` → `users.id`, nullable, on every business table. |
| Booleans | `is_` or `has_` prefix. `TINYINT(1)`, never nullable, always with a default. |
| Enums | Stored as `VARCHAR(30)`, validated by a PHP backed enum. **Never a database `ENUM` type** — altering one on a large table locks it. |
| JSON | `JSON` type. Any field needing a query gets a generated column plus an index. |
| Money | `{name}_minor BIGINT` + `{name}_currency CHAR(3)`. Never `DECIMAL`. Never `FLOAT`. |
| Indexes | Every composite index on a tenant table leads with `school_id`. |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci`. Learner names include diacritics. |

### 0.4 The standard column set

Every tenant-owned business table carries this block. It is not optional.

```sql
id              BIGINT UNSIGNED  PK AUTO_INCREMENT
ulid            CHAR(26)         NOT NULL  UNIQUE
school_id       BIGINT UNSIGNED  NOT NULL  FK → schools.id  INDEX
-- ... module columns ...
created_by      BIGINT UNSIGNED  NULL      FK → users.id
updated_by      BIGINT UNSIGNED  NULL      FK → users.id
deleted_by      BIGINT UNSIGNED  NULL      FK → users.id
created_at      TIMESTAMP        NULL
updated_at      TIMESTAMP        NULL
deleted_at      TIMESTAMP        NULL      INDEX
```

**Session-bound tables** (anything academic or financial) additionally carry:

```sql
academic_year_id  BIGINT UNSIGNED  NOT NULL  FK → academic_years.id
term_id           BIGINT UNSIGNED  NULL      FK → terms.id
```

`term_id` is nullable only for records that are genuinely year-scoped rather than term-scoped (an annual SBP, a yearly asset depreciation schedule). If in doubt, it is term-scoped.

### 0.5 Migration ordering

Module migrations run in dependency order, enforced by a numeric prefix reserved per module:

```
0001_01_01_*    CORE-01  installer / system tables
0002_01_01_*    CORE-02  tenancy
0003_01_01_*    CORE-03  sessions
0004_01_01_*    CORE-04  settings
0005_01_01_*    CORE-05  identity
...
0100_01_01_*    PPL-*
0200_01_01_*    ACA-*
0300_01_01_*    FIN-*
```

A module may never create a foreign key to a table owned by a module with a higher prefix.

### 0.6 Code style

- PHP 8.4, strict types declared in every file.
- PSR-12, enforced by Laravel Pint in CI.
- PHPStan level 8. No baseline exceptions in new code.
- Every public method has a return type. Every parameter is typed.
- No facades inside Action classes — dependencies are constructor-injected so actions are unit-testable without booting the framework.
- Readonly DTOs. Backed enums for every fixed set.

---

## Part 1 — Global Technical Foundations

> Build this part first, before any module. Everything else assumes it exists.

### 1.1 The Action pattern ⭐

Every business operation is a class with a single public method. This is the enforcement mechanism for ADR-002.

```php
namespace Modules\Core\Domain\Actions;

abstract class Action
{
    /**
     * Every action executes inside a database transaction by default.
     * Override to false only for actions that manage their own transaction
     * boundaries (batch runs, imports).
     */
    protected bool $transactional = true;
}
```

**Concrete shape:**

```php
final class CloseFinancialPeriodAction extends Action
{
    public function __construct(
        private readonly PeriodValidator $validator,
        private readonly SnapshotWriter $snapshots,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(CloseFinancialPeriodData $data): PeriodCloseResult
    {
        // 1. authorise (policy check — never trust the caller)
        // 2. validate business preconditions
        // 3. mutate
        // 4. dispatch events
        // 5. return a typed result
    }
}
```

**Rules:**

| # | Rule |
|---|---|
| `BR-GLOBAL-001` | An Action accepts exactly one readonly DTO and returns exactly one typed result object or `void`. |
| `BR-GLOBAL-002` | An Action re-checks authorisation itself. It never assumes the controller or Livewire component did. |
| `BR-GLOBAL-003` | An Action never reads from `request()`, `session()`, or `auth()` directly. Everything it needs is on the DTO. |
| `BR-GLOBAL-004` | An Action never returns an HTTP response, a Blade view, or an API Resource. |
| `BR-GLOBAL-005` | A Livewire component or API controller may not write to the database except through an Action. **CI-enforced by static analysis.** |
| `BR-GLOBAL-006` | Every Action has a unit test that calls it directly, without HTTP and without Livewire. |

**The CI rule that enforces it:**

```
FAIL the build if any file under Http/Controllers/ or Livewire/
contains: ->save() | ->create( | ->update( | ->delete( | DB::table(
```

### 1.2 `BelongsToSchool` trait

```php
trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope());

        static::creating(function (Model $model) {
            if (blank($model->school_id)) {
                $model->school_id = SchoolContext::currentId()
                    ?? throw new MissingSchoolContextException(static::class);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Escape hatch. Permission-gated and logged. */
    public function scopeWithoutSchoolScope(Builder $q): Builder
    {
        SchoolScope::assertBypassPermitted(static::class);
        return $q->withoutGlobalScope(SchoolScope::class);
    }

    /** Explicit multi-school query for group-level reporting. */
    public function scopeForSchools(Builder $q, array $schoolIds): Builder
    {
        SchoolScope::assertSchoolsAccessible($schoolIds);
        return $q->withoutGlobalScope(SchoolScope::class)
                 ->whereIn('school_id', $schoolIds);
    }
}
```

| # | Rule |
|---|---|
| `BR-GLOBAL-010` | Every tenant model uses `BelongsToSchool`. A migration creating a tenant table without `school_id` fails CI. |
| `BR-GLOBAL-011` | Creating a model with no resolvable school context throws. It never silently writes `NULL` or `0`. |
| `BR-GLOBAL-012` | `withoutSchoolScope()` requires `core.system.bypass_school_scope` and writes an audit entry naming the model, the user, and the calling class. |
| `BR-GLOBAL-013` | `forSchools()` validates that the caller is assigned to every school listed. Requesting an unassigned school throws `UnauthorisedSchoolAccessException`. |
| `BR-GLOBAL-014` | A foreign key may never reference a row in a different school. Enforced by an `AssertsSameSchool` validation rule on every cross-model relationship, plus a nightly integrity job. |

### 1.3 `BelongsToSession` trait

```php
trait BelongsToSession
{
    protected static function bootBelongsToSession(): void
    {
        static::addGlobalScope(new SessionScope());   // filters by active year+term

        static::creating(function (Model $model) {
            $model->academic_year_id ??= SessionContext::yearId();
            $model->term_id          ??= SessionContext::termId();
        });

        static::saving(function (Model $model) {
            PeriodGuard::assertWritable($model);      // see BR-CORE-03-020
        });
    }
}
```

### 1.4 The `Money` value object ⭐

```php
final readonly class Money implements JsonSerializable
{
    private function __construct(
        public int $minor,        // 45000
        public Currency $currency, // Currency::USD
    ) {}

    public static function of(int $minor, Currency $c): self;
    public static function fromDecimal(string $decimal, Currency $c): self;  // string input only
    public static function zero(Currency $c): self;

    public function plus(Money $other): self;      // throws on currency mismatch
    public function minus(Money $other): self;
    public function multiplyBy(string $factor, RoundingMode $m = Banker): self;
    public function allocate(array $ratios): array; // distributes remainder deterministically

    public function isZero(): bool;
    public function isNegative(): bool;
    public function compareTo(Money $other): int;

    public function toDecimal(): string;
    public function format(?string $locale = null): string;
    public function jsonSerialize(): array;         // the API money envelope
}
```

| # | Rule |
|---|---|
| `BR-GLOBAL-020` | Money is never constructed from a `float`. `fromDecimal()` accepts a string only. |
| `BR-GLOBAL-021` | Arithmetic between different currencies throws `CurrencyMismatchException`. Conversion is explicit, through the FX service, and always records the rate. |
| `BR-GLOBAL-022` | `allocate()` distributes any rounding remainder to the largest share first, deterministically. The sum of allocations always equals the original to the cent. |
| `BR-GLOBAL-023` | Default rounding is banker's rounding, applied once, at the point of persistence. |
| `BR-GLOBAL-024` | Every API money field serialises as `{ amount_minor, currency, formatted }`. A bare number in a money field fails contract testing. |

### 1.5 Context singletons

```php
SchoolContext::current(): ?School
SchoolContext::currentId(): ?int
SchoolContext::set(School $school): void       // re-resolves permissions, flushes caches
SchoolContext::assertSet(): void

SessionContext::year(): AcademicYear
SessionContext::term(): ?Term
SessionContext::isCurrentLiveTerm(): bool      // drives the historical-view banner
SessionContext::set(AcademicYear $y, ?Term $t): void
```

Both are request-scoped, resolved by middleware, and available for injection. They are **never** written to from inside an Action — an Action receives context on its DTO.

### 1.6 Exception hierarchy

```
SerpException (abstract)
├── DomainException          — business rule violated; renders 422
│   ├── PeriodLockedException
│   ├── InsufficientBalanceException
│   ├── DuplicateRecordException
│   └── InvalidStateTransitionException
├── AuthorisationException   — renders 403
│   ├── UnauthorisedSchoolAccessException
│   ├── ModuleNotEnabledException
│   └── InsufficientScopeException
├── ContextException         — renders 400
│   ├── MissingSchoolContextException
│   └── MissingSessionContextException
└── IntegrationException     — renders 502, always retried
    ├── GatewayUnreachableException
    └── FiscalisationFailedException
```

Every exception carries a stable machine `code` used verbatim in the API error envelope, and a human message safe to display.

### 1.7 Event naming and the module contract

Events are the only sanctioned way for one module to react to another. Direct cross-module service calls are permitted **downward** in the dependency graph only; anything upward or sideways is an event.

```php
namespace Modules\Core\Domain\Events;

final readonly class PeriodClosed
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public PeriodType $type,       // ACADEMIC | FINANCIAL
        public int $closedByUserId,
        public CarbonImmutable $closedAt,
    ) {}
}
```

| # | Rule |
|---|---|
| `BR-GLOBAL-030` | Event names are past tense: `PeriodClosed`, not `ClosePeriod`. |
| `BR-GLOBAL-031` | Events carry IDs and scalars, never Eloquent models. A queued listener must not depend on model state at dispatch time. |
| `BR-GLOBAL-032` | Every event carries `schoolId`. Listeners set the school context from it before doing anything. |
| `BR-GLOBAL-033` | Every module documents the events it publishes and consumes. That list is the integration contract. |

### 1.8 Permission registry

```
{module}.{resource}.{action}[.{scope}]

scope ∈ { own, assigned, section, school }   — absent means school-wide
```

Permissions are **registered in code** by each module's service provider and synced to the database on deploy. A permission that is not registered cannot be assigned.

```php
PermissionRegistry::register('core', [
    'school.view', 'school.create', 'school.update', 'school.archive',
    'period.close', 'period.reopen',
    'settings.view', 'settings.update',
    'audit.view', 'audit.export',
    // ...
]);
```

### 1.9 API resource conventions

```php
final class SchoolResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->ulid,          // never the integer PK
            'code'        => $this->code,
            'name'        => $this->name,
            'centre_number' => $this->centre_number,
            'base_currency' => $this->base_currency,
            'logo_url'    => $this->logoUrl(),
            'sections'    => SectionResource::collection($this->whenLoaded('sections')),
            'custom_fields' => $this->customFieldValues(),   // school-defined, always present
        ];
    }
}
```

| # | Rule |
|---|---|
| `BR-GLOBAL-040` | Integer primary keys are never exposed. ULIDs only. |
| `BR-GLOBAL-041` | Relationships are only included when eager-loaded. No lazy loading inside a resource — it produces N+1 under load. |
| `BR-GLOBAL-042` | Every resource includes a `custom_fields` map, even when empty, so clients can render school-defined fields generically. |
| `BR-GLOBAL-043` | Every list endpoint is paginated. There is no unpaginated collection endpoint anywhere in the API. |

### 1.10 The middleware stack

Registered globally, in this order. By the time module code runs, context is guaranteed.

| Order | Middleware | Web | API | Behaviour |
|---|---|---|---|---|
| 1 | `ResolveTenant` | ✅ | ✅ | Subdomain (web) or token (API). 404 on unknown tenant. |
| 2 | `EnsureSubscriptionActive` | ✅ | ✅ | Suspended tenants get read-only + notice, never a hard lockout. |
| 3 | `Authenticate` | web guard | sanctum | |
| 4 | `SetSchoolContext` | ✅ | ✅ | From user preference or validated `X-School-Id`. |
| 5 | `SetSessionContext` | ✅ | ✅ | From preference or validated session headers. |
| 6 | `EnsureModuleEnabled` | ✅ | ✅ | Route declares its module code. 403 `MODULE_NOT_ENABLED`. |
| 7 | `EnforceTokenAbility` | — | ✅ | Route declares required abilities. |
| 8 | `RecordActivity` | ✅ | ✅ | Last-seen, request ID injection, audit correlation. |

### 1.11 Testing standards

| Test class | Requirement |
|---|---|
| Unit | Every Action, every value object, every state machine. |
| Feature — Livewire | Every screen: render, permission denial, validation failure, happy path. |
| Feature — API | Every endpoint: 200, 401, 403, 422, and the module-disabled 403. |
| **Tenancy isolation** | **Automated for every tenant model.** Create the same record in School A and School B, authenticate as a School A user, assert School B's record is invisible on index, show, update, and delete. **This suite is generated from the model registry — adding a model without a passing isolation test fails CI.** |
| Period guard | For every session-bound model: assert writes are rejected in a `LOCKED` period and require approval in `SOFT_CLOSED`. |
| Coverage | 80% overall. **90% minimum for any module in Domain D (Finance).** |

---

## Part 2 — Module Specifications

---

# CORE-01 · System Installer & Provisioning

### 1. Scope

**In scope.** Browser and CLI installation, requirements verification, database setup, migration execution, licence activation, super-admin creation, first tenant and school, Zimbabwe baseline seeding, service configuration with connectivity tests, upgrade runner.

**Out of scope.** Server provisioning, web server configuration, TLS certificates, DNS. Documented in the deployment runbook, not automated here.

### 2. Data model

```sql
system_installations
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
installed_at        TIMESTAMP    NOT NULL
installed_version   VARCHAR(20)  NOT NULL      -- '1.0.0'
deployment_mode     VARCHAR(20)  NOT NULL      -- saas | dedicated | on_premise
licence_key         VARCHAR(255) NULL
licence_activated_at TIMESTAMP   NULL
licence_expires_at  TIMESTAMP    NULL
installation_uuid   CHAR(36)     NOT NULL UNIQUE  -- reported to licence server
server_fingerprint  VARCHAR(128) NULL             -- on-premise binding
created_at, updated_at

system_upgrades
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
from_version        VARCHAR(20)  NOT NULL
to_version          VARCHAR(20)  NOT NULL
started_at          TIMESTAMP    NOT NULL
completed_at        TIMESTAMP    NULL
status              VARCHAR(20)  NOT NULL   -- running | completed | failed | rolled_back
backup_reference    VARCHAR(255) NULL
migrations_run      JSON         NULL
error_log           TEXT         NULL

install_steps                          -- resumability
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
step_key            VARCHAR(50)  NOT NULL UNIQUE
status              VARCHAR(20)  NOT NULL   -- pending | running | completed | failed
payload             JSON         NULL       -- collected input, secrets redacted
completed_at        TIMESTAMP    NULL
error_message       TEXT         NULL
```

### 3. Domain actions

| Action | Input | Output | Notes |
|---|---|---|---|
| `ACT-VerifyRequirements` | — | `RequirementsReport` | PHP version, extensions (`pdo`, `mbstring`, `openssl`, `gd`, `zip`, `bcmath`, `intl`), writable paths, Composer, Node, memory limit, `max_execution_time` |
| `ACT-TestDatabaseConnection` | `DatabaseCredentialsData` | `ConnectionResult` | Connects, checks version, checks privileges, checks schema empty or matching |
| `ACT-WriteEnvironmentFile` | `EnvironmentData` | `void` | Atomic write; backs up existing `.env` first |
| `ACT-RunInstallMigrations` | `MigrationRunData` | `MigrationResult` | Chunked, resumable, progress broadcast |
| `ACT-ActivateLicence` | `LicenceKeyData` | `LicenceActivation` | Online validation with offline grace fallback |
| `ACT-CreateSuperAdmin` | `SuperAdminData` | `User` | Forces 2FA enrolment on first login |
| `ACT-ProvisionFirstTenant` | `TenantProvisionData` | `Tenant` | |
| `ACT-ProvisionFirstSchool` | `SchoolProvisionData` | `School` | Delegates to `CORE-02` |
| `ACT-SeedZimbabweBaseline` | `SeedPackData` | `SeedResult` | See §7 |
| `ACT-TestServiceConnection` | `ServiceTestData` | `ServiceTestResult` | mail / SMS / WhatsApp / storage / queue |
| `ACT-FinaliseInstallation` | — | `void` | Caches config+routes+views, writes `installed.lock`, records the installation |
| `ACT-RunUpgrade` | `UpgradeData` | `UpgradeResult` | Pre-backup → migrate → verify → record |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-01-001` | If `storage/installed.lock` exists, every installer route returns 404. The installer is unreachable after install. |
| `BR-CORE-01-002` | Installation cannot proceed past step 2 while any **mandatory** requirement fails. Optional requirements produce warnings only. |
| `BR-CORE-01-003` | Each step's outcome is persisted. A resumed installation restarts at the first non-completed step, never step 1. |
| `BR-CORE-01-004` | Database credentials are validated by an actual connection before being written to `.env`. |
| `BR-CORE-01-005` | The target schema must be empty or contain only sERP tables. A schema with foreign tables is rejected with an explicit message. |
| `BR-CORE-01-006` | Migrations run in module prefix order (§0.5). A failure halts immediately, reports the failing migration, and leaves prior migrations intact for resumption. |
| `BR-CORE-01-007` | Licence activation failure does not block installation. The system installs in a 14-day grace state and warns daily on the dashboard. **A school is never locked out mid-installation by a network problem.** |
| `BR-CORE-01-008` | The super administrator password must meet the configured policy and cannot be a known-breached password. 2FA enrolment is forced on first login and cannot be skipped. |
| `BR-CORE-01-009` | Timezone defaults to `Africa/Harare`; locale to `en_ZW`; base currency to `USD`. All are changeable. |
| `BR-CORE-01-010` | Every service test (mail, SMS, storage, queue) is skippable. A school without an SMS gateway on day one must still be able to finish installing. |
| `BR-CORE-01-011` | The upgrade runner takes a full database backup before running any migration and records the backup reference. An upgrade with no verified backup is refused. |
| `BR-CORE-01-012` | `installation_uuid` is generated once and never regenerated. It is the licence identity. |

### 5. Screens (Livewire, unauthenticated, standalone layout)

| Screen | Component | Notes |
|---|---|---|
| Welcome & licence | `Install\Welcome` | Terms acceptance recorded with timestamp and IP |
| Requirements | `Install\Requirements` | Live re-check button; pass/warn/fail per item with remediation text |
| Environment | `Install\Environment` | App name, URL, timezone, locale, deployment mode |
| Database | `Install\Database` | Credentials + **Test connection** before Next is enabled |
| Migrations | `Install\Migrations` | Live progress via polling; per-module status; resumable |
| Licence | `Install\Licence` | Key entry, activation, or "continue in grace mode" |
| Administrator | `Install\Administrator` | Name, email, password with live strength meter |
| Tenant & school | `Install\Organisation` | Tenant name, school name, code, centre number, sections, base currency |
| Baseline seed | `Install\Seed` | Checkbox list of seed packs with descriptions |
| Services | `Install\Services` | Per-service config with individual Test buttons, all skippable |
| Finalise | `Install\Finalise` | Runs optimisation, writes the lock, shows credentials summary and login link |

### 6. Artisan commands (headless install)

```bash
php artisan serp:install --headless --config=install.json
php artisan serp:install:verify              # requirements only
php artisan serp:install:status              # step-by-step state
php artisan serp:upgrade --to=1.1.0 --backup-first
php artisan serp:seed:zimbabwe --school={ulid} --packs=coa,grading,roles
```

### 7. Zimbabwe baseline seed packs

| Pack | Contents |
|---|---|
| `roles` | The 40+ seeded role templates from Volume 1 §3.3, with default permission assignments |
| `coa` | Chart of accounts for a Zimbabwean school — asset, liability, equity, income segmented by fee component, expense by department, plus mandatory system accounts (Suspense, Realised FX, Unrealised FX, Rounding, Prior Period Adjustment, Fee Discount Contra, Bad Debt) |
| `currencies` | USD, ZWG with precision and formatting |
| `grading` | ZIMSEC O-Level (A–U), A-Level (A–E with points), Grade 7 unit grades, primary Distinction/Merit/Credit/Pass, Cambridge (A*–U) |
| `learning_areas` | Heritage-Based Curriculum learning areas with ZIMSEC subject codes, by level, with pathway tagging |
| `calendar` | Three-term MoPSE calendar template for the current and next year |
| `levels` | ECD A, ECD B, Grade 1–7, Form 1–6 with default section mapping |
| `templates` | Report card, invoice, receipt, statement, exeat pass, transfer letter defaults |
| `notifications` | Default message templates for all `COM-02` triggers, in English |
| `settings` | Sensible Zimbabwean defaults for every registered setting |

### 8. Acceptance criteria

```gherkin
AC-CORE-01-001
  Given a server meeting all mandatory requirements
  And an empty database
  When I complete the installer through every step
  Then installed.lock exists
  And I can log in as the super administrator
  And I am forced to enrol in 2FA before reaching the dashboard

AC-CORE-01-002
  Given installation has completed
  When I request /install
  Then I receive a 404

AC-CORE-01-003
  Given the migration step failed at module CORE-07
  When I return to the installer
  Then I resume at the migration step
  And migrations for CORE-01 through CORE-06 are not re-run

AC-CORE-01-004
  Given the licence server is unreachable
  When I submit a valid-format licence key
  Then installation continues in grace mode
  And the dashboard shows a grace-period warning with days remaining

AC-CORE-01-005
  Given a database containing tables from another application
  When I test the connection
  Then installation is blocked with a message naming the conflicting tables
```

---

# CORE-02 · Tenancy & School Registry

### 1. Scope

**In scope.** Tenant, school, section, level, class/stream, house entities. School switching. The `school_id` enforcement machinery. Cross-school user assignment. Group consolidation flags.

**Out of scope.** Users and roles (`CORE-05`). Academic years and terms (`CORE-03`). Subscription and billing (`SAA-01`).

### 2. Data model

```sql
tenants
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
name                  VARCHAR(150) NOT NULL
slug                  VARCHAR(60)  NOT NULL UNIQUE     -- subdomain
type                  VARCHAR(30)  NOT NULL   -- independent | trust | mission | council | group
contact_name          VARCHAR(150)
contact_email         VARCHAR(150)
contact_phone         VARCHAR(30)
country               CHAR(2)      NOT NULL DEFAULT 'ZW'
status                VARCHAR(20)  NOT NULL   -- active | trial | suspended | cancelled
is_group_reporting_enabled TINYINT(1) NOT NULL DEFAULT 0
created_at, updated_at, deleted_at

schools                                        -- THE tenancy anchor
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
tenant_id             BIGINT       FK → tenants.id  INDEX
code                  VARCHAR(20)  NOT NULL          -- 'SGC'  UNIQUE(tenant_id, code)
name                  VARCHAR(200) NOT NULL
short_name            VARCHAR(60)
centre_number         VARCHAR(20)  NULL              -- ZIMSEC centre number
emis_code             VARCHAR(30)  NULL              -- MoPSE EMIS identifier
category              VARCHAR(30)  NOT NULL   -- government | council | mission | trust | private
responsible_authority VARCHAR(150) NULL
band                  VARCHAR(20)  NULL       -- P1..P3, S1..S3 (MoPSE banding)
province              VARCHAR(60)
district              VARCHAR(60)
address_line_1        VARCHAR(200)
address_line_2        VARCHAR(200)
city                  VARCHAR(100)
latitude              DECIMAL(10,7) NULL
longitude             DECIMAL(10,7) NULL
phone                 VARCHAR(30)
email                 VARCHAR(150)
website               VARCHAR(200)
motto                 VARCHAR(200)
logo_path             VARCHAR(255)
crest_path            VARCHAR(255)
letterhead_path       VARCHAR(255)
primary_colour        CHAR(7)      DEFAULT '#1a3a5c'
secondary_colour      CHAR(7)
base_currency         CHAR(3)      NOT NULL DEFAULT 'USD'
timezone              VARCHAR(50)  NOT NULL DEFAULT 'Africa/Harare'
locale                VARCHAR(10)  NOT NULL DEFAULT 'en_ZW'
head_user_id          BIGINT       NULL FK → users.id
status                VARCHAR(20)  NOT NULL   -- active | inactive | archived
opened_on             DATE         NULL
created_by, updated_by, deleted_by, created_at, updated_at, deleted_at

  UNIQUE (tenant_id, code)
  INDEX  (tenant_id, status)

school_sections
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK  INDEX
code                  VARCHAR(20)  NOT NULL   -- 'INF','JUN','LSEC','USEC','SIXTH'
name                  VARCHAR(100) NOT NULL   -- 'Junior School'
type                  VARCHAR(20)  NOT NULL   -- ecd | primary | secondary | sixth_form
sort_order            SMALLINT     NOT NULL DEFAULT 0
head_user_id          BIGINT       NULL FK → users.id
is_active             TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

grade_levels
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK  INDEX
section_id            BIGINT       FK → school_sections.id
code                  VARCHAR(20)  NOT NULL   -- 'ECDA','G1'..'G7','F1'..'F6'
name                  VARCHAR(60)  NOT NULL   -- 'Form 3'
ordinal               SMALLINT     NOT NULL   -- 0..13, drives promotion sequence
is_exam_level         TINYINT(1)   NOT NULL DEFAULT 0   -- G7, F4, F6
is_entry_level        TINYINT(1)   NOT NULL DEFAULT 0
is_exit_level         TINYINT(1)   NOT NULL DEFAULT 0
capacity              SMALLINT     NULL
is_active             TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)
  INDEX  (school_id, ordinal)

school_classes                                 -- streams
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK  INDEX
academic_year_id      BIGINT       FK → academic_years.id  INDEX
grade_level_id        BIGINT       FK → grade_levels.id
code                  VARCHAR(30)  NOT NULL   -- 'F3B'
name                  VARCHAR(80)  NOT NULL   -- 'Form 3 Blue'
stream_label          VARCHAR(30)  NULL       -- 'Blue', 'Science', 'A'
class_teacher_id      BIGINT       NULL FK → users.id
assistant_teacher_id  BIGINT       NULL FK → users.id
room_id               BIGINT       NULL FK → rooms.id
capacity              SMALLINT     NOT NULL DEFAULT 40
is_active             TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, academic_year_id, code)
  INDEX  (school_id, academic_year_id, grade_level_id)

houses
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK  INDEX
code                  VARCHAR(20)  NOT NULL
name                  VARCHAR(60)  NOT NULL   -- 'Chitepo'
colour                CHAR(7)
motto                 VARCHAR(150)
housemaster_id        BIGINT       NULL FK → users.id
is_active             TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

school_user                                    -- cross-school assignment
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK  INDEX
user_id               BIGINT       FK  INDEX
is_primary            TINYINT(1)   NOT NULL DEFAULT 0
status                VARCHAR(20)  NOT NULL DEFAULT 'active'
assigned_at           TIMESTAMP
assigned_by           BIGINT       NULL FK → users.id
  UNIQUE (school_id, user_id)

school_modules                                 -- entitlement
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK  INDEX
module_code           VARCHAR(20)  NOT NULL   -- 'BRD-01'
is_enabled            TINYINT(1)   NOT NULL DEFAULT 0
enabled_at            TIMESTAMP    NULL
enabled_by            BIGINT       NULL FK → users.id
expires_at            TIMESTAMP    NULL
config                JSON         NULL
  UNIQUE (school_id, module_code)
```

### 3. Domain actions

| Action | Input | Output |
|---|---|---|
| `ACT-CreateTenant` | `CreateTenantData` | `Tenant` |
| `ACT-CreateSchool` | `CreateSchoolData` | `School` |
| `ACT-UpdateSchoolProfile` | `UpdateSchoolData` | `School` |
| `ACT-ArchiveSchool` | `ArchiveSchoolData` | `void` |
| `ACT-CloneSchoolConfiguration` | `CloneConfigData` | `CloneResult` |
| `ACT-CreateSection` / `ACT-CreateGradeLevel` / `ACT-CreateClass` / `ACT-CreateHouse` | respective DTOs | model |
| `ACT-AssignUserToSchool` | `AssignUserData` | `void` |
| `ACT-SwitchActiveSchool` | `SwitchSchoolData` | `School` |
| `ACT-ToggleSchoolModule` | `ToggleModuleData` | `void` |
| `ACT-BulkCreateClasses` | `BulkClassData` | `BulkResult` |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-02-001` | `schools.code` is unique within a tenant, immutable after creation, uppercase alphanumeric, 2–20 characters. It appears in every document number. |
| `BR-CORE-02-002` | A school must have at least one section before any grade level can be created. |
| `BR-CORE-02-003` | `grade_levels.ordinal` is unique per school and defines the promotion sequence. Promotion moves a learner from ordinal *n* to *n+1*. |
| `BR-CORE-02-004` | A school class always belongs to exactly one academic year. Classes are created fresh each year, never carried across. |
| `BR-CORE-02-005` | A school cannot be deleted. It can only be archived, and archiving requires zero active learners and zero open financial periods. |
| `BR-CORE-02-006` | Archiving a school preserves all data and blocks all writes. Historical reports remain fully accessible. |
| `BR-CORE-02-007` | A user may be assigned to many schools but has exactly one `is_primary = 1`. |
| `BR-CORE-02-008` | Switching the active school flushes the permission cache, re-resolves the role set for the new school, resets the session context to that school's current term, and writes an audit entry. |
| `BR-CORE-02-009` | A user cannot switch to a school they are not assigned to. Attempting it throws `UnauthorisedSchoolAccessException` and is logged as a security event. |
| `BR-CORE-02-010` | `base_currency` is immutable once any financial transaction exists for the school. |
| `BR-CORE-02-011` | `centre_number`, where present, is unique across the entire system, not just the tenant. Two schools cannot claim the same ZIMSEC centre. |
| `BR-CORE-02-012` | Disabling a module does not delete its data. Re-enabling restores full access. |
| `BR-CORE-02-013` | A module cannot be enabled while any of its declared dependencies is disabled. The UI shows the dependency chain. |
| `BR-CORE-02-014` | Cross-school queries require `is_group_reporting_enabled` on the tenant **and** the `core.school.view.group` permission. |
| `BR-CORE-02-015` | Class capacity is advisory by default. A configurable setting can make it a hard block on allocation. |

### 5. Screens (Livewire)

| Screen | Component | Permissions |
|---|---|---|
| School list | `Core\Schools\Index` | `core.school.view` |
| School profile | `Core\Schools\Profile` | `core.school.update` |
| School branding | `Core\Schools\Branding` | `core.school.update` — logo, crest, letterhead, colours, live preview |
| Academic structure | `Core\Structure\Manager` | `core.structure.manage` — sections → levels → classes tree with drag reorder |
| Houses | `Core\Houses\Index` | `core.structure.manage` |
| Module entitlement | `Core\Modules\Index` | `core.module.manage` — toggles with dependency warnings |
| User assignment | `Core\Schools\Users` | `core.school.assign_user` |
| School switcher | `Core\SchoolSwitcher` | always available — a header dropdown, not a page |
| Configuration clone | `Core\Schools\Clone` | `core.school.create` |

### 6. API endpoints

```
GET    /api/v1/me/schools                    → schools the caller may access
POST   /api/v1/me/active-school              → switch (body: school ulid)
GET    /api/v1/schools/{ulid}                → public school profile
GET    /api/v1/schools/{ulid}/sections
GET    /api/v1/schools/{ulid}/grade-levels
GET    /api/v1/schools/{ulid}/classes        ?academic_year=&grade_level=
GET    /api/v1/schools/{ulid}/houses
GET    /api/v1/schools/{ulid}/modules        → enabled modules, so clients hide unavailable features
```

`GET /modules` matters more than it looks: the Flutter app uses it to decide whether to render a boarding tab at all.

### 7. Permissions

```
core.school.view              core.school.view.group
core.school.create            core.school.update
core.school.archive           core.school.assign_user
core.structure.view           core.structure.manage
core.module.view              core.module.manage
core.system.bypass_school_scope        ← never granted to a customer role
```

### 8. Settings

| Key | Type | Default | Scope |
|---|---|---|---|
| `structure.class_naming_pattern` | string | `{level} {stream}` | school |
| `structure.enforce_class_capacity` | bool | `false` | school |
| `structure.default_class_capacity` | int | `40` | school |
| `structure.house_system_enabled` | bool | `true` | school |
| `school.allow_cross_school_switch` | bool | `true` | tenant |

### 9. Events published

`SchoolCreated` · `SchoolArchived` · `ActiveSchoolSwitched` · `ModuleEnabled` · `ModuleDisabled` · `ClassCreated` · `SectionCreated`

### 10. Acceptance criteria

```gherkin
AC-CORE-02-001
  Given I am a bursar assigned to School A and School B
  When I switch my active school to School B
  Then my permission set is re-resolved for School B
  And my session context resets to School B's current term
  And an audit entry records the switch

AC-CORE-02-002
  Given I am assigned only to School A
  When I request a resource belonging to School B by its ULID
  Then I receive 403 with code UNAUTHORISED_SCHOOL_ACCESS
  And a security event is logged

AC-CORE-02-003
  Given the Boarding module is disabled for School A
  When I call GET /api/v1/boarding/exeats
  Then I receive 403 with code MODULE_NOT_ENABLED
  And no boarding navigation renders in the admin panel

AC-CORE-02-004
  Given School A has recorded a financial transaction
  When I attempt to change its base currency
  Then the change is rejected with a message explaining why

AC-CORE-02-005
  Given School A has 1 active learner
  When I attempt to archive School A
  Then archiving is blocked and the blocking condition is named
```

---

# CORE-03 · Academic Session & Period Engine ⭐

> The most load-bearing module in the platform. Build it carefully, test it exhaustively, and do not let anyone talk you into shortcuts. Every finance and academic guarantee in the product rests here.

### 1. Scope

**In scope.** Academic years, terms, weeks, holidays. Session context resolution and switching. The dual period state machines. Period locking, closing, and reopening. The roll-over engine. Period snapshots. The reconciliation invariant.

**Out of scope.** What gets rolled over (each module registers its own roll-over handler). Fee structures (`FIN-02`). Promotion rules (`PPL-01`).

### 2. Data model

```sql
academic_years
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
ulid                 CHAR(26)     UNIQUE
school_id            BIGINT       FK  INDEX
name                 VARCHAR(30)  NOT NULL   -- '2026'
starts_on            DATE         NOT NULL
ends_on              DATE         NOT NULL
is_current           TINYINT(1)   NOT NULL DEFAULT 0
academic_state       VARCHAR(20)  NOT NULL   -- planned|open|soft_closed|locked|archived
financial_state      VARCHAR(20)  NOT NULL
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, name)
  INDEX  (school_id, is_current)

terms
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
ulid                 CHAR(26)     UNIQUE
school_id            BIGINT       FK  INDEX
academic_year_id     BIGINT       FK  INDEX
number               TINYINT      NOT NULL   -- 1 | 2 | 3
name                 VARCHAR(40)  NOT NULL   -- 'Term 1'
starts_on            DATE         NOT NULL
ends_on              DATE         NOT NULL
half_term_starts_on  DATE         NULL
half_term_ends_on    DATE         NULL
teaching_days        SMALLINT     NULL       -- computed, used for proration
fee_due_on           DATE         NULL
results_due_on       DATE         NULL
reports_release_on   DATE         NULL
is_current           TINYINT(1)   NOT NULL DEFAULT 0
academic_state       VARCHAR(20)  NOT NULL DEFAULT 'planned'
financial_state      VARCHAR(20)  NOT NULL DEFAULT 'planned'
academic_closed_at   TIMESTAMP    NULL
academic_closed_by   BIGINT       NULL FK → users.id
financial_closed_at  TIMESTAMP    NULL
financial_closed_by  BIGINT       NULL FK → users.id
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, academic_year_id, number)
  INDEX  (school_id, is_current)
  INDEX  (school_id, starts_on, ends_on)

term_weeks
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
school_id            BIGINT       FK  INDEX
term_id              BIGINT       FK  INDEX
week_number          TINYINT      NOT NULL
starts_on            DATE         NOT NULL
ends_on              DATE         NOT NULL
is_teaching_week     TINYINT(1)   NOT NULL DEFAULT 1
label                VARCHAR(60)  NULL   -- 'Half Term', 'Exam Week'

calendar_holidays
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
school_id            BIGINT       FK  INDEX
academic_year_id     BIGINT       FK  INDEX
name                 VARCHAR(100) NOT NULL
starts_on            DATE         NOT NULL
ends_on              DATE         NOT NULL
type                 VARCHAR(20)  NOT NULL  -- public | school | half_term

period_state_transitions              -- IMMUTABLE
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
school_id            BIGINT       FK  INDEX
term_id              BIGINT       FK  INDEX
period_type          VARCHAR(10)  NOT NULL  -- academic | financial
from_state           VARCHAR(20)  NOT NULL
to_state             VARCHAR(20)  NOT NULL
reason               TEXT         NULL      -- mandatory for reopen
performed_by         BIGINT       NOT NULL FK → users.id
approved_by          BIGINT       NULL FK → users.id     -- second approver on reopen
ip_address           VARCHAR(45)
occurred_at          TIMESTAMP    NOT NULL
  -- no updated_at, no deleted_at: append-only

period_snapshots                      -- the forensic anchor
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
ulid                 CHAR(26)     UNIQUE
school_id            BIGINT       FK  INDEX
academic_year_id     BIGINT       FK
term_id              BIGINT       FK  INDEX
snapshot_type        VARCHAR(30)  NOT NULL  -- pre_close | post_close | rollover
taken_at             TIMESTAMP    NOT NULL
taken_by             BIGINT       FK → users.id
payload              JSON         NOT NULL  -- trial balance, learner balances, key registers
payload_hash         CHAR(64)     NOT NULL  -- SHA-256 of canonical payload
previous_hash        CHAR(64)     NULL      -- chains snapshots — tamper-evident
row_counts           JSON         NOT NULL  -- per-table counts at snapshot time
  INDEX (school_id, term_id, snapshot_type)

period_rollovers
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
ulid                 CHAR(26)     UNIQUE
school_id            BIGINT       FK  INDEX
from_term_id         BIGINT       FK
to_term_id           BIGINT       FK
status               VARCHAR(20)  NOT NULL -- pending|validating|running|completed|failed|rolled_back
started_at           TIMESTAMP
completed_at         TIMESTAMP    NULL
initiated_by         BIGINT       FK → users.id
approved_by          BIGINT       NULL FK → users.id
validation_report    JSON         NULL
step_log             JSON         NULL     -- per-handler outcome
exception_report     JSON         NULL
report_document_id   BIGINT       NULL FK → documents.id
  INDEX (school_id, status)

rollover_handlers                     -- module registration, seeded from code
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
module_code          VARCHAR(20)  NOT NULL
handler_class        VARCHAR(255) NOT NULL
sort_order           SMALLINT     NOT NULL
is_blocking          TINYINT(1)   NOT NULL DEFAULT 1  -- failure aborts the rollover
description          VARCHAR(255)

user_session_preferences
──────────────────────────────────────────────────────────────────
id                   BIGINT PK
user_id              BIGINT       FK  INDEX
school_id            BIGINT       FK
academic_year_id     BIGINT       FK
term_id              BIGINT       NULL FK
updated_at           TIMESTAMP
  UNIQUE (user_id, school_id)
```

### 3. The state machines

```
ACADEMIC                                    FINANCIAL
────────                                    ─────────
PLANNED                                     PLANNED
   │ activate                                  │ activate
   ▼                                           ▼
OPEN ──────── marks entered, published ──►  OPEN ──────── invoices, receipts ──►
   │ close_academic                            │ soft_close
   ▼                                           ▼
SOFT_CLOSED  amendments need approval       SOFT_CLOSED  late receipts need approval
   │ lock                                      │ lock  (requires close checklist)
   ▼                                           ▼
LOCKED       no writes                      LOCKED       no writes
   │ archive                                   │ archive
   ▼                                           ▼
ARCHIVED                                    ARCHIVED

Reopen: LOCKED → OPEN  and  SOFT_CLOSED → OPEN
        requires: permission + reason + second approver + audit + notification
```

**Legal transitions table:**

| From | To | Guard |
|---|---|---|
| `PLANNED` | `OPEN` | Prior term is at least `SOFT_CLOSED`; calendar dates set |
| `OPEN` | `SOFT_CLOSED` | Term end date has passed, or override permission |
| `SOFT_CLOSED` | `LOCKED` | **Financial:** the full close checklist passes. **Academic:** all marks submitted and published. |
| `SOFT_CLOSED` | `OPEN` | `core.period.reopen` + reason |
| `LOCKED` | `OPEN` | `core.period.reopen` + reason + **second approver** + head notification |
| `LOCKED` | `ARCHIVED` | Retention age reached; configurable |

Any other transition throws `InvalidStateTransitionException`.

### 4. Domain actions

| Action | Input | Output | Notes |
|---|---|---|---|
| `ACT-CreateAcademicYear` | `CreateYearData` | `AcademicYear` | Optionally auto-generates three terms from a template |
| `ACT-CreateTerm` | `CreateTermData` | `Term` | Generates weeks, computes teaching days |
| `ACT-GenerateTermWeeks` | `Term` | `Collection<TermWeek>` | Excludes holidays and half-term |
| `ACT-SwitchSession` | `SwitchSessionData` | `SessionContextResult` | Validates access, persists preference |
| `ACT-TransitionPeriodState` | `TransitionPeriodData` | `Term` | The single gateway for all state changes |
| `ACT-RunPeriodCloseChecklist` | `Term`, `PeriodType` | `ChecklistResult` | Read-only. Runs every registered validator. |
| `ACT-TakePeriodSnapshot` | `SnapshotData` | `PeriodSnapshot` | Canonical JSON, SHA-256, hash-chained |
| `ACT-InitiateRollover` | `InitiateRolloverData` | `PeriodRollover` | Creates the record, runs validation, does not execute |
| `ACT-ExecuteRollover` | `ExecuteRolloverData` | `RolloverResult` | Dispatches `JOB-RunPeriodRollover` |
| `ACT-RollbackRollover` | `RollbackRolloverData` | `void` | Only while `status = failed` and within the retention window |
| `ACT-VerifyRolloverInvariant` | `Term`, `Term` | `InvariantResult` | The Σ-closing = Σ-opening check, per currency |
| `ACT-ReopenPeriod` | `ReopenPeriodData` | `Term` | Two-approver flow through `CORE-07` |

### 5. The roll-over handler contract

Every module that owns period-bound data registers a handler. `CORE-03` orchestrates; it knows nothing about fees or marks.

```php
interface RolloverHandler
{
    public function moduleCode(): string;
    public function sortOrder(): int;
    public function isBlocking(): bool;

    /** Read-only. Runs before anything is written. */
    public function validate(Term $from, Term $to): ValidationResult;

    /** Executes inside the rollover transaction. */
    public function execute(Term $from, Term $to, RolloverContext $ctx): HandlerResult;

    /** Must undo execute() exactly. Called on failure of a later handler. */
    public function rollback(Term $from, Term $to, RolloverContext $ctx): void;
}
```

**Registered handler order for the full product:**

| Order | Module | Handler | Blocking |
|---|---|---|---|
| 10 | `FIN-01` | `AssertTrialBalanceHandler` | ✅ |
| 20 | `FIN-04` | `AssertTillSessionsClosedHandler` | ✅ |
| 30 | `FIN-04` | `AssertSuspenseClearedHandler` | ✅ |
| 40 | `FIN-05` | `AssertReconciliationCompleteHandler` | ✅ |
| 50 | `FIN-06` | `RevalueForeignBalancesHandler` | ✅ |
| 60 | `CORE-03` | `TakePreCloseSnapshotHandler` | ✅ |
| 70 | `FIN-03` | `CarryForwardLearnerBalancesHandler` | ✅ |
| 80 | `FIN-03` | `CarryForwardCreditsHandler` | ✅ |
| 90 | `FIN-08` | `CarryForwardSupplierBalancesHandler` | ✅ |
| 100 | `PPL-01` | `PromoteLearnersHandler` | ❌ (drafts only) |
| 110 | `ACA-02` | `CloneClassAllocationsHandler` | ❌ |
| 120 | `FIN-02` | `CloneFeeStructuresHandler` | ❌ |
| 130 | `ACA-03` | `CloneTimetableTemplateHandler` | ❌ |
| 140 | `BRD-01` | `CloneHostelAllocationsHandler` | ❌ |
| 900 | `CORE-03` | `TakePostCloseSnapshotHandler` | ✅ |
| 910 | `CORE-03` | `VerifyInvariantHandler` | ✅ |
| 920 | `CORE-03` | `GenerateRolloverReportHandler` | ❌ |

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-03-001` | A school has exactly one `is_current = 1` academic year and exactly one `is_current = 1` term at any moment. Enforced by a partial unique index or an application-level guard inside a transaction. |
| `BR-CORE-03-002` | Terms within a year may not overlap. Overlap validation runs on create and update. |
| `BR-CORE-03-003` | `terms_per_year` defaults to 3 and is configurable, but the seeded Zimbabwe pack always creates 3. |
| `BR-CORE-03-004` | `teaching_days` is computed from term dates minus weekends, holidays, and half-term. It is the denominator for all proration. Recomputed whenever dates or holidays change, with a warning if any invoice already used the old figure. |
| `BR-CORE-03-005` | The session context is resolved on **every** request. A request that cannot resolve one is rejected with `MISSING_SESSION_CONTEXT`, never defaulted silently. |
| `BR-CORE-03-006` | A user may only switch to a session belonging to their active school. |
| `BR-CORE-03-007` | Switching to a non-current term sets `SessionContext::isCurrentLiveTerm() = false`, which **must** render the historical-view banner. Every admin layout test asserts the banner's presence. |
| `BR-CORE-03-008` | All state transitions go through `ACT-TransitionPeriodState`. Direct writes to `terms.academic_state` or `terms.financial_state` are forbidden and caught by a model observer that throws. |
| `BR-CORE-03-009` | Every transition writes an immutable row to `period_state_transitions`. That table has no update or delete path. |
| `BR-CORE-03-010` | Reopening from `LOCKED` requires `core.period.reopen`, a reason of at least 20 characters, and a second approver holding the same permission who is **not** the initiator. |
| `BR-CORE-03-011` | Reopening notifies the head, the bursar, and the tenant owner immediately, on all configured channels. |
| `BR-CORE-03-012` | Every transaction posted into a reopened period is tagged `is_prior_period_adjustment = 1` and appears on its own line in every subsequent report. |
| `BR-CORE-03-013` | `financial_state` cannot reach `LOCKED` unless the close checklist returns zero blocking failures. |
| `BR-CORE-03-014` | The financial close checklist comprises, at minimum: trial balance balances per currency; suspense account is zero or explicitly acknowledged with a reason; every till session is closed; gateway reconciliation has no unresolved exceptions; FX revaluation is posted; no draft invoices remain. |
| `BR-CORE-03-015` | A snapshot's `payload_hash` is SHA-256 over a canonically serialised payload (sorted keys, fixed number formatting). `previous_hash` chains to the school's prior snapshot, making silent tampering detectable. |
| `BR-CORE-03-016` | Snapshots are never deleted, never edited, and are excluded from all cascade deletes. |
| `BR-CORE-03-017` | The roll-over runs in a single database transaction. Any blocking handler failure rolls back the entire operation and leaves both terms exactly as they were. |
| `BR-CORE-03-018` | Validation runs completely, for every handler, before execution begins. A roll-over is never half-validated. |
| `BR-CORE-03-019` | **The invariant:** for every school, every currency, `Σ(closing balances of term N) ≡ Σ(opening balances of term N+1)`, with FX movement accounted separately. Failure aborts and rolls back the roll-over. |
| `BR-CORE-03-020` | `PeriodGuard::assertWritable()` runs on save for every session-bound model: `OPEN` → allow; `SOFT_CLOSED` → allow only with an approved override token; `LOCKED`/`ARCHIVED` → throw `PeriodLockedException`. |
| `BR-CORE-03-021` | Reads from any period state are always permitted, subject to normal permissions. Locking restricts writing, never viewing. |
| `BR-CORE-03-022` | A roll-over cannot start while another is `running` for the same school. Enforced by an atomic lock. |
| `BR-CORE-03-023` | Non-blocking handlers that fail are recorded in the exception report; the roll-over completes and the failures are presented for manual follow-up. |
| `BR-CORE-03-024` | Promotion produces **draft** class allocations requiring human confirmation. Learners are never auto-committed into next year's classes. |

### 7. Screens (Livewire)

| Screen | Component | Permission |
|---|---|---|
| Academic years | `Core\Sessions\Years` | `core.session.view` |
| Year setup wizard | `Core\Sessions\YearWizard` | `core.session.manage` — creates year + 3 terms + weeks + holidays |
| Term detail | `Core\Sessions\TermDetail` | `core.session.view` |
| Calendar & holidays | `Core\Sessions\Calendar` | `core.session.manage` |
| Session switcher | `Core\SessionSwitcher` | always — header dropdown, current term flagged, closed terms marked |
| Period control panel | `Core\Sessions\PeriodControl` | `core.period.view` — dual state display, transition buttons, checklist results inline |
| Close checklist | `Core\Sessions\CloseChecklist` | `core.period.close` — live pass/fail per item with drill-through to offending records |
| Roll-over wizard | `Core\Sessions\RolloverWizard` | `core.period.rollover` — validate → review → approve → execute → report |
| Roll-over history | `Core\Sessions\RolloverHistory` | `core.period.view` |
| Snapshot browser | `Core\Sessions\Snapshots` | `core.period.view` — hash verification button |
| Transition log | `Core\Sessions\TransitionLog` | `core.period.view` |

**Historical-view banner specification.** Rendered by the admin layout whenever `SessionContext::isCurrentLiveTerm()` is false:

- Full-width bar, amber for `SOFT_CLOSED`, red for `LOCKED`/`ARCHIVED`, fixed below the header.
- Text: `HISTORICAL VIEW — {year} {term} ({STATE}). This period is read-only.` plus a **Return to current term** button.
- The page background gains a subtle tint so the state is unmistakable even peripherally.
- Present on **every** admin page without exception. Asserted in a shared layout test.

### 8. API endpoints

```
GET  /api/v1/sessions/academic-years                 ?school=
GET  /api/v1/sessions/academic-years/{ulid}/terms
GET  /api/v1/sessions/current                        → active year + term + states + is_live
POST /api/v1/sessions/switch                         { academic_year, term }
GET  /api/v1/sessions/terms/{ulid}/calendar          → weeks, holidays, key dates
```

`GET /sessions/current` returns the period states so clients can disable write affordances rather than letting a teacher type marks for ten minutes and then hit a 422.

```json
{
  "academic_year": { "id": "01JB...", "name": "2026" },
  "term": { "id": "01JC...", "name": "Term 3", "number": 3,
            "starts_on": "2026-09-08", "ends_on": "2026-12-04" },
  "academic_state": "open",
  "financial_state": "open",
  "is_current_live_term": true,
  "can_write_academic": true,
  "can_write_financial": true
}
```

### 9. Permissions

```
core.session.view          core.session.manage
core.period.view           core.period.close
core.period.reopen         core.period.approve_reopen
core.period.rollover       core.period.override_soft_close
```

`core.period.reopen` and `core.period.approve_reopen` are deliberately separate so one person cannot do both.

### 10. Settings

| Key | Type | Default | Scope |
|---|---|---|---|
| `academic.terms_per_year` | int | `3` | school |
| `academic.auto_soft_close_days_after_term_end` | int | `14` | school |
| `academic.require_dual_approval_for_reopen` | bool | `true` | school (**cannot be disabled on Enterprise tier**) |
| `academic.snapshot_retention_years` | int | `10` | tenant |
| `academic.archive_after_years` | int | `7` | tenant |
| `academic.block_writes_in_soft_closed` | bool | `false` | school |
| `academic.week_starts_on` | enum | `monday` | school |

### 11. Events published

`AcademicYearCreated` · `TermCreated` · `SessionSwitched` · `PeriodStateChanged` · `PeriodClosed` · `PeriodReopened` · `RolloverStarted` · `RolloverCompleted` · `RolloverFailed` · `SnapshotTaken`

### 12. Jobs

| Job | Trigger | Behaviour |
|---|---|---|
| `JOB-RunPeriodRollover` | Manual | Long-running, single transaction, progress broadcast, `timeout=3600`, `tries=1` |
| `JOB-AutoSoftClosePeriods` | Daily 02:00 | Soft-closes terms past `auto_soft_close_days_after_term_end` |
| `JOB-VerifyRolloverInvariants` | Weekly | Re-runs the invariant for all completed roll-overs; alerts on drift |
| `JOB-VerifySnapshotChain` | Weekly | Recomputes the hash chain; alerts on any break |
| `JOB-NotifyUpcomingPeriodClose` | Daily | Reminds the bursar of the approaching close and outstanding checklist items |

### 13. Acceptance criteria

```gherkin
AC-CORE-03-001
  Given the 2025 Term 3 financial period is LOCKED
  When I attempt to create a receipt dated in that term
  Then the write is rejected with code PERIOD_LOCKED
  And no journal entry is created

AC-CORE-03-002
  Given I hold core.period.reopen
  And 2025 Term 3 is LOCKED
  When I request a reopen with a reason
  Then the period remains LOCKED until a different user with
       core.period.approve_reopen approves it
  And on approval the head and bursar are notified

AC-CORE-03-003
  Given 2026 Term 1 closes with learner balances totalling USD 84,320.00
  When the roll-over to Term 2 completes
  Then Term 2 opening balances total exactly USD 84,320.00
  And every carried balance links to its source term and source journal lines

AC-CORE-03-004
  Given a blocking roll-over handler throws
  When the roll-over runs
  Then the entire transaction is rolled back
  And both terms are in their original states
  And the failure is recorded on period_rollovers with the failing handler named

AC-CORE-03-005
  Given I switch my session to 2025 Term 2
  When I load any admin page
  Then the historical-view banner is displayed
  And every create and edit control on the page is disabled

AC-CORE-03-006
  Given the trial balance for 2026 Term 1 does not balance
  When I run the financial close checklist
  Then the checklist fails
  And the LOCK transition is unavailable
  And the imbalance is reported with a drill-through to the unbalanced journals

AC-CORE-03-007
  Given a period snapshot was taken on 2026-04-30
  When I verify its hash six months later
  Then the hash matches
  And the chain to the previous snapshot is intact

AC-CORE-03-008
  Given 2025 Term 3 was reopened and a receipt was posted into it
  When I generate the 2025 Term 3 income statement
  Then the receipt appears
  And it is shown on a separate prior-period adjustment line
  And the report states that the period was reopened, by whom, and when

AC-CORE-03-009
  Given a roll-over is already running for School A
  When a second user initiates a roll-over for School A
  Then the request is rejected with a clear message
  And no second roll-over record is created
```

---

# CORE-04 · Settings, Feature Flags & Custom Fields

### 1. Scope

**In scope.** The hierarchical settings engine, typed schema registry, auto-generated settings UI, feature flags, module entitlement resolution, custom field definitions and values, configuration profile export/import.

**Out of scope.** Module toggling UI (`CORE-02`). Subscription-driven entitlement source (`SAA-01`).

### 2. Data model

```sql
setting_definitions                   -- registered in code, synced on deploy
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
key                 VARCHAR(120) NOT NULL UNIQUE   -- 'academic.terms_per_year'
module_code         VARCHAR(20)  NOT NULL
group_key           VARCHAR(60)  NOT NULL          -- UI grouping
label               VARCHAR(150) NOT NULL
description         TEXT         NULL
data_type           VARCHAR(20)  NOT NULL  -- string|int|float|bool|json|array|enum|money|date|time
default_value       TEXT         NULL
validation_rules    VARCHAR(255) NULL      -- Laravel rule string
options             JSON         NULL      -- for enum/select
ui_control          VARCHAR(30)  NOT NULL  -- text|number|toggle|select|multiselect|colour|
                                           -- date|time|textarea|file|key_value
lowest_scope        VARCHAR(20)  NOT NULL  -- system|tenant|school|section|year|term|user
is_encrypted        TINYINT(1)   NOT NULL DEFAULT 0   -- gateway keys, API secrets
is_locked_on_tier   VARCHAR(20)  NULL      -- tier at/above which the value cannot be changed
sort_order          SMALLINT     NOT NULL DEFAULT 0
created_at, updated_at

setting_values
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
setting_key         VARCHAR(120) NOT NULL  INDEX
scope_type          VARCHAR(20)  NOT NULL  -- tenant|school|section|academic_year|term|user
scope_id            BIGINT       NOT NULL
value               TEXT         NULL      -- encrypted at rest when is_encrypted
set_by              BIGINT       NULL FK → users.id
created_at, updated_at
  UNIQUE (setting_key, scope_type, scope_id)
  INDEX  (scope_type, scope_id)

setting_change_log                    -- append-only
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
setting_key         VARCHAR(120) NOT NULL
scope_type          VARCHAR(20)  NOT NULL
scope_id            BIGINT       NOT NULL
old_value           TEXT         NULL     -- redacted when is_encrypted
new_value           TEXT         NULL
changed_by          BIGINT       FK → users.id
ip_address          VARCHAR(45)
changed_at          TIMESTAMP    NOT NULL

feature_flags
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
key                 VARCHAR(80)  NOT NULL UNIQUE
name                VARCHAR(150) NOT NULL
description         TEXT
is_globally_enabled TINYINT(1)   NOT NULL DEFAULT 0
rollout_percentage  TINYINT      NOT NULL DEFAULT 0
created_at, updated_at

feature_flag_overrides
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
feature_flag_id     BIGINT       FK INDEX
scope_type          VARCHAR(20)  NOT NULL  -- tenant|school|user
scope_id            BIGINT       NOT NULL
is_enabled          TINYINT(1)   NOT NULL
  UNIQUE (feature_flag_id, scope_type, scope_id)

custom_field_definitions
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK  INDEX
entity_type         VARCHAR(60)  NOT NULL  -- 'student','guardian','staff','invoice',...
key                 VARCHAR(60)  NOT NULL  -- snake_case, immutable
label               VARCHAR(150) NOT NULL
description         VARCHAR(255) NULL
data_type           VARCHAR(20)  NOT NULL  -- text|textarea|number|decimal|date|datetime|
                                           -- bool|select|multiselect|file|money|email|phone
options             JSON         NULL
validation_rules    VARCHAR(255) NULL
is_required         TINYINT(1)   NOT NULL DEFAULT 0
is_searchable       TINYINT(1)   NOT NULL DEFAULT 0   -- generates an indexed column
is_exposed_in_api   TINYINT(1)   NOT NULL DEFAULT 1
is_printable        TINYINT(1)   NOT NULL DEFAULT 0
visible_to_roles    JSON         NULL      -- null = all with entity view permission
group_label         VARCHAR(80)  NULL
sort_order          SMALLINT     NOT NULL DEFAULT 0
is_active           TINYINT(1)   NOT NULL DEFAULT 1
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, entity_type, key)

custom_field_values
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK  INDEX
definition_id       BIGINT       FK  INDEX
entity_type         VARCHAR(60)  NOT NULL
entity_id           BIGINT       NOT NULL
value_text          TEXT         NULL
value_number        DECIMAL(20,6) NULL
value_date          DATETIME     NULL
value_bool          TINYINT(1)   NULL
value_json          JSON         NULL
created_at, updated_at
  UNIQUE (definition_id, entity_id)
  INDEX  (school_id, entity_type, entity_id)

configuration_profiles                -- clone a school's setup
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
tenant_id           BIGINT       FK
name                VARCHAR(150) NOT NULL
description         TEXT
source_school_id    BIGINT       NULL FK → schools.id
payload             JSON         NOT NULL   -- settings, custom fields, templates, roles
version             VARCHAR(20)  NOT NULL
created_by, created_at
```

### 3. Resolution algorithm ⭐

```php
final class SettingResolver
{
    public function get(string $key, ?ScopeChain $chain = null): mixed
    {
        $chain ??= ScopeChain::fromCurrentContext();
        // user → term → academic_year → section → school → tenant → tier → system default

        foreach ($chain->descendingSpecificity() as $scope) {
            $cacheKey = "setting:{$key}:{$scope->type}:{$scope->id}";
            $hit = Cache::get($cacheKey, self::MISS);
            if ($hit !== self::MISS) {
                return $hit === null ? $this->definitionDefault($key) : $this->cast($key, $hit);
            }
            $row = SettingValue::where(...)->first();
            Cache::put($cacheKey, $row?->value, self::TTL);
            if ($row !== null) return $this->cast($key, $row->value);
        }
        return $this->definitionDefault($key);
    }
}
```

**Cache invalidation.** Writing a value at scope *S* flushes that key at *S* and every scope **below** it (more specific scopes can't be affected, but the tag-based flush is simpler and safe). Tags: `setting:{key}` and `school:{id}:settings`.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-04-001` | A setting must be registered by a module service provider before a value can be stored for it. Unregistered keys throw. |
| `BR-CORE-04-002` | Resolution walks from most specific to least. The first defined value wins. If none is defined, the definition default applies. |
| `BR-CORE-04-003` | A value cannot be set at a scope more general than `lowest_scope`. Attempting it throws `InvalidSettingScopeException`. |
| `BR-CORE-04-004` | Every value is validated against `validation_rules` and `data_type` on write. Invalid values are never stored. |
| `BR-CORE-04-005` | Settings marked `is_encrypted` are encrypted at rest, redacted in the change log, redacted in exports, and shown masked in the UI. |
| `BR-CORE-04-006` | Every change writes to `setting_change_log`. That table is append-only. |
| `BR-CORE-04-007` | Settings marked `is_locked_on_tier` cannot be changed by a customer at or above that tier (e.g. dual-approval-for-reopen on Enterprise). |
| `BR-CORE-04-008` | The settings admin UI is generated entirely from `setting_definitions`. Adding a setting requires no new screen. |
| `BR-CORE-04-009` | `custom_field_definitions.key` is immutable after creation and unique per `(school, entity_type)`. |
| `BR-CORE-04-010` | Deactivating a custom field hides it from forms but preserves all stored values. Deletion is only permitted when zero values exist. |
| `BR-CORE-04-011` | Marking a field `is_searchable` provisions a generated column and index via a queued migration; the field is unavailable for search until that completes. |
| `BR-CORE-04-012` | Custom field values are automatically included in the entity's API resource, Livewire forms, exports, and — when `is_printable` — document templates, with no per-entity code. |
| `BR-CORE-04-013` | Required custom fields are enforced in the entity's own validation, exactly as core fields are. |
| `BR-CORE-04-014` | A configuration profile export excludes all learner, staff, and financial data. It carries structure and configuration only. |
| `BR-CORE-04-015` | Importing a profile into a school with existing data is additive by default; overwrite requires explicit confirmation per section and is logged. |
| `BR-CORE-04-016` | Feature flag resolution order: user override → school override → tenant override → percentage rollout (hashed on school ID for stability) → global default. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Settings browser | `Core\Settings\Index` | `core.settings.view` — grouped, searchable, shows the scope each value resolves from |
| Setting editor | `Core\Settings\Edit` | `core.settings.update` — inline, with "inherited from Tenant" indicators and a Reset to inherited action |
| Change history | `Core\Settings\History` | `core.settings.view` |
| Custom fields | `Core\CustomFields\Index` | `core.custom_field.manage` — per entity type |
| Custom field builder | `Core\CustomFields\Builder` | `core.custom_field.manage` — type, validation, visibility, live preview |
| Feature flags | `Core\FeatureFlags\Index` | `core.feature_flag.manage` (vendor) |
| Configuration profiles | `Core\Profiles\Index` | `core.profile.manage` — export, import, clone from school |

### 6. API endpoints

```
GET /api/v1/settings/public              → non-sensitive, client-relevant settings for the active school
GET /api/v1/custom-fields/{entity_type}  → definitions so clients render dynamic forms
```

`GET /settings/public` is deliberately narrow: branding, currency, term dates, feature availability, portal toggles. Anything `is_encrypted` or operationally sensitive never appears.

### 7. Permissions

```
core.settings.view          core.settings.update
core.custom_field.view      core.custom_field.manage
core.feature_flag.view      core.feature_flag.manage
core.profile.export         core.profile.import
```

### 8. Events published

`SettingChanged` · `CustomFieldDefined` · `CustomFieldDeactivated` · `FeatureFlagToggled` · `ConfigurationProfileImported`

### 9. Acceptance criteria

```gherkin
AC-CORE-04-001
  Given tenant-level academic.terms_per_year is 3
  And School B overrides it to 2
  When School A resolves the setting
  Then it returns 3
  And School B returns 2

AC-CORE-04-002
  Given a setting has lowest_scope = 'school'
  When I attempt to set a value at user scope
  Then the write is rejected with INVALID_SETTING_SCOPE

AC-CORE-04-003
  Given I define a required custom field "Parish" on the student entity
  When I create a student without a Parish value
  Then validation fails with the field's label in the error message
  And the field appears in the student API resource for that school only

AC-CORE-04-004
  Given a setting is marked is_encrypted
  When I view it in the UI, the change log, and a configuration export
  Then the value is masked in all three
  And the stored value is encrypted at rest

AC-CORE-04-005
  Given School A has a complete configuration
  When I export it as a profile and import it into new School C
  Then School C has the same settings, custom fields, roles, and templates
  And no learner, staff, or financial data was transferred
```

---

# CORE-05 · Identity, Authentication & RBAC

### 1. Scope

**In scope.** Users, dual-guard authentication, Sanctum token lifecycle, device management, 2FA, password policy, role templates, scoped permissions, policies, impersonation, account linking.

**Out of scope.** Learner and guardian *profile* data (`PPL-01`, `PPL-03`). This module owns the **login identity** only; a learner's academic record is not a user attribute.

### 2. Data model

```sql
users                                 -- global identity, NOT school-scoped
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
tenant_id             BIGINT       FK  INDEX
first_name            VARCHAR(80)  NOT NULL
last_name             VARCHAR(80)  NOT NULL
other_names           VARCHAR(120) NULL
email                 VARCHAR(150) NULL   -- nullable: many parents have no email
phone                 VARCHAR(30)  NULL   -- E.164, primary identifier in Zimbabwe
username              VARCHAR(60)  NULL
password              VARCHAR(255) NULL   -- null for phone-OTP-only accounts
avatar_path           VARCHAR(255) NULL
user_type             VARCHAR(20)  NOT NULL -- staff|parent|student|alumni|supplier|vendor
locale                VARCHAR(10)  NOT NULL DEFAULT 'en_ZW'
status                VARCHAR(20)  NOT NULL -- active|inactive|suspended|locked|pending
must_change_password  TINYINT(1)   NOT NULL DEFAULT 0
password_changed_at   TIMESTAMP    NULL
email_verified_at     TIMESTAMP    NULL
phone_verified_at     TIMESTAMP    NULL
two_factor_secret     TEXT         NULL   -- encrypted
two_factor_recovery   TEXT         NULL   -- encrypted
two_factor_confirmed_at TIMESTAMP  NULL
last_login_at         TIMESTAMP    NULL
last_login_ip         VARCHAR(45)  NULL
failed_login_count    SMALLINT     NOT NULL DEFAULT 0
locked_until          TIMESTAMP    NULL
created_by, updated_by, created_at, updated_at, deleted_at
  UNIQUE (tenant_id, email)      -- where email NOT NULL
  UNIQUE (tenant_id, phone)      -- where phone NOT NULL
  UNIQUE (tenant_id, username)   -- where username NOT NULL
  INDEX  (tenant_id, user_type, status)

personal_access_tokens                -- Sanctum, extended
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
tokenable_type        VARCHAR(255)
tokenable_id          BIGINT
name                  VARCHAR(150) NOT NULL   -- device name
token                 CHAR(64)     UNIQUE
abilities             TEXT         NULL
school_id             BIGINT       NULL FK   -- token bound to a school
device_id             VARCHAR(120) NULL       -- stable client-generated identifier
device_platform       VARCHAR(30)  NULL       -- ios|android|web
device_model          VARCHAR(100) NULL
app_version           VARCHAR(20)  NULL
last_used_at          TIMESTAMP    NULL
last_used_ip          VARCHAR(45)  NULL
expires_at            TIMESTAMP    NULL
revoked_at            TIMESTAMP    NULL
revoked_by            BIGINT       NULL FK → users.id
created_at, updated_at
  INDEX (tokenable_type, tokenable_id, revoked_at)

refresh_tokens
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
user_id               BIGINT       FK INDEX
access_token_id       BIGINT       FK → personal_access_tokens.id
token_hash            CHAR(64)     UNIQUE
device_id             VARCHAR(120)
expires_at            TIMESTAMP    NOT NULL
used_at               TIMESTAMP    NULL      -- single-use; reuse = theft signal
replaced_by_id        BIGINT       NULL
created_at

roles                                 -- school-scoped templates
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       NULL FK   -- NULL = system template
name                  VARCHAR(80)  NOT NULL
display_name          VARCHAR(120) NOT NULL
description           VARCHAR(255)
guard_name            VARCHAR(30)  NOT NULL DEFAULT 'web'
is_system             TINYINT(1)   NOT NULL DEFAULT 0   -- cannot be deleted
is_vendor_only        TINYINT(1)   NOT NULL DEFAULT 0
category              VARCHAR(30)  NOT NULL
created_at, updated_at
  UNIQUE (school_id, name, guard_name)

permissions
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
name                  VARCHAR(120) NOT NULL   -- 'academic.result.enter'
module_code           VARCHAR(20)  NOT NULL
resource              VARCHAR(60)  NOT NULL
action                VARCHAR(40)  NOT NULL
description           VARCHAR(255)
is_dangerous          TINYINT(1)   NOT NULL DEFAULT 0
guard_name            VARCHAR(30)  NOT NULL DEFAULT 'web'
  UNIQUE (name, guard_name)

role_permission_scopes                -- the scope layer over spatie
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
role_id               BIGINT       FK INDEX
permission_id         BIGINT       FK INDEX
scope                 VARCHAR(20)  NOT NULL  -- own|assigned|section|school
  UNIQUE (role_id, permission_id)

model_has_roles                       -- spatie, extended with school_id
──────────────────────────────────────────────────────────────────
role_id               BIGINT
model_type            VARCHAR(255)
model_id              BIGINT
school_id             BIGINT       NOT NULL FK   -- ← the extension
  PRIMARY KEY (role_id, model_id, model_type, school_id)
  INDEX (model_id, school_id)

login_attempts                        -- append-only
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
identifier            VARCHAR(150) NOT NULL  -- email/phone/username attempted
user_id               BIGINT       NULL FK
guard                 VARCHAR(20)  NOT NULL  -- web | sanctum
was_successful        TINYINT(1)   NOT NULL
failure_reason        VARCHAR(60)  NULL
ip_address            VARCHAR(45)
user_agent            VARCHAR(500)
attempted_at          TIMESTAMP    NOT NULL
  INDEX (identifier, attempted_at)
  INDEX (ip_address, attempted_at)

impersonation_sessions                -- append-only
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
impersonator_id       BIGINT       FK → users.id
impersonated_id       BIGINT       FK → users.id
school_id             BIGINT       NULL FK
reason                TEXT         NOT NULL
ticket_reference      VARCHAR(60)  NULL
consent_reference     VARCHAR(120) NULL    -- customer consent record
started_at            TIMESTAMP    NOT NULL
expires_at            TIMESTAMP    NOT NULL
ended_at              TIMESTAMP    NULL
actions_performed     JSON         NULL

user_account_links                    -- parent ↔ learner, staff ↔ teacher identity
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
user_id               BIGINT       FK INDEX
linked_type           VARCHAR(60)  NOT NULL   -- 'student','guardian','staff'
linked_id             BIGINT       NOT NULL
is_active             TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (user_id, linked_type, linked_id)
```

### 3. Domain actions

| Action | Input | Output |
|---|---|---|
| `ACT-CreateUser` | `CreateUserData` | `User` |
| `ACT-AuthenticateWeb` | `WebLoginData` | `AuthResult` |
| `ACT-IssueApiToken` | `IssueTokenData` | `TokenPair` (access + refresh) |
| `ACT-RefreshApiToken` | `RefreshTokenData` | `TokenPair` |
| `ACT-RevokeToken` | `RevokeTokenData` | `void` |
| `ACT-RevokeAllUserTokens` | `User` | `int` |
| `ACT-EnableTwoFactor` / `ACT-ConfirmTwoFactor` / `ACT-DisableTwoFactor` | DTOs | — |
| `ACT-RequestOtp` / `ACT-VerifyOtp` | `OtpData` | `AuthResult` |
| `ACT-ChangePassword` / `ACT-ResetPassword` | DTOs | `void` |
| `ACT-AssignRole` / `ACT-RevokeRole` | `RoleAssignmentData` | `void` |
| `ACT-CloneRoleTemplate` | `CloneRoleData` | `Role` |
| `ACT-UpdateRolePermissions` | `RolePermissionData` | `Role` |
| `ACT-StartImpersonation` / `ACT-EndImpersonation` | DTOs | — |
| `ACT-LinkAccount` | `LinkAccountData` | `void` |

### 4. Authentication flows

**Web (Livewire admin):**
```
identifier + password → throttle check → credential check → status check
  → 2FA challenge (if enrolled or role-required) → session established
  → school context resolved from primary assignment → session context resolved
```

**API — password grant (Next.js and Flutter):**
```
POST /api/v1/auth/login
  { identifier, password, device: { id, platform, model, app_version } }
→ throttle → credentials → status → 2FA if required
→ issue access token (TTL 60 min) + refresh token (TTL 30 days, single-use)
→ response includes user, schools, active school, session context, abilities
```

**API — OTP grant (parents without email or password):**
```
POST /api/v1/auth/otp/request   { phone }
   → 6-digit code by SMS/WhatsApp, TTL 5 min, max 3 attempts, 60s resend cooldown
POST /api/v1/auth/otp/verify    { phone, code, device }
   → same token pair as password grant
```

This matters: a large share of Zimbabwean parents will never set a password. Phone + OTP is their real login, and it must be first-class rather than a fallback.

**Refresh rotation:**
```
POST /api/v1/auth/refresh  { refresh_token }
→ validate hash, not expired, not used
→ mark used, issue new pair, link replaced_by_id
→ IF an already-used refresh token is presented:
     revoke the ENTIRE device token family, log a security event, notify the user
```

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-05-001` | A user must have at least one of email, phone, or username. All three may be present. |
| `BR-CORE-05-002` | Email, phone, and username are each unique **within a tenant**, not globally. Two tenants may legitimately have the same parent. |
| `BR-CORE-05-003` | Phone numbers are normalised to E.164 on write (`+263...`). Local formats (`077...`, `07 7...`) are accepted and converted. |
| `BR-CORE-05-004` | Passwords are hashed with Argon2id. Minimum length is configurable, default 10. New and changed passwords are checked against a breached-password list. |
| `BR-CORE-05-005` | 2FA is mandatory for Super Admin, Head, Bursar, and Cashier. Those users cannot reach any page other than 2FA enrolment until enrolled. |
| `BR-CORE-05-006` | Failed logins are throttled per identifier **and** per IP. After the configured limit the account locks for a configured duration and the user is notified. |
| `BR-CORE-05-007` | Every login attempt, successful or not, writes to `login_attempts`. Append-only. |
| `BR-CORE-05-008` | Access tokens carry abilities. A token missing a required ability receives 403 `INSUFFICIENT_SCOPE`, **even if the user's role would permit the action**. Ability is a ceiling on role. |
| `BR-CORE-05-009` | Refresh tokens are single-use. Reuse revokes the entire device family and raises a security event. |
| `BR-CORE-05-010` | A user may hold at most `auth.max_devices_per_user` active devices (default 5). Issuing beyond the limit revokes the least recently used. |
| `BR-CORE-05-011` | A user may revoke any of their own devices from the portal. Revocation is effective on the next request, not on a cache expiry. |
| `BR-CORE-05-012` | Roles are assigned per school. The same user may hold different roles in different schools. |
| `BR-CORE-05-013` | System role templates (`is_system = 1`) cannot be deleted or renamed. They can be cloned and the clone modified freely. |
| `BR-CORE-05-014` | Permission checks resolve `(user, active_school, permission, scope)`. Scope narrows the record set; it never widens it. |
| `BR-CORE-05-015` | Scope precedence when a user holds the same permission at multiple scopes through different roles: the **widest** applies (`school` > `section` > `assigned` > `own`). |
| `BR-CORE-05-016` | Permissions marked `is_dangerous` require a confirmation step in the UI and are highlighted in the role editor. |
| `BR-CORE-05-017` | Impersonation requires `core.user.impersonate`, a reason, a support ticket reference, and — in production — a recorded customer consent reference. It is time-boxed (default 60 minutes) and auto-expires. |
| `BR-CORE-05-018` | An impersonator can never perform a financial mutation, change permissions, or export bulk data. Those abilities are stripped from the impersonated session. |
| `BR-CORE-05-019` | Every action during impersonation is audited as `performed_by = impersonated, on_behalf_of = impersonator`. Both identities are permanently visible. |
| `BR-CORE-05-020` | Deactivating a user immediately revokes all their tokens and terminates all their sessions. |
| `BR-CORE-05-021` | A user cannot be hard-deleted while any audit entry, financial transaction, or approval references them. Only deactivation is possible. |
| `BR-CORE-05-022` | Student portal accounts are age-gated: a configurable minimum age governs account creation, and features are gated per grade level band. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Login | `Auth\Login` | — |
| 2FA challenge / enrolment | `Auth\TwoFactor` / `Auth\TwoFactorSetup` | — |
| Forgot / reset password | `Auth\ForgotPassword`, `Auth\ResetPassword` | — |
| User directory | `Core\Users\Index` | `core.user.view` |
| User detail | `Core\Users\Show` | `core.user.view` — roles per school, devices, login history, linked records |
| Create / edit user | `Core\Users\Form` | `core.user.create` / `core.user.update` |
| Role manager | `Core\Roles\Index` | `core.role.view` |
| Role editor | `Core\Roles\Editor` | `core.role.update` — permission matrix by module with per-permission scope selector |
| Permission explorer | `Core\Permissions\Explorer` | `core.role.view` — "who can do X?" reverse lookup |
| My devices | `Core\Profile\Devices` | own |
| My security | `Core\Profile\Security` | own — password, 2FA, active sessions |
| Impersonation console | `Core\Users\Impersonate` | `core.user.impersonate` (vendor) |
| Login audit | `Core\Users\LoginAudit` | `core.audit.view` |

### 7. API endpoints

```
POST   /api/v1/auth/login                { identifier, password, device }
POST   /api/v1/auth/otp/request          { phone }
POST   /api/v1/auth/otp/verify           { phone, code, device }
POST   /api/v1/auth/two-factor/challenge { code | recovery_code }
POST   /api/v1/auth/refresh              { refresh_token }
POST   /api/v1/auth/logout
POST   /api/v1/auth/logout-all
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password

GET    /api/v1/me                        → profile, schools, active school, session, abilities
PATCH  /api/v1/me                        → self-service profile update
POST   /api/v1/me/password
GET    /api/v1/me/permissions            → resolved permissions + scopes for the active school
GET    /api/v1/me/devices
DELETE /api/v1/me/devices/{ulid}
GET    /api/v1/me/linked-records         → learners for a parent, staff record for a teacher
```

`GET /me/permissions` lets Next.js and Flutter hide affordances the user cannot use. The server still enforces everything — client-side hiding is a courtesy, never a control.

### 8. Token abilities catalogue (Domain A subset)

```
profile:read      profile:write
schools:read      sessions:read      sessions:switch
settings:read
notifications:read notifications:write
files:read        files:write
```

Domain-specific abilities (`fees:read`, `results:write`, `exeat:request`) are declared by their own modules and listed in their books.

### 9. Permissions

```
core.user.view            core.user.create          core.user.update
core.user.deactivate      core.user.reset_password  core.user.impersonate ⚠
core.role.view            core.role.create          core.role.update      core.role.delete
core.role.assign          core.permission.view
```

### 10. Settings

| Key | Type | Default |
|---|---|---|
| `auth.password_min_length` | int | `10` |
| `auth.password_requires_mixed_case` | bool | `true` |
| `auth.password_requires_number` | bool | `true` |
| `auth.password_requires_symbol` | bool | `false` |
| `auth.password_expiry_days` | int | `0` (disabled) |
| `auth.max_failed_attempts` | int | `5` |
| `auth.lockout_minutes` | int | `15` |
| `auth.session_idle_timeout_minutes` | int | `30` |
| `auth.access_token_ttl_minutes` | int | `60` |
| `auth.refresh_token_ttl_days` | int | `30` |
| `auth.max_devices_per_user` | int | `5` |
| `auth.require_2fa_roles` | array | `[super_admin, head, bursar, cashier]` |
| `auth.otp_length` | int | `6` |
| `auth.otp_ttl_seconds` | int | `300` |
| `auth.otp_resend_cooldown_seconds` | int | `60` |
| `auth.impersonation_max_minutes` | int | `60` |
| `auth.student_portal_min_grade_ordinal` | int | `4` (Grade 3) |

### 11. Events published

`UserCreated` · `UserLoggedIn` · `UserLoginFailed` · `UserLockedOut` · `TokenIssued` · `TokenRevoked` · `RefreshTokenReuseDetected` ⚠ · `TwoFactorEnabled` · `RoleAssigned` · `PermissionsChanged` · `ImpersonationStarted` · `ImpersonationEnded`

### 12. Acceptance criteria

```gherkin
AC-CORE-05-001
  Given a parent with a phone number and no password
  When they request an OTP and submit the correct code
  Then they receive an access token and a refresh token
  And the response includes their linked learners

AC-CORE-05-002
  Given a refresh token that has already been used
  When it is presented again
  Then every token for that device family is revoked
  And a RefreshTokenReuseDetected security event is raised
  And the user is notified on all configured channels

AC-CORE-05-003
  Given a teacher holds academic.result.enter scoped to 'assigned'
  When they request marks for a class they do not teach
  Then they receive 403
  And when they request a class they do teach, they receive 200

AC-CORE-05-004
  Given a user holds a role granting fee viewing
  But their token lacks the fees:read ability
  When they call GET /api/v1/finance/balances
  Then they receive 403 with code INSUFFICIENT_SCOPE

AC-CORE-05-005
  Given a bursar has not enrolled in 2FA
  When they log in
  Then they are redirected to 2FA enrolment
  And no other page is reachable until enrolment completes

AC-CORE-05-006
  Given a support engineer is impersonating a bursar
  When they attempt to create a receipt
  Then the action is blocked
  And the block is recorded on the impersonation session

AC-CORE-05-007
  Given a user is deactivated
  When any of their existing tokens is used
  Then the request returns 401
  And no cached permission allows the request through
```

---

# CORE-06 · Numbering, Templates & Document Generation

### 1. Scope

**In scope.** Gapless numbering series, void handling, template authoring and versioning, variable resolution, PDF rendering pipeline, bulk generation, digital signatures and stamps, QR verification.

**Out of scope.** The content of specific documents — a report card's layout belongs to `ACA-05`, which registers a template *type* here.

### 2. Data model

```sql
numbering_series
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
document_type       VARCHAR(40)  NOT NULL   -- invoice|receipt|credit_note|purchase_order|
                                            -- exeat|admission|transfer_letter|payslip|...
academic_year_id    BIGINT       NULL FK    -- null = not year-scoped
term_id             BIGINT       NULL FK
pattern             VARCHAR(120) NOT NULL   -- '{SCHOOL}/{TYPE}/{YEAR}/{TERM}/{SEQ:6}'
prefix              VARCHAR(20)  NULL
next_sequence       BIGINT       NOT NULL DEFAULT 1
sequence_padding    TINYINT      NOT NULL DEFAULT 6
reset_policy        VARCHAR(20)  NOT NULL   -- never|yearly|termly
is_active           TINYINT(1)   NOT NULL DEFAULT 1
created_at, updated_at
  UNIQUE (school_id, document_type, academic_year_id, term_id)

allocated_numbers                     -- append-only; the gapless guarantee
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
series_id           BIGINT       FK INDEX
sequence            BIGINT       NOT NULL
formatted_number    VARCHAR(80)  NOT NULL
document_type       VARCHAR(40)  NOT NULL
documentable_type   VARCHAR(255) NULL
documentable_id     BIGINT       NULL
status              VARCHAR(20)  NOT NULL  -- allocated|used|voided
void_reason         TEXT         NULL
allocated_by        BIGINT       FK → users.id
allocated_at        TIMESTAMP    NOT NULL
used_at             TIMESTAMP    NULL
voided_at           TIMESTAMP    NULL
  UNIQUE (series_id, sequence)
  UNIQUE (school_id, formatted_number)
  INDEX  (documentable_type, documentable_id)

document_templates
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
section_id          BIGINT       NULL FK        -- section-specific variant
template_type       VARCHAR(40)  NOT NULL       -- registered by owning module
name                VARCHAR(150) NOT NULL
version             SMALLINT     NOT NULL DEFAULT 1
content             LONGTEXT     NOT NULL       -- sandboxed Blade-like syntax
styles              LONGTEXT     NULL           -- scoped CSS
page_size           VARCHAR(20)  NOT NULL DEFAULT 'A4'
orientation         VARCHAR(20)  NOT NULL DEFAULT 'portrait'
margins             JSON         NULL
header_content      LONGTEXT     NULL
footer_content      LONGTEXT     NULL
is_default          TINYINT(1)   NOT NULL DEFAULT 0
is_active           TINYINT(1)   NOT NULL DEFAULT 1
effective_from      DATE         NULL
effective_to        DATE         NULL
created_by, updated_by, created_at, updated_at
  INDEX (school_id, template_type, is_active)

documents                             -- every generated artefact
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       NULL FK
term_id             BIGINT       NULL FK
document_type       VARCHAR(40)  NOT NULL
template_id         BIGINT       NULL FK        -- the exact version used
template_version    SMALLINT     NULL
number              VARCHAR(80)  NULL
documentable_type   VARCHAR(255) NULL
documentable_id     BIGINT       NULL
file_path           VARCHAR(500) NOT NULL
file_hash           CHAR(64)     NOT NULL       -- integrity + dedupe
file_size           BIGINT
verification_code   VARCHAR(40)  NULL UNIQUE    -- QR target
generated_by        BIGINT       FK → users.id
generated_at        TIMESTAMP    NOT NULL
expires_at          TIMESTAMP    NULL
download_count      INT          NOT NULL DEFAULT 0
  INDEX (school_id, document_type, generated_at)
  INDEX (documentable_type, documentable_id)

document_batches
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
document_type       VARCHAR(40)  NOT NULL
template_id         BIGINT       FK
total_count         INT          NOT NULL
completed_count     INT          NOT NULL DEFAULT 0
failed_count        INT          NOT NULL DEFAULT 0
status              VARCHAR(20)  NOT NULL  -- queued|running|completed|failed|cancelled
merged_file_path    VARCHAR(500) NULL      -- single PDF for bulk printing
error_log           JSON         NULL
requested_by        BIGINT       FK → users.id
started_at, completed_at
```

### 3. The numbering allocation algorithm ⭐

```php
public function allocate(string $documentType, ?Model $for = null): AllocatedNumber
{
    return DB::transaction(function () use ($documentType, $for) {
        $series = NumberingSeries::where('school_id', SchoolContext::currentId())
            ->where('document_type', $documentType)
            ->forCurrentPeriod()
            ->lockForUpdate()          // ← row lock: the whole guarantee lives here
            ->firstOrFail();

        $sequence = $series->next_sequence;
        $formatted = $this->format($series, $sequence);

        $series->increment('next_sequence');

        return AllocatedNumber::create([
            'series_id'        => $series->id,
            'sequence'         => $sequence,
            'formatted_number' => $formatted,
            'document_type'    => $documentType,
            'documentable_type'=> $for?->getMorphClass(),
            'documentable_id'  => $for?->id,
            'status'           => 'allocated',
            'allocated_by'     => auth()->id(),
            'allocated_at'     => now(),
        ]);
    });
}
```

**Critical detail.** Allocation happens **inside the same transaction as the document it numbers**. If the document creation fails, the transaction rolls back and the sequence is not consumed. If the document is created but later cancelled, the number is marked `voided` with a reason — never reused, never quietly skipped.

### 4. Template variable syntax

Sandboxed. No arbitrary PHP. No file access. No database queries from within a template.

```
{{ school.name }}                       simple
{{ learner.full_name | upper }}          filters
{{ invoice.balance | money }}            money formatting with currency
{{ term.starts_on | date:'d M Y' }}      dates
{{ invoice.due_on | date_diff }}         relative

@if(invoice.balance_minor > 0) ... @endif
@foreach(results as result) ... @endforeach

{{ results | table:'subject,mark,grade,remark' }}   registered renderers
{{ school.logo | image:120 }}
{{ document.verification_qr }}
{{ head.signature | signature }}
```

Available filters: `upper`, `lower`, `title`, `money`, `number`, `date`, `date_diff`, `ordinal`, `percentage`, `truncate`, `default`, `image`, `table`, `signature`, `qr`, `barcode`.

Each template type registers its own available variable set, which the editor exposes as an insertable, searchable list — the author never has to guess a variable name.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-06-001` | Number allocation is transactional with row-level locking. Two concurrent requests can never receive the same number. |
| `BR-CORE-06-002` | An allocated number is never reused. A failed or cancelled document produces a `voided` record with a mandatory reason. |
| `BR-CORE-06-003` | `formatted_number` is unique per school across all document types. |
| `BR-CORE-06-004` | Sequences reset per `reset_policy`. `never` is the safest default for receipts and is recommended in the seeded configuration. |
| `BR-CORE-06-005` | A numbering pattern cannot be changed once numbers have been allocated in the current period. Changing it takes effect from the next reset boundary. |
| `BR-CORE-06-006` | A gap report is available and is part of the audit pack: it lists every voided sequence with its reason. |
| `BR-CORE-06-007` | Editing a template creates a **new version**. Existing documents retain a reference to the exact version that produced them. |
| `BR-CORE-06-008` | Regenerating a historical document uses the original template version, not the current one. A 2024 report card always renders as it did in 2024. |
| `BR-CORE-06-009` | Templates execute in a sandbox. Any attempt to invoke unregistered functions, access the filesystem, or query the database fails template validation at save time. |
| `BR-CORE-06-010` | A template cannot be saved unless every variable it references exists in its type's registered variable set. |
| `BR-CORE-06-011` | Generated PDFs are stored with a SHA-256 hash. Re-rendering identical inputs with the same template version must produce an identical hash — determinism is asserted in tests. |
| `BR-CORE-06-012` | Every generated document may carry a `verification_code` and QR pointing to a public verification endpoint that confirms authenticity without exposing content. |
| `BR-CORE-06-013` | Batch generation is queued, chunked, resumable, and reports per-item failures without aborting the whole batch. |
| `BR-CORE-06-014` | Documents inherit the retention policy of their document type from `CMP-03`. |
| `BR-CORE-06-015` | Document downloads are served through short-lived signed URLs and increment `download_count`. Direct storage paths are never exposed. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Numbering series | `Core\Numbering\Index` | `core.numbering.manage` |
| Series editor | `Core\Numbering\Editor` | `core.numbering.manage` — pattern builder with live preview |
| Gap report | `Core\Numbering\GapReport` | `core.numbering.view` |
| Template library | `Core\Templates\Index` | `core.template.view` |
| Template editor | `Core\Templates\Editor` | `core.template.update` — split pane: code, variable palette, live preview against sample data |
| Version history | `Core\Templates\Versions` | `core.template.view` — diff between versions |
| Document archive | `Core\Documents\Index` | `core.document.view` |
| Batch monitor | `Core\Documents\Batches` | `core.document.generate` |

### 7. API endpoints

```
GET  /api/v1/documents/{ulid}              → metadata
GET  /api/v1/documents/{ulid}/download     → signed redirect
GET  /api/v1/documents/batches/{ulid}      → batch progress
GET  /verify/{verification_code}           → PUBLIC, unauthenticated: authenticity only
```

The public verification endpoint returns document type, issuing school, issue date, and validity. It never returns content. A prospective employer can confirm a leaving certificate is genuine without seeing the learner's marks.

### 8. Permissions

```
core.numbering.view       core.numbering.manage
core.template.view        core.template.create      core.template.update
core.template.delete      core.document.view        core.document.generate
core.document.download    core.document.delete ⚠
```

### 9. Events published

`NumberAllocated` · `NumberVoided` · `TemplateVersionCreated` · `DocumentGenerated` · `DocumentBatchCompleted` · `DocumentDownloaded`

### 10. Jobs

| Job | Notes |
|---|---|
| `JOB-GenerateDocument` | Single render; `tries=3`, exponential backoff |
| `JOB-GenerateDocumentBatch` | Fan-out with chunking; progress on the batch record |
| `JOB-MergeBatchDocuments` | Combines a batch into one PDF for bulk printing |
| `JOB-PurgeExpiredDocuments` | Nightly, honouring retention policy |

### 11. Acceptance criteria

```gherkin
AC-CORE-06-001
  Given 50 concurrent receipt creations
  When all complete
  Then 50 distinct sequential numbers were issued
  And there are no duplicates and no gaps

AC-CORE-06-002
  Given a receipt creation fails after its number is allocated
  When the transaction rolls back
  Then the sequence is not consumed
  And the next receipt receives that number

AC-CORE-06-003
  Given a report card was generated with template version 2
  And the template has since been updated to version 4
  When I regenerate that historical report card
  Then it renders identically to the original
  And its file hash matches the original

AC-CORE-06-004
  Given a template references a variable not in its registered set
  When I save the template
  Then the save is rejected naming the unknown variable

AC-CORE-06-005
  Given a leaving certificate with verification code ABC123
  When an unauthenticated third party visits /verify/ABC123
  Then they see the document type, school, issue date, and validity
  And they see no learner marks or personal details
```

---

# CORE-07 · Workflow & Approvals Engine

### 1. Scope

**In scope.** Chain definitions, approval requests, delegation, escalation, approval history, bulk queues, mobile approval.

**Out of scope.** What is being approved. Each module registers an *approvable type* and supplies its own display and post-approval handler.

### 2. Data model

```sql
approval_chains
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
approvable_type     VARCHAR(60)  NOT NULL   -- 'exeat','purchase_order','fee_waiver',
                                            -- 'mark_amendment','period_reopen','leave_request'
name                VARCHAR(150) NOT NULL
description         VARCHAR(255)
condition_rules     JSON         NULL       -- when this chain applies (amount bands, type)
is_default          TINYINT(1)   NOT NULL DEFAULT 0
priority            SMALLINT     NOT NULL DEFAULT 0   -- evaluation order
is_active           TINYINT(1)   NOT NULL DEFAULT 1
created_by, updated_by, created_at, updated_at
  INDEX (school_id, approvable_type, is_active)

approval_steps
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
chain_id            BIGINT       FK INDEX
step_number         SMALLINT     NOT NULL
name                VARCHAR(120) NOT NULL   -- 'Housemaster verification'
approver_type       VARCHAR(20)  NOT NULL   -- role|user|dynamic
approver_role_id    BIGINT       NULL FK
approver_user_id    BIGINT       NULL FK
dynamic_resolver    VARCHAR(120) NULL       -- 'housemaster_of_learner','hod_of_subject'
mode                VARCHAR(20)  NOT NULL   -- sequential|parallel_all|parallel_any
required_approvals  TINYINT      NOT NULL DEFAULT 1
condition_rules     JSON         NULL       -- step-level skip conditions
escalate_after_hours SMALLINT    NULL
escalate_to_role_id BIGINT       NULL FK
can_reject          TINYINT(1)   NOT NULL DEFAULT 1
can_return          TINYINT(1)   NOT NULL DEFAULT 1  -- send back for amendment
requires_comment    TINYINT(1)   NOT NULL DEFAULT 0
  UNIQUE (chain_id, step_number)

approval_requests
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       FK
term_id             BIGINT       NULL FK
chain_id            BIGINT       FK
approvable_type     VARCHAR(255) NOT NULL
approvable_id       BIGINT       NOT NULL
current_step        SMALLINT     NOT NULL DEFAULT 1
status              VARCHAR(20)  NOT NULL  -- pending|approved|rejected|returned|
                                           -- cancelled|expired
title               VARCHAR(200) NOT NULL  -- human summary for the queue
summary             TEXT         NULL
amount_minor        BIGINT       NULL      -- for threshold routing and queue display
amount_currency     CHAR(3)      NULL
requested_by        BIGINT       FK → users.id
requested_at        TIMESTAMP    NOT NULL
completed_at        TIMESTAMP    NULL
due_at              TIMESTAMP    NULL
  INDEX (school_id, status, current_step)
  INDEX (approvable_type, approvable_id)

approval_actions                      -- append-only
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
request_id          BIGINT       FK INDEX
step_number         SMALLINT     NOT NULL
action              VARCHAR(20)  NOT NULL  -- approved|rejected|returned|delegated|
                                           -- escalated|cancelled|auto_approved
actor_id            BIGINT       FK → users.id
on_behalf_of_id     BIGINT       NULL FK   -- delegation
comment             TEXT         NULL
ip_address          VARCHAR(45)
acted_at            TIMESTAMP    NOT NULL

approval_delegations
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
delegator_id        BIGINT       FK → users.id
delegate_id         BIGINT       FK → users.id
approvable_type     VARCHAR(60)  NULL      -- null = all types
starts_at           TIMESTAMP    NOT NULL
ends_at             TIMESTAMP    NOT NULL
reason              VARCHAR(255)
is_active           TINYINT(1)   NOT NULL DEFAULT 1
created_by, created_at
```

### 3. The approvable contract

```php
interface Approvable
{
    public function approvableType(): string;
    public function approvalTitle(): string;
    public function approvalSummary(): string;
    public function approvalAmount(): ?Money;
    public function approvalPayload(): array;      // rendered in the approval queue
    public function onApproved(ApprovalRequest $r): void;
    public function onRejected(ApprovalRequest $r): void;
    public function onReturned(ApprovalRequest $r): void;
}
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-07-001` | Chain selection evaluates active chains for the type in `priority` order; the first whose `condition_rules` match applies. The default chain is the fallback. |
| `BR-CORE-07-002` | Approver resolution: `user` → that person; `role` → all holders of that role in this school; `dynamic` → a registered resolver run against the approvable. |
| `BR-CORE-07-003` | A step with no resolvable approver **blocks** and notifies an administrator. It never auto-approves. |
| `BR-CORE-07-004` | A user cannot approve their own request, regardless of role. Attempting it is rejected and logged. |
| `BR-CORE-07-005` | `parallel_all` requires every resolved approver; `parallel_any` requires `required_approvals`; `sequential` advances one step at a time. |
| `BR-CORE-07-006` | Rejection terminates the request immediately and fires `onRejected`. Later steps are not consulted. |
| `BR-CORE-07-007` | Return sends the request back to the requester in `returned` status, editable. Resubmission restarts at step 1 unless configured otherwise. |
| `BR-CORE-07-008` | Escalation after `escalate_after_hours` notifies the escalation role but does **not** transfer authority. Only a legitimate approver can approve. |
| `BR-CORE-07-009` | Delegation transfers approval capability for a bounded window. Every delegated action records both `actor_id` and `on_behalf_of_id`. |
| `BR-CORE-07-010` | `approval_actions` is append-only. Nothing in an approval history is ever edited or removed. |
| `BR-CORE-07-011` | The complete approval history is permanently attached to the approvable and rendered on its printed document where applicable. |
| `BR-CORE-07-012` | A request may be cancelled by its requester or by a user holding `core.approval.cancel`, only while `pending`. |
| `BR-CORE-07-013` | Approval requests are session-bound. A request created in a term that later locks remains viewable and its history intact. |
| `BR-CORE-07-014` | Steps with `requires_comment` reject approval submitted without a comment. |
| `BR-CORE-07-015` | Every step transition notifies the next approver and the requester through `CORE-09`. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| My approvals | `Core\Approvals\Queue` | own — grouped by type, sortable by age and amount, bulk approve for eligible types |
| Request detail | `Core\Approvals\Show` | contextual — full payload, history, comment box |
| My requests | `Core\Approvals\MyRequests` | own |
| Chain manager | `Core\Approvals\Chains` | `core.approval.configure` |
| Chain builder | `Core\Approvals\ChainBuilder` | `core.approval.configure` — visual step editor with condition rules and preview |
| Delegations | `Core\Approvals\Delegations` | own + `core.approval.delegate_others` |
| SLA report | `Core\Approvals\SlaReport` | `core.approval.view_reports` — average time to approve, by approver and type |

### 6. API endpoints

```
GET   /api/v1/approvals/pending          → my queue
GET   /api/v1/approvals/{ulid}
POST  /api/v1/approvals/{ulid}/approve   { comment }
POST  /api/v1/approvals/{ulid}/reject    { comment }
POST  /api/v1/approvals/{ulid}/return    { comment }
GET   /api/v1/approvals/mine             → requests I raised
POST  /api/v1/approvals/delegations
DELETE /api/v1/approvals/delegations/{ulid}
```

Mobile approval matters enormously here — a head approving exeats from a phone on a Friday afternoon is a real workflow, and it is the difference between the module being adopted and being bypassed.

### 7. Acceptance criteria

```gherkin
AC-CORE-07-001
  Given a purchase order for USD 750
  And a chain routing under-500 to the Bursar and 500-or-more to the Head
  When the request is created
  Then the chain requiring Head approval is selected

AC-CORE-07-002
  Given I am the Bursar and I raised a fee waiver request
  When I attempt to approve it myself
  Then the approval is rejected
  And the attempt is logged

AC-CORE-07-003
  Given an exeat chain of Housemaster → Boarding Master → Deputy Head
  When the Boarding Master rejects it
  Then the request status is 'rejected'
  And the Deputy Head is never notified
  And onRejected fires on the exeat

AC-CORE-07-004
  Given a step has no resolvable approver
  When the request reaches that step
  Then it remains pending
  And an administrator is notified
  And it is never auto-approved

AC-CORE-07-005
  Given the Head has delegated approvals to the Deputy for 5 days
  When the Deputy approves a request in that window
  Then the action records actor = Deputy and on_behalf_of = Head
  And both names appear in the printed approval history
```

---

# CORE-08 · Audit, Activity & Data Integrity

### 1. Scope

**In scope.** Model change logging, the immutable financial audit stream with hash chaining, security event logging, export and access logging, audit search and export, integrity verification jobs.

**Out of scope.** Login attempts (`CORE-05` owns that table; this module reads it). Data retention policy (`CMP-03`).

### 2. Data model

```sql
activity_log                          -- general change log
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       NULL FK INDEX
log_name            VARCHAR(60)  NOT NULL   -- module code
description         VARCHAR(255) NOT NULL
subject_type        VARCHAR(255) NULL
subject_id          BIGINT       NULL
causer_type         VARCHAR(255) NULL
causer_id           BIGINT       NULL
event               VARCHAR(30)  NULL       -- created|updated|deleted|restored|viewed
properties          JSON         NULL       -- { old: {...}, attributes: {...} }
batch_uuid          CHAR(36)     NULL       -- groups a multi-model operation
ip_address          VARCHAR(45)
user_agent          VARCHAR(500)
request_id          CHAR(26)     NULL       -- correlates every entry in one request
impersonator_id     BIGINT       NULL FK
academic_year_id    BIGINT       NULL
term_id             BIGINT       NULL
created_at          TIMESTAMP    NOT NULL
  INDEX (school_id, created_at)
  INDEX (subject_type, subject_id)
  INDEX (causer_id, created_at)
  INDEX (request_id)

financial_audit_log                   -- APPEND-ONLY, HASH-CHAINED
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
sequence            BIGINT       NOT NULL   -- per-school monotonic
event_type          VARCHAR(50)  NOT NULL   -- journal_posted|receipt_issued|invoice_voided|
                                            -- period_closed|period_reopened|write_off|
                                            -- discount_granted|refund_issued
subject_type        VARCHAR(255) NOT NULL
subject_id          BIGINT       NOT NULL
amount_minor        BIGINT       NULL
amount_currency     CHAR(3)      NULL
payload             JSON         NOT NULL   -- canonical snapshot of the event
payload_hash        CHAR(64)     NOT NULL   -- SHA-256 of canonical payload
previous_hash       CHAR(64)     NULL       -- chains to sequence - 1
causer_id           BIGINT       NOT NULL FK → users.id
impersonator_id     BIGINT       NULL FK
academic_year_id    BIGINT       NOT NULL
term_id             BIGINT       NULL
ip_address          VARCHAR(45)
occurred_at         TIMESTAMP(6) NOT NULL   -- microsecond precision for ordering
  UNIQUE (school_id, sequence)
  INDEX  (school_id, event_type, occurred_at)
  -- Database grants: INSERT and SELECT only. No UPDATE. No DELETE.

security_events
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       NULL FK
event_type          VARCHAR(60)  NOT NULL   -- unauthorised_school_access|
                                            -- refresh_token_reuse|scope_bypass_attempt|
                                            -- permission_escalation|bulk_export|
                                            -- repeated_login_failure|impersonation_started
severity            VARCHAR(20)  NOT NULL   -- info|warning|critical
user_id             BIGINT       NULL FK
description         TEXT         NOT NULL
context             JSON         NULL
ip_address          VARCHAR(45)
user_agent          VARCHAR(500)
is_reviewed         TINYINT(1)   NOT NULL DEFAULT 0
reviewed_by         BIGINT       NULL FK
reviewed_at         TIMESTAMP    NULL
review_notes        TEXT         NULL
occurred_at         TIMESTAMP    NOT NULL
  INDEX (severity, is_reviewed, occurred_at)

data_access_log                       -- sensitive reads
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
user_id             BIGINT       FK
access_type         VARCHAR(30)  NOT NULL   -- view|export|print|download|search
resource_type       VARCHAR(60)  NOT NULL   -- medical_record|safeguarding_record|
                                            -- financial_statement|learner_bulk|payroll
resource_id         BIGINT       NULL
record_count        INT          NULL       -- for bulk operations
purpose             VARCHAR(255) NULL
ip_address          VARCHAR(45)
accessed_at         TIMESTAMP    NOT NULL
  INDEX (school_id, resource_type, accessed_at)
  INDEX (user_id, accessed_at)

integrity_check_runs
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       NULL FK
check_type          VARCHAR(50)  NOT NULL   -- trial_balance|audit_chain|snapshot_chain|
                                            -- cross_school_fk|cached_balance|orphan_records
status              VARCHAR(20)  NOT NULL   -- passed|failed|error
records_checked     BIGINT
failures_found      INT          NOT NULL DEFAULT 0
failure_details     JSON         NULL
duration_ms         INT
ran_at              TIMESTAMP    NOT NULL
  INDEX (check_type, status, ran_at)
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-08-001` | Every model using the `Auditable` trait logs create, update, delete, and restore with a before/after diff. |
| `BR-CORE-08-002` | Fields listed in a model's `$auditExcluded` (passwords, tokens, secrets) are never written to the log, not even as a hash. |
| `BR-CORE-08-003` | Every log entry carries `request_id`, so every change made by a single request can be retrieved as one unit. |
| `BR-CORE-08-004` | Every entry carries `school_id`, `academic_year_id`, and `term_id` where resolvable, so audit can be filtered by period. |
| `BR-CORE-08-005` | `financial_audit_log` is append-only, enforced by revoking `UPDATE` and `DELETE` on the application database user. |
| `BR-CORE-08-006` | `sequence` is monotonic per school, allocated with row locking. No gaps, no duplicates. |
| `BR-CORE-08-007` | `payload_hash` is SHA-256 over a canonical serialisation (sorted keys, fixed numeric formatting, UTC timestamps). `previous_hash` chains to the prior sequence, making retrospective modification detectable. |
| `BR-CORE-08-008` | A nightly job verifies the entire chain per school. Any break raises a **critical** security event and alerts the vendor immediately. |
| `BR-CORE-08-009` | Reads of medical, safeguarding, payroll, and bulk learner data write to `data_access_log`. |
| `BR-CORE-08-010` | Any export exceeding `audit.bulk_export_threshold` rows creates a security event and notifies the school administrator. |
| `BR-CORE-08-011` | Audit records are never deleted by the application. Archival to cold storage after the retention window is the only lifecycle operation, and it preserves the chain. |
| `BR-CORE-08-012` | Actions performed under impersonation record both the impersonated user and the impersonator. |
| `BR-CORE-08-013` | The Auditor role has read access to all audit data and write access to nothing anywhere in the system. |
| `BR-CORE-08-014` | Audit writes are queued to avoid adding latency to user requests, **except** `financial_audit_log`, which is written synchronously inside the originating transaction. A financial event and its audit entry commit together or not at all. |

### 4. Integrity check suite

| Check | Frequency | Failure action |
|---|---|---|
| Trial balance per school per currency | Nightly | Critical alert; flag on the school's finance dashboard |
| Financial audit hash chain | Nightly | Critical alert to vendor; tamper investigation |
| Snapshot hash chain | Weekly | Critical alert |
| Cross-school foreign keys | Nightly | Critical alert; list offending rows |
| Cached balance vs derived balance | Nightly | Rebuild cache; log discrepancy |
| Orphan records (FK targets missing) | Weekly | Warning; remediation list |
| Numbering gap scan | Weekly | Warning if any gap lacks a void reason |
| Missing `school_id` scan | Nightly | Critical — indicates a scope bug |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Audit explorer | `Core\Audit\Explorer` | `core.audit.view` — filter by user, model, event, date, term, request |
| Record history | `Core\Audit\RecordHistory` | `core.audit.view` — embeddable timeline on any record's detail page |
| Financial audit stream | `Core\Audit\FinancialStream` | `core.audit.view_financial` — with a chain verification button |
| Security events | `Core\Audit\SecurityEvents` | `core.audit.view_security` — triage queue with review workflow |
| Access log | `Core\Audit\AccessLog` | `core.audit.view_access` |
| Integrity dashboard | `Core\Audit\Integrity` | `core.audit.view` — last run, status, failures per check |
| Audit export | `Core\Audit\Export` | `core.audit.export` — for external auditors, itself logged |

### 6. Permissions

```
core.audit.view              core.audit.view_financial
core.audit.view_security     core.audit.view_access
core.audit.export            core.audit.review_security_event
```

### 7. Acceptance criteria

```gherkin
AC-CORE-08-001
  Given a receipt is created
  When the transaction commits
  Then a financial_audit_log entry exists in the same transaction
  And its previous_hash equals the payload_hash of the prior sequence for that school

AC-CORE-08-002
  Given a financial_audit_log row is altered directly in the database
  When the nightly chain verification runs
  Then it fails at that sequence
  And a critical security event is raised naming the sequence

AC-CORE-08-003
  Given the application database user
  When it attempts UPDATE or DELETE on financial_audit_log
  Then the database refuses the statement

AC-CORE-08-004
  Given a nurse views a learner's medical record
  Then a data_access_log entry records the user, resource, and time

AC-CORE-08-005
  Given a user exports 1,500 learner records
  And the bulk export threshold is 500
  Then a security event is created
  And the school administrator is notified

AC-CORE-08-006
  Given I hold the Auditor role
  When I attempt any write operation anywhere in the system
  Then it is refused
  And when I view any audit record, it is permitted
```

---

# CORE-09 · Notification Orchestration Bus

### 1. Scope

**In scope.** Channel driver abstraction, recipient resolution, template binding, dispatch, delivery tracking, retry and fallback, quiet hours, cost attribution and caps, opt-out, in-app inbox.

**Out of scope.** Gateway-specific implementations (`COM-01`). Trigger rules (`COM-02`). This module is the pipe; those modules are the taps.

### 2. Data model

```sql
notification_templates
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       NULL FK   -- NULL = system default
key                 VARCHAR(80)  NOT NULL  -- 'fee.payment_received'
channel             VARCHAR(20)  NOT NULL  -- sms|whatsapp|email|push|in_app
locale              VARCHAR(10)  NOT NULL DEFAULT 'en_ZW'
subject             VARCHAR(200) NULL      -- email/push title
body                TEXT         NOT NULL
variables           JSON         NULL      -- declared, validated at save
provider_template_id VARCHAR(120) NULL     -- WhatsApp approved template reference
is_active           TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, key, channel, locale)

notifications
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
notification_key    VARCHAR(80)  NOT NULL
recipient_type      VARCHAR(60)  NOT NULL  -- user|guardian|staff|external
recipient_id        BIGINT       NULL
recipient_address   VARCHAR(200) NOT NULL  -- phone/email, resolved at send
channel             VARCHAR(20)  NOT NULL
subject             VARCHAR(200) NULL
body                TEXT         NOT NULL  -- rendered
context             JSON         NULL      -- source event data
related_type        VARCHAR(255) NULL
related_id          BIGINT       NULL
status              VARCHAR(20)  NOT NULL  -- queued|sending|sent|delivered|read|
                                           -- failed|bounced|suppressed
provider            VARCHAR(40)  NULL
provider_message_id VARCHAR(120) NULL
attempt_count       TINYINT      NOT NULL DEFAULT 0
cost_minor          BIGINT       NULL
cost_currency       CHAR(3)      NULL
error_code          VARCHAR(60)  NULL
error_message       TEXT         NULL
scheduled_for       TIMESTAMP    NULL
sent_at, delivered_at, read_at, failed_at   TIMESTAMP NULL
dedupe_key          VARCHAR(120) NULL
created_at
  INDEX (school_id, status, created_at)
  INDEX (recipient_type, recipient_id, created_at)
  UNIQUE (school_id, dedupe_key)      -- where dedupe_key NOT NULL

notification_preferences
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
user_id             BIGINT       FK INDEX
notification_key    VARCHAR(80)  NULL      -- null = global preference
channel             VARCHAR(20)  NOT NULL
is_enabled          TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (user_id, notification_key, channel)

notification_opt_outs
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK
address             VARCHAR(200) NOT NULL  -- phone or email
channel             VARCHAR(20)  NOT NULL
reason              VARCHAR(120) NULL      -- user_request|bounce|complaint|invalid
opted_out_at        TIMESTAMP    NOT NULL
  UNIQUE (school_id, address, channel)

notification_budgets
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
period_month        CHAR(7)      NOT NULL  -- '2026-09'
channel             VARCHAR(20)  NOT NULL
cap_minor           BIGINT       NULL      -- null = uncapped
spent_minor         BIGINT       NOT NULL DEFAULT 0
currency            CHAR(3)      NOT NULL
warn_at_percent     TINYINT      NOT NULL DEFAULT 80
is_hard_stop        TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, period_month, channel)
```

### 3. Recipient resolution ⭐

This is more subtle than it appears, and getting it wrong sends fee reminders to the wrong parent.

```
Notification about a LEARNER
  │
  ├─ audience = 'fee_responsible'   → guardians where pivot.is_fee_responsible = 1
  ├─ audience = 'primary_contact'   → guardians where pivot.is_primary_contact = 1
  ├─ audience = 'all_guardians'     → every linked guardian
  ├─ audience = 'emergency'         → guardians where pivot.is_emergency_contact = 1
  └─ audience = 'learner'           → the learner's own account (age-gated)

For each resolved recipient:
  1. opted out on this channel?        → suppress, record reason
  2. preference disables this key?     → suppress
  3. address present and valid?        → else fall back to next channel
  4. quiet hours active and not urgent?→ schedule for the next permitted window
  5. duplicate dedupe_key in window?   → suppress
  6. budget cap reached, hard stop?    → suppress, alert administrator
  → dispatch
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-09-001` | A module never talks to a gateway. It dispatches a notification intent to this bus, which resolves everything else. |
| `BR-CORE-09-002` | Every notification key is registered with its declared variables, default channels, urgency, and default audience. Unregistered keys throw. |
| `BR-CORE-09-003` | Template rendering fails loudly on a missing variable. A message is **never** sent containing an unresolved placeholder. |
| `BR-CORE-09-004` | Channel fallback follows the configured order (default WhatsApp → SMS → email) and only on hard failure, not on slow delivery. |
| `BR-CORE-09-005` | Notifications marked urgent (missing boarder, medical emergency, security) bypass quiet hours and budget caps. Everything else respects both. |
| `BR-CORE-09-006` | Opt-outs are honoured absolutely on marketing and general notices, but **not** on transactional and safety notices (receipt confirmation, missing-learner alert), which are operational necessities. |
| `BR-CORE-09-007` | Every sent message records its provider cost. Cost accrues to the monthly budget and is reportable by module and by notification key. |
| `BR-CORE-09-008` | On reaching a hard-stop cap, non-urgent sends are suppressed and the school administrator and bursar are alerted immediately. |
| `BR-CORE-09-009` | Deduplication suppresses identical `(recipient, key, related_record)` messages within a configurable window, default 60 minutes. |
| `BR-CORE-09-010` | Delivery status is updated from provider webhooks. Terminal failure after `max_attempts` marks the message failed and surfaces it in the failure report. |
| `BR-CORE-09-011` | A hard bounce or invalid-number response automatically adds the address to the opt-out list with reason `invalid`, and flags the contact record for correction. |
| `BR-CORE-09-012` | Every notification is also written to the in-app inbox, regardless of external channel outcome. The parent can always find it in the app even if the SMS failed. |
| `BR-CORE-09-013` | Phone numbers are validated and normalised to E.164 before dispatch. Invalid numbers are suppressed and reported rather than paid for. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Notification log | `Core\Notifications\Log` | `core.notification.view` — filter by status, channel, key, recipient |
| Failure report | `Core\Notifications\Failures` | `core.notification.view` — with bulk retry |
| Template manager | `Core\Notifications\Templates` | `core.notification.manage_templates` |
| Template editor | `Core\Notifications\TemplateEditor` | with variable palette, character/segment counter for SMS, live preview |
| Budget & cost | `Core\Notifications\Budget` | `core.notification.manage_budget` — spend by channel, module, key; trend |
| Opt-out register | `Core\Notifications\OptOuts` | `core.notification.view` |

### 6. API endpoints

```
GET   /api/v1/notifications                 → in-app inbox, paginated
GET   /api/v1/notifications/unread-count
POST  /api/v1/notifications/{ulid}/read
POST  /api/v1/notifications/read-all
GET   /api/v1/me/notification-preferences
PUT   /api/v1/me/notification-preferences
POST  /api/v1/me/push-tokens                → register a device for push
DELETE /api/v1/me/push-tokens/{id}
```

### 7. Acceptance criteria

```gherkin
AC-CORE-09-001
  Given a learner with a father marked fee-responsible and a mother marked primary contact
  When a fee reminder with audience 'fee_responsible' is dispatched
  Then only the father receives it
  And when an absence alert with audience 'primary_contact' is dispatched
  Then only the mother receives it

AC-CORE-09-002
  Given a template references {{ learner.first_name }}
  And the rendering context has no learner
  Then dispatch fails and no message is sent
  And the failure names the missing variable

AC-CORE-09-003
  Given School A's SMS budget cap for September is reached with hard stop enabled
  When a non-urgent notification is dispatched
  Then it is suppressed
  And the administrator is alerted
  And when an urgent missing-boarder alert is dispatched
  Then it is sent regardless of the cap

AC-CORE-09-004
  Given a WhatsApp send fails with a hard error
  Then the system falls back to SMS
  And both attempts are recorded on the notification

AC-CORE-09-005
  Given an SMS is dispatched and every external channel fails
  Then the notification still appears in the recipient's in-app inbox
```

---

# CORE-10 · File Vault & Media Management

### 1. Scope

**In scope.** Upload handling, storage abstraction, signed URL delivery, virus scanning, image variants, document categorisation and expiry, quotas, access control.

**Out of scope.** Generated PDFs (`CORE-06` owns `documents`; this module owns *uploads*).

### 2. Data model

```sql
files
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
disk                VARCHAR(30)  NOT NULL DEFAULT 's3'
path                VARCHAR(500) NOT NULL      -- school/{id}/{category}/{ulid}.{ext}
original_name       VARCHAR(255) NOT NULL
mime_type           VARCHAR(120) NOT NULL
extension           VARCHAR(10)  NOT NULL
size_bytes          BIGINT       NOT NULL
hash                CHAR(64)     NOT NULL      -- SHA-256, dedupe + integrity
category            VARCHAR(60)  NOT NULL      -- see registry below
attachable_type     VARCHAR(255) NULL
attachable_id       BIGINT       NULL
is_sensitive        TINYINT(1)   NOT NULL DEFAULT 0   -- medical, safeguarding
scan_status         VARCHAR(20)  NOT NULL DEFAULT 'pending' -- pending|clean|infected|skipped
scan_result         VARCHAR(255) NULL
variants            JSON         NULL          -- { thumb: path, medium: path }
expires_on          DATE         NULL          -- document validity, not storage lifetime
uploaded_by         BIGINT       FK → users.id
created_at, updated_at, deleted_at
  INDEX (school_id, category)
  INDEX (attachable_type, attachable_id)
  INDEX (school_id, hash)

file_categories                       -- registered by modules
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
key                 VARCHAR(60)  NOT NULL UNIQUE
label               VARCHAR(120) NOT NULL
module_code         VARCHAR(20)  NOT NULL
allowed_mimes       JSON         NOT NULL
max_size_bytes      BIGINT       NOT NULL
is_sensitive        TINYINT(1)   NOT NULL DEFAULT 0
generates_variants  TINYINT(1)   NOT NULL DEFAULT 0
requires_expiry     TINYINT(1)   NOT NULL DEFAULT 0
retention_years     TINYINT      NULL

file_access_log                       -- for sensitive categories only
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
file_id             BIGINT       FK INDEX
user_id             BIGINT       FK
action              VARCHAR(20)  NOT NULL   -- view|download
ip_address          VARCHAR(45)
accessed_at         TIMESTAMP    NOT NULL

storage_quotas
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK UNIQUE
quota_bytes         BIGINT       NOT NULL
used_bytes          BIGINT       NOT NULL DEFAULT 0
warn_at_percent     TINYINT      NOT NULL DEFAULT 85
updated_at
```

**Seeded categories:** `learner_photo`, `birth_certificate`, `national_registration`, `learner_permit`, `medical_report`, `immunisation_record`, `transfer_letter`, `staff_photo`, `staff_contract`, `qualification`, `police_clearance`, `supplier_document`, `tax_clearance`, `asset_photo`, `incident_evidence`, `assignment_submission`, `sbp_evidence`, `lms_content`, `school_logo`, `signature`, `receipt_attachment`, `safeguarding_evidence`.

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-10-001` | Every upload declares a registered category. Uploads without one are rejected. |
| `BR-CORE-10-002` | MIME type is validated by **content inspection**, not by the filename extension or the client-supplied header. |
| `BR-CORE-10-003` | Files are stored outside the web root and served exclusively through signed URLs with a default TTL of 5 minutes. |
| `BR-CORE-10-004` | Storage paths are school-namespaced: `school/{school_id}/{category}/{ulid}.{ext}`. Cross-school path traversal is structurally impossible. |
| `BR-CORE-10-005` | Every upload is virus-scanned asynchronously. A file with `scan_status = pending` is downloadable only by its uploader. An `infected` file is quarantined, the uploader notified, and a security event raised. |
| `BR-CORE-10-006` | Images in categories with `generates_variants` produce thumbnail (150px), medium (600px), and large (1200px) WebP variants. **The API serves the smallest variant that satisfies the request**, which matters directly for parents on metered data. |
| `BR-CORE-10-007` | Files in sensitive categories require explicit permission beyond simple record access, and every view or download writes to `file_access_log`. |
| `BR-CORE-10-008` | Identical content (same SHA-256) within a school is stored once and referenced many times. |
| `BR-CORE-10-009` | Categories with `requires_expiry` reject uploads without `expires_on`. Expiry alerts fire at 90, 30, and 7 days. |
| `BR-CORE-10-010` | Exceeding the storage quota blocks new uploads with a clear message and alerts the administrator. Existing files remain fully accessible. |
| `BR-CORE-10-011` | Soft-deleted files are retained for `files.soft_delete_retention_days` (default 30) before physical removal, so an accidental deletion is recoverable. |
| `BR-CORE-10-012` | Deleting a parent record soft-deletes its attachments but never removes files referenced by any audit or financial record. |

### 4. API endpoints

```
POST   /api/v1/files                       multipart: file, category, attachable
GET    /api/v1/files/{ulid}                → metadata
GET    /api/v1/files/{ulid}/url            ?variant=thumb|medium|large|original
DELETE /api/v1/files/{ulid}
POST   /api/v1/files/presign               → direct-to-S3 upload for large files
```

Presigned direct upload matters for the Flutter app: routing a 4 MB assignment scan through the PHP application on a Zimbabwean connection is slow and wasteful.

### 5. Acceptance criteria

```gherkin
AC-CORE-10-001
  Given a file renamed from .exe to .jpg
  When it is uploaded to the learner_photo category
  Then it is rejected on content inspection

AC-CORE-10-002
  Given a file whose scan is still pending
  When a user other than the uploader requests it
  Then access is denied until the scan reports clean

AC-CORE-10-003
  Given a learner photo is uploaded
  Then thumbnail, medium, and large WebP variants are generated
  And requesting variant=thumb returns the smallest file

AC-CORE-10-004
  Given a nurse downloads a medical report
  Then a file_access_log entry records the user, file, action, and time

AC-CORE-10-005
  Given School A is at 100% of its storage quota
  When a user uploads a new file
  Then the upload is rejected with a clear message
  And existing files remain downloadable
```

---

# CORE-11 · Data Import & Migration Toolkit

> Commercially, this is the module that decides whether onboarding takes two days or two months. Build it properly.

### 1. Scope

**In scope.** Template generation, column mapping, validation, dry run, duplicate detection, batch execution, error correction files, batch rollback, opening balance import.

**Out of scope.** Importer implementations for specific entities live with their owning modules; this module provides the framework and the UI.

### 2. Data model

```sql
import_definitions                    -- registered by modules
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
key                 VARCHAR(60)  NOT NULL UNIQUE  -- 'students','opening_balances'
label               VARCHAR(150) NOT NULL
module_code         VARCHAR(20)  NOT NULL
importer_class      VARCHAR(255) NOT NULL
description         TEXT
required_permission VARCHAR(120) NOT NULL
depends_on          JSON         NULL   -- other import keys that must run first
is_rollbackable     TINYINT(1)   NOT NULL DEFAULT 1
sort_order          SMALLINT

import_batches
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       NULL FK
term_id             BIGINT       NULL FK
definition_key      VARCHAR(60)  NOT NULL
source_file_id      BIGINT       FK → files.id
column_mapping      JSON         NOT NULL
options             JSON         NULL   -- update_existing, skip_duplicates, dry_run
status              VARCHAR(20)  NOT NULL -- mapping|validating|validated|importing|
                                          -- completed|failed|rolled_back
total_rows          INT          NOT NULL DEFAULT 0
valid_rows          INT          NOT NULL DEFAULT 0
invalid_rows        INT          NOT NULL DEFAULT 0
imported_rows       INT          NOT NULL DEFAULT 0
skipped_rows        INT          NOT NULL DEFAULT 0
failed_rows         INT          NOT NULL DEFAULT 0
validation_report   JSON         NULL
error_file_id       BIGINT       NULL FK → files.id
imported_by         BIGINT       FK → users.id
started_at, completed_at, rolled_back_at   TIMESTAMP NULL
  INDEX (school_id, definition_key, status)

import_rows                           -- traceability + rollback
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
batch_id            BIGINT       FK INDEX
row_number          INT          NOT NULL
raw_data            JSON         NOT NULL
mapped_data         JSON         NULL
status              VARCHAR(20)  NOT NULL -- pending|valid|invalid|imported|skipped|failed
errors              JSON         NULL
created_type        VARCHAR(255) NULL     -- what this row created
created_id          BIGINT       NULL     -- ← enables precise rollback
  INDEX (batch_id, status)
```

### 3. The importer contract

```php
interface Importer
{
    public function key(): string;
    public function templateColumns(): array;        // header, example, required, notes
    public function rules(): array;                  // per-column validation
    public function transform(array $row): array;    // normalise (dates, phones, casing)
    public function findExisting(array $row): ?Model;
    public function import(array $row, ImportContext $ctx): ImportRowResult;
    public function rollbackRow(ImportRow $row): void;
}
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-11-001` | Every import runs a full validation pass over **every** row before a single record is written. |
| `BR-CORE-11-002` | The validation report shows total, valid, and invalid counts, and every error grouped by type with row numbers. |
| `BR-CORE-11-003` | The user must explicitly approve the validation report before import proceeds. There is no one-click import. |
| `BR-CORE-11-004` | Invalid rows are excluded and returned as a downloadable correction file containing the original data plus an error column, ready to fix and re-upload. |
| `BR-CORE-11-005` | Duplicate detection uses the importer's `findExisting()`. Behaviour on match — skip, update, or create anyway — is chosen by the user per batch. |
| `BR-CORE-11-006` | Every imported row records what it created, enabling precise rollback of exactly that batch's records. |
| `BR-CORE-11-007` | Rollback is available for `is_rollbackable` definitions and refuses if any created record has since been modified or referenced. |
| `BR-CORE-11-008` | Imports respect the period guard. Importing into a locked period is refused. |
| `BR-CORE-11-009` | Imports respect all normal validation and business rules. There is no bypass path — an import cannot create a record the UI would refuse. |
| `BR-CORE-11-010` ⭐ | **Opening balance import posts proper double-entry journals** through `FIN-01`. It never writes a balance column. The resulting trial balance must balance, and the import fails atomically if it does not. |
| `BR-CORE-11-011` | Import dependencies are enforced: guardians before learners, learners before balances, subjects before subject enrolments. |
| `BR-CORE-11-012` | Large imports are chunked, queued, and report live progress. A 3,000-row learner import must not time out. |
| `BR-CORE-11-013` | Every batch is retained with its source file for the audit retention period. |

### 5. Screens

| Screen | Component |
|---|---|
| Import centre | `Core\Import\Index` — available imports with dependency status and prerequisite warnings |
| Template download | `Core\Import\Template` — generates a formatted spreadsheet with headers, examples, and notes |
| Upload & map | `Core\Import\Mapper` — auto-maps by header similarity, manual override, saved mapping profiles |
| Validation report | `Core\Import\Validation` — summary, errors grouped by type, download correction file |
| Progress | `Core\Import\Progress` — live counters and current chunk |
| Batch history | `Core\Import\History` — with rollback |

### 6. Acceptance criteria

```gherkin
AC-CORE-11-001
  Given a learner file with 500 rows, 12 of which have invalid dates of birth
  When I run validation
  Then 488 rows are valid and 12 invalid
  And no records are created
  And I can download a correction file containing exactly those 12 rows with error text

AC-CORE-11-002
  Given an opening balance file totalling USD 240,000 in debtor balances
  When the import completes
  Then journal entries exist totalling USD 240,000 against the debtors control account
  And the trial balance balances
  And no balance column was written directly

AC-CORE-11-003
  Given an opening balance file whose debits and credits do not balance
  When I attempt the import
  Then the entire import is rejected
  And no journal entries are created

AC-CORE-11-004
  Given a learner import batch created 500 learners
  And none has been modified since
  When I roll back the batch
  Then exactly those 500 learners are removed
  And no other learner is affected

AC-CORE-11-005
  Given I attempt to import learners before importing guardians
  Then the import centre warns me of the unmet dependency
  And the import is blocked until guardians are imported
```

---

# CORE-12 · Jobs, Scheduling & Observability

### 1. Scope

Queue management, scheduled task registry, failed job handling, long-running job progress, system health, alerting, maintenance mode.

### 2. Data model

```sql
scheduled_tasks                       -- registry, seeded from code
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
key                 VARCHAR(80)  NOT NULL UNIQUE
module_code         VARCHAR(20)  NOT NULL
name                VARCHAR(150) NOT NULL
description         TEXT
command             VARCHAR(255) NOT NULL
schedule_expression VARCHAR(60)  NOT NULL   -- cron
is_enabled          TINYINT(1)   NOT NULL DEFAULT 1
is_per_school       TINYINT(1)   NOT NULL DEFAULT 0
timeout_seconds     INT          NOT NULL DEFAULT 300
alert_on_failure    TINYINT(1)   NOT NULL DEFAULT 1
alert_if_not_run_within_minutes INT NULL

scheduled_task_runs
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
task_id             BIGINT       FK INDEX
school_id           BIGINT       NULL FK
status              VARCHAR(20)  NOT NULL   -- running|completed|failed|timed_out
started_at          TIMESTAMP    NOT NULL
completed_at        TIMESTAMP    NULL
duration_ms         INT          NULL
output              TEXT         NULL
error               TEXT         NULL
  INDEX (task_id, started_at)

job_progress                          -- user-visible long-running work
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
user_id             BIGINT       FK
job_type            VARCHAR(80)  NOT NULL
title               VARCHAR(200) NOT NULL
status              VARCHAR(20)  NOT NULL  -- queued|running|completed|failed|cancelled
total_steps         INT          NULL
completed_steps     INT          NOT NULL DEFAULT 0
current_message     VARCHAR(255) NULL
result              JSON         NULL
error               TEXT         NULL
started_at, completed_at
  INDEX (user_id, status)

system_health_checks
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
check_key           VARCHAR(60)  NOT NULL
status              VARCHAR(20)  NOT NULL  -- healthy|degraded|unhealthy
value               VARCHAR(120) NULL
threshold           VARCHAR(120) NULL
message             TEXT         NULL
checked_at          TIMESTAMP    NOT NULL
  INDEX (check_key, checked_at)
```

### 3. Standard health checks

| Check | Healthy | Degraded | Unhealthy |
|---|---|---|---|
| Queue depth | < 100 | 100–1,000 | > 1,000 |
| Failed jobs (24h) | 0 | 1–10 | > 10 |
| Oldest queued job age | < 1 min | 1–10 min | > 10 min |
| Database connection | < 50 ms | 50–200 ms | > 200 ms or failing |
| Redis connection | reachable | slow | unreachable |
| Storage free space | > 20% | 10–20% | < 10% |
| Scheduler last run | < 2 min | 2–10 min | > 10 min |
| Fiscalisation queue depth 🇿🇼 | 0 | 1–50 | > 50 |
| Notification failure rate (1h) | < 2% | 2–10% | > 10% |
| Gateway reachability | all up | one down | payment gateway down |
| Trial balance status | balanced | — | imbalanced ⚠ critical |
| SMS/WhatsApp credit | > 20% of cap | 5–20% | < 5% |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-12-001` | Every queued job is tagged with its school so queue metrics and failures are attributable per tenant. |
| `BR-CORE-12-002` | Financial and fiscalisation jobs run on a dedicated high-priority queue, isolated from bulk work like report generation. A batch of 1,400 report cards must never delay a receipt. |
| `BR-CORE-12-003` | Every job is idempotent or guarded by a unique lock. Retrying must never double-post. |
| `BR-CORE-12-004` | Failed jobs are retained for 30 days and are retryable individually or in bulk from the UI. |
| `BR-CORE-12-005` | Jobs whose progress a user is waiting on write to `job_progress`, which the UI polls. |
| `BR-CORE-12-006` | Scheduled tasks marked `is_per_school` run once per active school, with the school context set. |
| `BR-CORE-12-007` | A task not run within `alert_if_not_run_within_minutes` raises an alert — a silent scheduler is a worse failure than a loud one. |
| `BR-CORE-12-008` | Maintenance mode shows a friendly, school-branded page and permits an IP allowlist for the deploying team. |
| `BR-CORE-12-009` | Any health check reporting unhealthy alerts the vendor operations channel. Trial balance imbalance additionally alerts the school's bursar. |

### 5. Acceptance criteria

```gherkin
AC-CORE-12-001
  Given 1,400 report card generation jobs are queued
  When a receipt is created requiring a fiscalisation job
  Then the fiscalisation job runs on the priority queue without waiting

AC-CORE-12-002
  Given a job fails and is retried
  When it runs a second time
  Then no duplicate record is created

AC-CORE-12-003
  Given the scheduler has not run for 15 minutes
  Then an alert is raised to the vendor operations channel

AC-CORE-12-004
  Given a bulk report generation is running
  When the requesting user views the progress screen
  Then they see live completed and total counts and the current step
```

---

# CORE-13 · Backup, Restore & Disaster Recovery

### 1. Scope

Scheduled encrypted backups, retention and rotation, restore verification, per-school logical export, restore runbook, contract-exit data export.

### 2. Data model

```sql
backups
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
type                VARCHAR(20)  NOT NULL   -- database|files|full|school_export
scope               VARCHAR(20)  NOT NULL   -- system|tenant|school
scope_id            BIGINT       NULL
disk                VARCHAR(30)  NOT NULL
path                VARCHAR(500) NOT NULL
size_bytes          BIGINT       NOT NULL
checksum            CHAR(64)     NOT NULL
is_encrypted        TINYINT(1)   NOT NULL DEFAULT 1
status              VARCHAR(20)  NOT NULL   -- running|completed|failed|verified|expired
verified_at         TIMESTAMP    NULL       -- test-restore verification
verification_notes  TEXT         NULL
started_at, completed_at
expires_at          TIMESTAMP    NULL
triggered_by        VARCHAR(20)  NOT NULL   -- schedule|manual|pre_upgrade
created_by          BIGINT       NULL FK
  INDEX (type, status, completed_at)

restore_tests
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
backup_id           BIGINT       FK INDEX
status              VARCHAR(20)  NOT NULL   -- running|passed|failed
target_environment  VARCHAR(60)  NOT NULL
checks_performed    JSON         NULL       -- row counts, trial balance, spot checks
duration_seconds    INT
error               TEXT         NULL
tested_at           TIMESTAMP    NOT NULL
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-CORE-13-001` | Database backups run at least daily, are encrypted at rest, and are stored on a different provider or region from the primary database. |
| `BR-CORE-13-002` | Every backup records a checksum verified immediately after upload. |
| `BR-CORE-13-003` ⭐ | **A backup that has never been test-restored is not a backup.** An automated restore test runs at least weekly against an isolated environment and asserts row counts, referential integrity, and a balanced trial balance. |
| `BR-CORE-13-004` | A failed backup or failed restore test raises a critical alert. Two consecutive failures escalate. |
| `BR-CORE-13-005` | Retention follows a grandfather-father-son policy: 7 daily, 4 weekly, 12 monthly, 7 yearly, all configurable. |
| `BR-CORE-13-006` | A full backup is taken before every upgrade and every migration, and its reference recorded on `system_upgrades`. |
| `BR-CORE-13-007` | Per-school logical export produces a complete, importable dataset for one school without affecting others. |
| `BR-CORE-13-008` | Contract-exit export delivers all of a school's data in open formats (CSV, JSON, plus original files) within the contractual window. This is a deliberate commercial commitment and it increases trust at the point of sale. |
| `BR-CORE-13-009` | Backup and restore operations are audited. Restores in production require dual authorisation. |
| `BR-CORE-13-010` | Backup status appears on the system health dashboard: last successful backup, last verified restore, next scheduled run. |

### 4. Acceptance criteria

```gherkin
AC-CORE-13-001
  Given the nightly backup completes
  Then its checksum is verified
  And the health dashboard shows the timestamp of the last successful backup

AC-CORE-13-002
  Given the weekly restore test runs against the latest backup
  Then it restores into an isolated environment
  And asserts row counts match
  And asserts the trial balance balances
  And records the outcome against the backup

AC-CORE-13-003
  Given two consecutive backups fail
  Then a critical alert escalates to the vendor operations lead

AC-CORE-13-004
  Given a school terminates its contract
  When a contract-exit export is requested
  Then all of that school's data is produced in CSV and JSON with original files
  And no other school's data is included
```

---

## Part 3 — Domain A Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **A1** | Part 1 foundations — Action base, `BelongsToSchool`, `BelongsToSession`, `Money`, context singletons, exception hierarchy, middleware stack, permission registry, testing harness | The tenancy isolation test generator runs and passes against a scaffold model |
| **A2** | `CORE-02` Tenancy & School Registry | A tenant with two schools exists; switching works; isolation tests pass |
| **A3** | `CORE-03` Session & Period Engine | Years, terms, weeks, state machines, period guard. Roll-over framework with a stub handler. **Every `AC-CORE-03-*` green.** |
| **A4** | `CORE-04` Settings, Flags & Custom Fields | Resolution chain verified across all seven scopes; custom fields surface in forms and API automatically |
| **A5** | `CORE-05` Identity & RBAC | Web login, API token flows, OTP flow, 2FA, scoped permissions. Full auth test suite green. |
| **A6** | `CORE-08` Audit & Integrity | Hash chain verified; DB grants confirmed revoked; integrity suite scheduled |
| **A7** | `CORE-06` Numbering & Templates | Concurrency test proves gapless allocation; template versioning proves historical fidelity |
| **A8** | `CORE-07` Workflow & Approvals | Chains, delegation, escalation, mobile approval endpoints |
| **A9** | `CORE-09` Notifications + `CORE-10` File Vault | Log-only channel driver for testing; recipient resolution verified; uploads with scanning and variants |
| **A10** | `CORE-11` Import + `CORE-12` Jobs + `CORE-13` Backup | Import framework with a learner importer; health dashboard; verified restore test |
| **A11** | `CORE-01` Installer | Full clean install from bare server; headless install; upgrade runner |

`CORE-01` is built **last** on purpose. You cannot write a reliable installer for a system whose shape you have not finished defining, and every earlier sprint develops against a seeded local environment anyway.

---

## Part 4 — Domain A Acceptance Gate

> Domain B does not begin until every item is green. No exceptions, no "we'll come back to it."

### Functional gate

- [ ] All `AC-CORE-01-*` through `AC-CORE-13-*` acceptance criteria pass
- [ ] Clean installation completes on a bare server in under 15 minutes
- [ ] Two tenants, four schools, and three academic years with full term structures exist in the demo dataset
- [ ] A user assigned to three schools can switch between them with correct permission re-resolution
- [ ] A term can be opened, soft-closed, locked, and reopened through the full dual-approval path
- [ ] A roll-over executes end to end with a stub financial handler and the invariant holds

### Security gate

- [ ] **Tenancy isolation suite passes for every registered model** — no cross-school read, write, update, or delete is possible
- [ ] Horizontal privilege escalation tested on every API endpoint
- [ ] `UPDATE` and `DELETE` confirmed revoked on `financial_audit_log` and `period_state_transitions` for the application database user
- [ ] Refresh token reuse revokes the device family and raises a security event
- [ ] Impersonation cannot perform financial mutations or bulk exports
- [ ] Uploaded files cannot be retrieved without a valid signed URL
- [ ] All sensitive settings confirmed encrypted at rest and redacted in logs and exports

### Integrity gate

- [ ] Financial audit hash chain verification passes over 10,000 seeded events
- [ ] Snapshot hash chain verification passes
- [ ] Numbering concurrency test: 200 parallel allocations produce 200 unique, gapless numbers
- [ ] Template regeneration produces byte-identical output from an archived version
- [ ] Every integrity check is registered, scheduled, and alerting correctly

### Quality gate

- [ ] PHPStan level 8 clean, no new baseline entries
- [ ] Pint clean
- [ ] Overall coverage ≥ 80%
- [ ] The static analysis rule forbidding direct writes in Livewire components and API controllers is active and passing
- [ ] `migrate:fresh` from zero succeeds in CI
- [ ] Every module declares a complete `module.json` manifest

### Documentation gate

- [ ] Every Domain A permission registered and described
- [ ] Every Domain A setting registered with a default and description
- [ ] OpenAPI specification generated and validated for all Domain A endpoints
- [ ] Restore runbook written and rehearsed once by someone who did not write it

---

## Appendix A — Domain A Permission Registry

```
core.school.view                  core.school.view.group
core.school.create                core.school.update
core.school.archive               core.school.assign_user
core.structure.view               core.structure.manage
core.module.view                  core.module.manage
core.session.view                 core.session.manage
core.period.view                  core.period.close
core.period.reopen ⚠              core.period.approve_reopen ⚠
core.period.rollover ⚠            core.period.override_soft_close ⚠
core.settings.view                core.settings.update
core.custom_field.view            core.custom_field.manage
core.feature_flag.view            core.feature_flag.manage
core.profile.export               core.profile.import
core.user.view                    core.user.create
core.user.update                  core.user.deactivate
core.user.reset_password          core.user.impersonate ⚠
core.role.view                    core.role.create
core.role.update                  core.role.delete
core.role.assign                  core.permission.view
core.numbering.view               core.numbering.manage
core.template.view                core.template.create
core.template.update              core.template.delete
core.document.view                core.document.generate
core.document.download            core.document.delete ⚠
core.approval.view                core.approval.configure
core.approval.cancel              core.approval.delegate_others
core.approval.view_reports
core.audit.view                   core.audit.view_financial
core.audit.view_security          core.audit.view_access
core.audit.export                 core.audit.review_security_event
core.notification.view            core.notification.manage_templates
core.notification.manage_budget
core.file.view                    core.file.upload
core.file.delete                  core.file.view_sensitive ⚠
core.import.view                  core.import.run
core.import.rollback ⚠
core.system.health                core.system.maintenance ⚠
core.system.bypass_school_scope ⚠⚠ (vendor only, never granted to a customer role)
core.backup.view                  core.backup.create
core.backup.restore ⚠⚠            core.backup.export_school
```

⚠ = requires UI confirmation and is highlighted in the role editor.
⚠⚠ = vendor-only; cannot be assigned to any customer role template.

---

## Appendix B — Next Book

**Book B — Domain D Financial Core (`FIN-01` → `FIN-06`)** covers the General Ledger, the Fee & Billing Engine including full-time and part-time models, Invoicing and Debtor Management, Receipting and Till Control, Payment Gateways and Reconciliation, and the Multi-Currency and FX Engine.

It is the hardest book in the set and the one on which the commercial case rests. It assumes every rule in this book is implemented and passing.

---

*End of Volume 2, Book A.*

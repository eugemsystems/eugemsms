# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book J — Domain I & J: Intelligence & SaaS Control (`INT-01`–`INT-04`, `SAA-01`–`SAA-03`)

| Field | Value |
|---|---|
| Document | Volume 2, Book J of 11 *(revised from 10 — see Appendix B)* |
| Covers | Reporting Engine & Data Warehouse · Executive Dashboards · Early Warning & Predictive Analytics · Public API & Integrations · Licensing & Subscription · Vendor Control Centre · Onboarding & Customer Success |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A–I complete.** This book has the highest density of cross-references to earlier infrastructure of any book in the set. |
| Companion | **Book K — the six modules from Volume 1's catalogue not yet built.** See Appendix B. |

---

## Part 0 — Read This First

### 0.1 ⭐ The "don't rebuild it" map

This book sits on top of nearly everything already specified. Before writing a line of code for any module below, check this table — every row is something a developer will be tempted to reimplement, and every time, the existing infrastructure is correct and this book must consume it, not duplicate it.

| Temptation | Already built in | What this book actually adds |
|---|---|---|
| A new permission-scoping system for reports | `CORE-05` (Book A) — scoped permissions, `BelongsToSchool` | `INT-01` queries through the same Eloquent models with the same scopes. It never issues raw SQL. |
| A new cross-school query mechanism | `CORE-02` `forSchools()` (Book A) | `INT-01`'s consolidated reporting calls exactly that method. |
| A new dashboard widget system | `COM-03/04/05` `dashboard_widgets` (Book I) | `INT-02` registers **executive-persona** widgets in the same registry, not a parallel one. |
| A new anomaly-detection engine for stock or fuel | `FIN-09` consumption anomalies, `OPS-01` fuel anomalies (Book H1/H2) | `INT-03` is about **people** — learners and staff. It surfaces those existing commodity anomalies on the executive dashboard; it does not recompute them. |
| A new accounting export | `FIN-12` `accounting_exports` (Book H3) | `INT-04`'s public API can expose it to a third-party integration; the export logic itself is not rebuilt. |
| A new full-data export for leaving customers | `CORE-13` contract-exit export (Book A) | `INT-04` is for **live, ongoing** third-party integrations via API keys — a different problem from a one-time full dump on contract termination. |
| A new impersonation mechanism | `CORE-05` impersonation (Book A) | `SAA-02` is the **vendor-side console** that initiates it; the guardrails, audit, and financial-action lockout are `CORE-05`'s, unchanged. |
| A new feature-flag system | `CORE-04` `feature_flags` (Book A) | `SAA-02` is where the vendor operates the rollout; the flag mechanics are `CORE-04`'s. |
| A new module-entitlement table | `CORE-02` `school_modules` (Book A) | `SAA-01`'s subscription engine **writes to** that exact table on plan change. It does not own a second one. |
| A new configuration-cloning mechanism | `CORE-04` `configuration_profiles` (Book A) | `SAA-03`'s onboarding templates are configuration profiles. Same table. |
| A new support-ticket system | `COM-08` complaints (Book I) | Different audience entirely — see §0.2. `SAA-03` is genuinely new. |

### 0.2 ⭐ Two audiences that must never be confused

Every other book in this specification is **school-facing**: it serves the people inside one school — its staff, its parents, its learners. This book introduces the first genuinely **vendor-facing** modules, and the two must never share a screen, a guard, or a permission.

```
SCHOOL-FACING (everything through Book I)          VENDOR-FACING (SAA-01/02/03, this book)
──────────────────────────────────────────         ─────────────────────────────────────────
Guard: web (Livewire) or sanctum (API)              Guard: vendor — a THIRD, separate guard
Scoped to: one school at a time                     Scoped to: across all tenants
A parent's complaint about the school (COM-08)      A school's support ticket about the SOFTWARE
Who: Head, Bursar, teachers, parents, learners      Who: Anthropic support and success staff only
```

`SAA-02`'s console is not a "super admin" screen inside the normal application. It is a **separate authentication realm**, IP-allowlisted, that happens to run from the same codebase. A school's Head Teacher, however senior, has no path into it — not through a permission, not through a role, not at all.

### 0.3 Build order

```
INT-01  Reporting Engine            ← the field registry other INT modules assume exists
   ↓
INT-02  Executive Dashboards        ← consumes INT-01's warehouse
   ↓
INT-03  Early Warning               ← consumes INT-01's warehouse + existing anomaly feeds
   ↓
INT-04  Public API & Integrations   ← independent; can run in parallel from the start
   ↓
SAA-01  Licensing & Subscription    ← must exist before SAA-02/03 have anything to operate on
   ↓
SAA-02  Vendor Control Centre  ─┐
SAA-03  Onboarding & Success   ─┘  parallel
```

---

# INT-01 · Reporting Engine & Data Warehouse

### 1. Scope

**In scope.** The field registry every module contributes to, the permission-scoped ad hoc query builder, saved and shared reports, scheduled delivery, denormalised nightly warehouse tables, cross-school consolidation, export, execution audit.

**Out of scope.** Any specific pre-built report — trial balance, aged debtors, EMIS census — those belong to their owning module and remain there. `INT-01` is the tool a school uses to build the report nobody thought to pre-build.

### 2. Data model

```sql
report_fields                         -- ⭐ the registry every module contributes to
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
module_code             VARCHAR(20)  NOT NULL
entity_key               VARCHAR(60)  NOT NULL   -- 'student','invoice','staff'
field_key                  VARCHAR(80)  NOT NULL   -- 'gender','balance_minor'
label                        VARCHAR(150) NOT NULL
data_type                     VARCHAR(20)  NOT NULL   -- string|number|money|date|
                                                      -- boolean|enum
is_filterable                  TINYINT(1)   NOT NULL DEFAULT 1
is_groupable                     TINYINT(1)   NOT NULL DEFAULT 1
is_aggregatable                    TINYINT(1)   NOT NULL DEFAULT 0   -- sum/avg/count
required_permission                  VARCHAR(120) NOT NULL   -- ⭐ enforced per field
is_sensitive                           TINYINT(1)   NOT NULL DEFAULT 0   -- extra gate
enum_options                             JSON         NULL
  UNIQUE (module_code, entity_key, field_key)
  INDEX  (entity_key, is_filterable)

report_entities                       -- what can be reported on, and how it joins
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
entity_key                VARCHAR(60)  NOT NULL UNIQUE
module_code                 VARCHAR(20)  NOT NULL
base_model_class              VARCHAR(255) NOT NULL   -- the Eloquent model, ⭐
                                                       -- queried through its OWN
                                                       -- scopes, never raw SQL
default_school_scoped           TINYINT(1)   NOT NULL DEFAULT 1
allowed_join_entity_keys         JSON         NULL      -- explicit whitelist

custom_reports
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
name                     VARCHAR(150) NOT NULL
description                VARCHAR(255) NULL
primary_entity_key           VARCHAR(60)  NOT NULL
selected_fields                 JSON         NOT NULL   -- [{entity, field, alias}]
filters                            JSON         NULL      -- condition tree, same
                                                          -- shape as COM-02's
group_by                             JSON         NULL
aggregations                           JSON         NULL     -- [{field, function}]
sort                                     JSON         NULL
chart_type                                VARCHAR(20)  NULL     -- table|bar|line|pie
created_by                                  BIGINT       FK → users.id
is_active                                     TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, primary_entity_key)

report_shares
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
report_id                 BIGINT       FK INDEX
shared_with_type              VARCHAR(20)  NOT NULL   -- user|role
shared_with_id                  BIGINT       NOT NULL
can_edit                          TINYINT(1)   NOT NULL DEFAULT 0
shared_by                            BIGINT       FK → users.id
  UNIQUE (report_id, shared_with_type, shared_with_id)

report_schedules
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
report_id                 BIGINT       FK
frequency                   VARCHAR(20)  NOT NULL   -- daily|weekly|monthly|termly
recipients                     JSON         NOT NULL
format                           VARCHAR(20)  NOT NULL   -- pdf|excel|csv
next_run_at                        TIMESTAMP    NULL
is_active                            TINYINT(1)   NOT NULL DEFAULT 1

report_executions                     -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
report_id                 BIGINT       NULL FK   -- null for ad hoc, unsaved runs
executed_by                 BIGINT       FK → users.id
row_count                     INT          NULL
duration_ms                     INT
schools_included                  JSON         NULL   -- for consolidated runs
executed_at                          TIMESTAMP(6) NOT NULL
  INDEX (school_id, executed_at)

warehouse_snapshots                   -- nightly denormalised tables, one per entity
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
entity_key                VARCHAR(60)  NOT NULL
snapshot_date                DATE         NOT NULL
row_count                       INT          NOT NULL
rebuilt_at                        TIMESTAMP    NOT NULL
duration_ms                          INT
  UNIQUE (school_id, entity_key, snapshot_date)
```

### 3. ⭐ Why the builder cannot leak data — structurally, not by convention

The single most dangerous thing a generic report builder can do is let a user construct a query that reaches data their role would never let them see through a normal screen. The defence is architectural, not a checklist:

```php
final class ExecuteCustomReportAction extends Action
{
    public function execute(CustomReport $report, User $runner): ReportResult
    {
        $entity = ReportEntity::where('entity_key', $report->primary_entity_key)->firstOrFail();

        // ⭐ The query starts from the REAL Eloquent model, never a raw table name.
        // BelongsToSchool's global scope applies automatically. There is no
        // code path in this action that can bypass it.
        $query = $entity->base_model_class::query();

        foreach ($report->selected_fields as $selection) {
            $field = $this->fieldRegistry->resolve($selection['entity'], $selection['field']);

            // ⭐ Every field is permission-checked against the RUNNING USER,
            // not against the report's creator. A report saved by the head
            // and shared with a class teacher exposes only what the class
            // teacher could see if they built it themselves.
            if (! $runner->hasPermission($field->required_permission)) {
                throw new InsufficientScopeException($field);
            }
            if ($field->is_sensitive && ! $runner->hasPermission('report.sensitive_field.access')) {
                throw new InsufficientScopeException($field);
            }

            $query->addSelect($field->qualifiedColumn());
        }

        foreach ($report->filters as $filterGroup) {
            $this->conditionBuilder->apply($query, $filterGroup, $this->fieldRegistry, $runner);
        }

        // Joins are restricted to the entity's explicit whitelist — a report
        // author cannot join arbitrarily across the schema.
        $this->joinResolver->apply($query, $report, $entity->allowed_join_entity_keys);

        return $this->paginateAndExecute($query, $report);
    }
}
```

**The consequence of this design.** A class teacher building a report can select fields from `students` and `attendance_records` because those permissions exist on their role. They cannot select `staff.basic_salary_minor` because `report_fields` marks it `required_permission = 'staff.view_compensation'`, which they do not hold — the field is simply absent from their field picker, and even a crafted request naming it directly is rejected server-side by the same check.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-INT-01-001` ⭐ | The query builder never constructs raw SQL from user input. Every query runs through the owning entity's real Eloquent model, inheriting every existing scope and policy. |
| `BR-INT-01-002` | A field is available to a report author if and only if their live permission set includes `report_fields.required_permission` for it, evaluated at **execution time**, not at report-save time — a permission revoked after a report was built silently narrows what that report can still return. |
| `BR-INT-01-003` | Fields marked `is_sensitive` require the additional `report.sensitive_field.access` permission on top of the field's own requirement, for data classes (medical existence flags, compensation) that warrant a second gate even for users who technically hold the base permission. |
| `BR-INT-01-004` | Joins are restricted to an entity's explicit `allowed_join_entity_keys` whitelist, set by the owning module, not discovered by the builder from foreign keys. |
| `BR-INT-01-005` | A report shared with a user or role is re-evaluated against the **viewer's** permissions on every run, never the creator's. |
| `BR-INT-01-006` | Cross-school consolidated reporting is available only where `is_group_reporting_enabled` on the tenant and `core.school.view.group` on the user both hold, and executes through `forSchools()`, never a manual `school_id IN (...)` constructed by this module. |
| `BR-INT-01-007` | Nightly warehouse rebuilds run after all other nightly jobs (balance rebuilds, integrity checks) so denormalised figures never race a same-night correction. |
| `BR-INT-01-008` | Ad hoc reports over live tables are capped at a configurable row and time budget; anything larger is redirected to run against the warehouse snapshot or as a scheduled background export. |
| `BR-INT-01-009` | Every execution — ad hoc or scheduled, saved or thrown away — writes to `report_executions`, so the platform can see which reports actually get used before deciding what to pre-build next. |
| `BR-INT-01-010` | Scheduled delivery follows exactly the recipient and channel machinery of `CORE-09`; this module supplies the content, not a second delivery mechanism. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| **Report builder** | `Intelligence\Reports\Builder` | `report.build` ⭐ — entity picker, field picker (permission-filtered live), condition groups, group/aggregate, chart preview |
| My reports | `Intelligence\Reports\Index` | `report.build` |
| Shared with me | `Intelligence\Reports\Shared` | — |
| Schedule | `Intelligence\Reports\Schedule` | `report.schedule` |
| Execution log | `Intelligence\Reports\ExecutionLog` | `report.view_audit` |

```
GET  /api/v1/reports/entities              field-permission-filtered for the caller
POST /api/v1/reports/{ulid}/run
GET  /api/v1/reports/{ulid}/export         ?format=pdf|excel|csv
```

| Setting | Type | Default |
|---|---|---|
| `reporting.ad_hoc_row_limit` | int | `50000` |
| `reporting.ad_hoc_time_budget_seconds` | int | `30` |
| `reporting.warehouse_rebuild_hour` | int | `4` |

### 6. Acceptance criteria

```gherkin
AC-INT-01-001
  Given a class teacher builds a report
  Then staff.basic_salary_minor does not appear in their field picker
  And a crafted API request naming it directly is refused server-side

AC-INT-01-002
  Given a report was built by the head and shared with a class teacher
  When the class teacher runs it
  Then only fields the class teacher's own permissions allow are returned,
       even though the head could see more

AC-INT-01-003
  Given a permission is revoked from a user after they saved a report
       using a field that permission gated
  When they next run the report
  Then that field is absent from the result

AC-INT-01-004
  Given cross-school consolidation is requested by a user without
       core.school.view.group
  Then the request is refused regardless of tenant settings

AC-INT-01-005
  Given an ad hoc query would scan 200,000 rows
  Then it is redirected to the warehouse snapshot or scheduled background run
       rather than executing live
```

---

# INT-02 · Executive Dashboards

### 1. Scope

**In scope.** Role-based dashboards for Head, Bursar, and Board built from `COM-03`'s widget registry under the executive persona, KPI targets with variance highlighting, daily digest, comprehensive board pack assembly, period-over-period comparatives.

**Out of scope.** The widget rendering mechanics and dashboard aggregation pattern — those are `COM-03`'s. `FIN-12`'s financial-only board pack, which this module's comprehensive pack incorporates as one section rather than replaces.

### 2. Data model

```sql
kpi_definitions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       NULL FK    -- null = system default
key                       VARCHAR(60)  NOT NULL UNIQUE   -- 'collection_rate'
module_code                 VARCHAR(20)  NOT NULL
label                          VARCHAR(150) NOT NULL
unit                              VARCHAR(20)  NOT NULL   -- percent|currency|count
data_source_endpoint                VARCHAR(200) NOT NULL
higher_is_better                       TINYINT(1)   NOT NULL DEFAULT 1
default_target_value                     DECIMAL(14,2) NULL

kpi_targets                           -- per-school, per-year overrides
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
kpi_key                   VARCHAR(60)  FK
academic_year_id             BIGINT       FK
target_value                    DECIMAL(14,2) NOT NULL
warning_threshold_percent          DECIMAL(5,2) NOT NULL DEFAULT 90
  UNIQUE (school_id, kpi_key, academic_year_id)

executive_digests
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
recipient_user_id         BIGINT       FK
digest_date                 DATE         NOT NULL
content_summary                JSON         NOT NULL   -- the KPIs and exceptions sent
delivered_via                     VARCHAR(20)  NOT NULL   -- email|whatsapp
sent_at                              TIMESTAMP    NULL
  UNIQUE (school_id, recipient_user_id, digest_date)

board_packs
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
sections_included           JSON         NOT NULL   -- enrolment|academic|financial|
                                                     -- boarding|staffing|risk_summary
document_id                    BIGINT       NULL FK   -- CORE-06
generated_by                      BIGINT       FK → users.id
generated_at                         TIMESTAMP    NOT NULL
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-INT-02-001` | Executive widgets register in `COM-03`'s `dashboard_widgets` table under `persona = 'executive'`, resolved by the same widget resolver pattern, not a second one. |
| `BR-INT-02-002` | A KPI without a school-specific target falls back to the system default; a school may override any target for its own year. |
| `BR-INT-02-003` | Variance highlighting compares the current value against the target and the `warning_threshold_percent`, colour-coding green/amber/red — never a bare number with no context. |
| `BR-INT-02-004` | The daily digest summarises exceptions, not routine status — an all-green day produces a short confirmation, not eleven KPI values nobody needs to re-read. |
| `BR-INT-02-005` | Digest delivery uses `CORE-09` exclusively and respects the head's own channel preference. |
| `BR-INT-02-006` | The comprehensive board pack assembles `FIN-12`'s financial pack as one section alongside enrolment, academic outcomes, boarding occupancy, staffing, and — where `INT-03` is enabled — a risk summary, in one document. |
| `BR-INT-02-007` | Every widget drills down to the owning module's own detail screen; this module never builds a parallel detail view. |
| `BR-INT-02-008` | Period-over-period comparatives pull from `warehouse_snapshots` for anything beyond the current term, never a live re-aggregation over years of history. |

### 4. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Head dashboard | `Intelligence\Executive\HeadDashboard` | `executive.dashboard.view` |
| Bursar dashboard | `Intelligence\Executive\BursarDashboard` | `executive.dashboard.view.finance` |
| KPI targets | `Intelligence\Executive\Kpis` | `executive.kpi.manage` |
| Board pack | `Intelligence\Executive\BoardPack` | `executive.board_pack.generate` |

```
GET  /api/v1/executive/dashboard          ?persona=head|bursar
GET  /api/v1/executive/kpis
POST /api/v1/executive/board-pack
```

### 5. Acceptance criteria

```gherkin
AC-INT-02-001
  Given collection rate is 87% against an 92% target with a 90% warning threshold
  Then the KPI renders red, not merely a number

AC-INT-02-002
  Given every KPI is within target on a given day
  Then the digest is short and confirmatory, not a full re-listing

AC-INT-02-003
  Given a board pack is generated
  Then it includes FIN-12's financial section unmodified alongside
       enrolment, academic, boarding, and staffing sections

AC-INT-02-004
  Given a three-year enrolment comparative is requested
  Then it reads from warehouse snapshots, not a live cross-year aggregation
```

---

# INT-03 · Early Warning & Predictive Analytics

### 1. Scope

**In scope.** Composite, explainable at-risk learner identification; fee default risk; enrolment forecasting and attrition risk; staff workload and burnout indicators; capacity planning projections; surfacing — never recomputing — the commodity anomaly detection already built elsewhere.

**Out of scope.** `FIN-09` stock/consumption anomalies and `OPS-01` fuel anomalies, which are already fully specified and simply feed this module's dashboard as read-only signals.

### 2. Data model

```sql
risk_indicators                       -- ⭐ the registry of signals, each module's own
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
key                       VARCHAR(60)  NOT NULL UNIQUE   -- 'attendance_decline'
module_code                 VARCHAR(20)  NOT NULL
applies_to                     VARCHAR(20)  NOT NULL   -- learner|staff
data_source_query                 VARCHAR(200) NOT NULL   -- calls the OWNING
                                                          -- module's own query,
                                                          -- e.g. ACA-04's
                                                          -- attendance_summaries
default_weight                       DECIMAL(5,2) NOT NULL
plain_language_description              VARCHAR(255) NOT NULL   -- ⭐ for the
                                                                -- explanation, not
                                                                -- a technical label

risk_score_weights                    -- per-school configurable weighting
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
indicator_key              VARCHAR(60)  FK
weight                        DECIMAL(5,2) NOT NULL
is_enabled                       TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, indicator_key)

learner_risk_scores                   -- CACHE, rebuilt nightly
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id                 BIGINT       FK INDEX
term_id                      BIGINT       FK
composite_score                 DECIMAL(5,2) NOT NULL   -- 0-100
risk_band                          VARCHAR(20)  NOT NULL   -- low|medium|high|critical
contributing_factors                  JSON         NOT NULL   -- ⭐ THE EXPLANATION
computed_at                              TIMESTAMP    NOT NULL
  UNIQUE (school_id, student_id, term_id)
  INDEX  (school_id, term_id, risk_band)

fee_default_risk_scores               -- CACHE, rebuilt nightly
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id                 BIGINT       FK INDEX
guardian_id                  BIGINT       FK
risk_score                     DECIMAL(5,2) NOT NULL
contributing_factors              JSON         NOT NULL
recommended_action                   VARCHAR(60)  NULL   -- 'offer_payment_plan'
computed_at                              TIMESTAMP    NOT NULL
  UNIQUE (school_id, guardian_id)

enrolment_forecasts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id           BIGINT       FK
grade_level_id                BIGINT       FK
projected_intake                 SMALLINT     NULL
projected_attrition                 SMALLINT     NULL
confidence_band                        VARCHAR(20)  NOT NULL   -- based on data volume
basis_note                                VARCHAR(500) NOT NULL   -- ⭐ methodology
                                                                  -- stated plainly
computed_at                                  TIMESTAMP    NOT NULL

withdrawal_risk_flags
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id                 BIGINT       FK INDEX
flagged_at                    TIMESTAMP    NOT NULL
contributing_factors             JSON         NOT NULL
status                              VARCHAR(20)  NOT NULL   -- open|
                                                            -- intervention_logged|
                                                            -- resolved|withdrawn
reviewed_by                             BIGINT       NULL FK
intervention_note                          TEXT         NULL

staff_wellbeing_indicators             -- CACHE, rebuilt weekly
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
staff_id                   BIGINT       FK INDEX
term_id                       BIGINT       FK
workload_utilisation_percent      DECIMAL(5,2) NULL   -- from PPL-04 staff_workload
consecutive_terms_over_ceiling       TINYINT      NOT NULL DEFAULT 0
sick_leave_days_trend                   VARCHAR(20)  NULL   -- stable|rising
flag_level                                 VARCHAR(20)  NOT NULL   -- none|watch|concern
  UNIQUE (school_id, staff_id, term_id)
```

### 3. ⭐ Explainability — a worked example, not a promise

Volume 1's ethical constraint is binding: *"Every flag is explainable and advisory. No automated adverse decision is taken about a child without a human in the loop, and risk scores are never exposed to learners or parents."* Here is exactly what that produces on screen.

```json
{
  "student": { "admission_no": "SGC/2023/0412" },
  "composite_score": 68.5,
  "risk_band": "high",
  "contributing_factors": [
    {
      "indicator": "attendance_decline",
      "plain_language": "Attendance has fallen from 94% to 71% over the last two terms",
      "weight": 30, "contribution": 21.0,
      "source": "ACA-04 attendance_summaries, terms 2026-T1 and 2026-T2"
    },
    {
      "indicator": "mark_trajectory",
      "plain_language": "Average mark has declined in 3 of 4 core subjects this term",
      "weight": 25, "contribution": 17.5,
      "source": "ACA-05 term_subject_results, trend over 2 terms"
    },
    {
      "indicator": "fee_arrears",
      "plain_language": "Fee account is 45 days overdue",
      "weight": 20, "contribution": 15.0,
      "source": "FIN-03 invoices, days_overdue"
    },
    {
      "indicator": "clinic_visits",
      "plain_language": "6 sick bay admissions this term, above the school's baseline",
      "weight": 15, "contribution": 9.0,
      "source": "BRD-06 sick_bay_admissions, count this term"
    },
    {
      "indicator": "disciplinary_frequency",
      "plain_language": "2 behaviour incidents this term, within normal range",
      "weight": 10, "contribution": 6.0,
      "source": "BRD-07 behaviour_records, count this term"
    }
  ],
  "note": "This is a composite indicator for pastoral awareness. It does not
           diagnose a cause and is not shown to the learner or their guardians.
           A staff member reviewing this case should treat it as a prompt to
           look closer, not as a conclusion."
}
```

**Every number on that screen traces to a real record in a module already built.** There is no black-box model scoring a child from opaque features; there is a weighted sum of plain-language, individually verifiable facts, and the pastoral team can click through to the source of any one of them.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-INT-03-001` ⭐ | Every risk score decomposes into `contributing_factors`, each with a plain-language description, its weight, its contribution, and a link to the source record. A score with no decomposition is a build defect, not a shippable feature. |
| `BR-INT-03-002` ⭐ | Risk scores and their factors are **never** exposed to the learner or guardian portals, in any form, at any permission level. |
| `BR-INT-03-003` | Indicator weights are configurable per school within registered bounds; a school may disable an indicator entirely (a school with no clinic module cannot weight clinic visits) but not invent a new one without the owning module registering it first. |
| `BR-INT-03-004` | Composite scores are advisory and route to a human review queue. No rule in this module triggers an automated intervention, sanction, or communication to a guardian — every downstream action is a person's decision. |
| `BR-INT-03-005` | Scores recompute nightly from source data, never manually edited, and a learner's history of scores over time is retained for trend review, not overwritten. |
| `BR-INT-03-006` | Fee default risk recommends an action (payment plan offer, early contact) but never auto-triggers a collections process; `FIN-03`'s existing reminder ladder is untouched by this module. |
| `BR-INT-03-007` | Enrolment forecasts state their confidence band and basis plainly, and a forecast built on fewer than the configured minimum terms of history is marked low-confidence rather than hidden. |
| `BR-INT-03-008` | Withdrawal risk flags open a review record; closing one without an intervention note is refused, so the flag either produces a documented action or a documented decision not to act. |
| `BR-INT-03-009` | Staff wellbeing indicators reuse `PPL-04`'s `staff_workload` cache directly rather than recomputing utilisation. |
| `BR-INT-03-010` | A staff member's own wellbeing indicator is visible to them and to their line manager, never broadcast more widely, consistent with the confidentiality standard `PPL-04`'s disciplinary records already carry. |
| `BR-INT-03-011` ⭐ | This module's dashboard **surfaces** `FIN-09` and `OPS-01`'s existing anomaly records read-only. It contains no duplicate anomaly-detection logic. |
| `BR-INT-03-012` | Capacity planning projections combine `INT-03`'s own enrolment forecast with `BRD-01`'s hostel capacity and `ACA-03`'s venue capacity, read from those modules, not recalculated. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| **At-risk review queue** | `Intelligence\EarlyWarning\Queue` | `risk.review` ⭐ — banded, sortable, with the full factor breakdown one click away |
| Learner risk detail | `Intelligence\EarlyWarning\StudentDetail` | `risk.review` — the worked example above, rendered |
| Fee default risk | `Intelligence\EarlyWarning\FeeRisk` | `finance.report.view` |
| Enrolment forecast | `Intelligence\EarlyWarning\Enrolment` | `executive.dashboard.view` |
| Staff wellbeing | `Intelligence\EarlyWarning\StaffWellbeing` | `staff.wellbeing.view` — line managers, own record |
| Indicator weights | `Intelligence\EarlyWarning\Weights` | `risk.configure` |

```
GET /api/v1/risk/at-risk-learners      ?band=high|critical
GET /api/v1/risk/students/{ulid}       full factor breakdown, staff-only
GET /api/v1/risk/staff-wellbeing/me    own record
```

| Setting | Type | Default |
|---|---|---|
| `risk.recompute_hour` | int | `3` |
| `risk.forecast_minimum_terms` | int | `6` |
| `risk.staff_workload_watch_threshold_percent` | int | `110` |

Events: `LearnerFlaggedHighRisk` · `WithdrawalRiskFlagged` · `StaffWellbeingConcern` ⚠ · `RiskScoreRecomputed`

### 6. Acceptance criteria

```gherkin
AC-INT-03-001
  Given a learner's composite risk score is computed
  Then every contributing factor has a plain-language description,
       a weight, a contribution, and a source reference

AC-INT-03-002
  Given a learner or guardian is authenticated
  Then no risk score or factor is reachable through any endpoint they can call

AC-INT-03-003
  Given a risk score reaches the critical band
  Then a review queue entry is created
  And no automated communication to the guardian is triggered

AC-INT-03-004
  Given a withdrawal risk flag is reviewed
  When closure is attempted with no intervention note
  Then it is refused

AC-INT-03-005
  Given the executive dashboard displays commodity consumption anomalies
  Then the figures are read directly from FIN-09's existing records
  And no duplicate anomaly computation exists in this module

AC-INT-03-006
  Given a school has fewer than 6 terms of enrolment history
  Then any forecast produced is marked low-confidence, not withheld silently
```

---

# INT-04 · Public API, Webhooks & Integrations

### 1. Scope

**In scope.** Third-party developer API keys and scoped abilities, OpenAPI specification, outbound webhook subscriptions, SSO provisioning for Google Workspace/Microsoft 365, the generic hardware adapter registry, rate limiting and usage analytics.

**Out of scope.** `CORE-13`'s contract-exit export (a one-time full dump) and `FIN-12`'s accounting export (a specific pre-built integration) — both already built; this module is the general-purpose, ongoing, third-party-facing surface, distinct from both.

### 2. Data model

```sql
api_clients                           -- third-party integrations, not internal apps
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
name                     VARCHAR(150) NOT NULL   -- 'Zapier connector','Custom BI tool'
client_type                VARCHAR(20)  NOT NULL   -- integration|hardware_device
contact_email                 VARCHAR(150) NULL
api_key_hash                     VARCHAR(255) NOT NULL   -- hashed, never stored plain
scoped_abilities                    JSON         NOT NULL   -- explicit allow-list
rate_limit_per_minute                  INT          NOT NULL DEFAULT 60
ip_allowlist                              JSON         NULL
is_active                                    TINYINT(1)   NOT NULL DEFAULT 1
last_used_at                                    TIMESTAMP    NULL
created_by, created_at, revoked_at, revoked_by
  INDEX (school_id, is_active)

api_usage_log
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
client_id                  BIGINT       FK INDEX
endpoint                     VARCHAR(200) NOT NULL
method                          VARCHAR(10)  NOT NULL
status_code                        SMALLINT     NOT NULL
duration_ms                           INT
occurred_at                              TIMESTAMP(6) NOT NULL
  INDEX (school_id, client_id, occurred_at)

webhook_subscriptions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
client_id                  BIGINT       FK
event_names                   JSON         NOT NULL   -- ['InvoiceIssued','ReceiptVoided']
target_url                       VARCHAR(500) NOT NULL
signing_secret                      VARCHAR(200) NOT NULL   -- ENCRYPTED
is_active                              TINYINT(1)   NOT NULL DEFAULT 1
consecutive_failures                      SMALLINT     NOT NULL DEFAULT 0

webhook_deliveries                    -- APPEND-ONLY, mirrors the payment-gateway pattern
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
subscription_id            BIGINT       FK INDEX
event_name                    VARCHAR(80)  NOT NULL
payload                          JSON         NOT NULL
attempt_count                       SMALLINT     NOT NULL DEFAULT 0
status                                 VARCHAR(20)  NOT NULL   -- pending|delivered|
                                                                -- failed|abandoned
response_status                           SMALLINT     NULL
last_attempted_at                            TIMESTAMP    NULL
  INDEX (school_id, status)

sso_provisioning_configs
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
provider                  VARCHAR(30)  NOT NULL   -- google_workspace|microsoft_365
domain                       VARCHAR(120) NOT NULL
credentials                     TEXT         NOT NULL   -- ENCRYPTED
auto_provision_staff                TINYINT(1)   NOT NULL DEFAULT 0
sync_status                            VARCHAR(20)  NOT NULL
last_synced_at                            TIMESTAMP    NULL
  UNIQUE (school_id, provider)

hardware_devices                      -- ⭐ the registry every device_source column implies
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
device_type                VARCHAR(30)  NOT NULL   -- rfid_reader|biometric|
                                                   -- qr_scanner|gate_terminal
location                       VARCHAR(150) NULL
purpose                           VARCHAR(40)  NULL   -- gate|classroom|dining|
                                                       -- till
api_client_id                        BIGINT       FK   -- its own scoped token
firmware_version                        VARCHAR(30)  NULL
last_heartbeat_at                          TIMESTAMP    NULL
status                                        VARCHAR(20)  NOT NULL   -- online|
                                                                      -- offline|fault
  INDEX (school_id, device_type, status)
```

### 3. ⭐ Every `device_source` column in this specification, closed here

`BRD-02` roll call, `ACA-04` attendance, `FIN-04` till operations, `OPS-01` boarding capture, `BRD-03` gate scans — every one of these carries a `device_source` value like `rfid`, `biometric`, or `qr`, and none of those books specified how a physical reader actually authenticates to push a scan in. This is that specification.

```
A physical RFID reader is registered as a hardware_devices row
   → it receives its OWN api_clients row, client_type = 'hardware_device'
   → scoped_abilities is narrow: e.g. ['attendance:write', 'boarding:roll_call:write']
     — a gate reader cannot post payroll, only movement events
   → ip_allowlist restricts it to the school's own network range where practical
   → heartbeats confirm it is online; a device silent beyond the configured
     window flags 'offline' and alerts the same way a gateway health check does

A scan event arrives:
   POST /api/v1/hardware/scan
   Authorization: Bearer {device's own scoped token}
   { "device_id": "...", "tag": "...", "scanned_at": "..." }

   → resolved to a learner/staff member by tag lookup
   → routed to the correct module's existing endpoint (attendance mark,
     roll call mark, gate movement) exactly as a manual mark would be,
     with device_source recorded
   → the owning module's own business rules apply unchanged — a hardware
     scan is just another way of calling an endpoint that already existed
```

**This closes the gap without opening a new one.** The device gets its own narrowly-scoped credential, distinct from any human's, and a compromised reader in a dormitory can push attendance scans and nothing else.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-INT-04-001` | Every third-party API key is scoped to an explicit ability allow-list at issuance. A client requesting an unlisted ability is refused, not silently granted the union of everything. |
| `BR-INT-04-002` | API keys are stored hashed. The plaintext key is shown once, at creation, and never again. |
| `BR-INT-04-003` | Rate limiting is per client, configurable, and returns a standard `429` with a `Retry-After` header, never a silent drop. |
| `BR-INT-04-004` | Outbound webhooks sign every payload with the subscription's own secret; the receiving system verifies it exactly as this platform's own inbound webhooks are verified elsewhere in the specification. |
| `BR-INT-04-005` | Webhook delivery retries with backoff; a subscription accumulating `consecutive_failures` beyond the configured threshold auto-disables and alerts the school administrator, rather than retrying forever into a dead endpoint. |
| `BR-INT-04-006` | SSO-provisioned staff accounts still pass through `CORE-05`'s identity and role machinery unchanged; provisioning creates the user record, it does not bypass permission assignment. |
| `BR-INT-04-007` ⭐ | A hardware device receives its own `api_clients` credential, distinct from any human user's, scoped to only the write abilities its function requires. |
| `BR-INT-04-008` | A hardware scan event is routed to the same domain Action a manual entry would call. There is no separate, lighter-touch validation path for hardware input. |
| `BR-INT-04-009` | A device silent beyond its configured heartbeat window is marked offline and alerted, and — per the pattern established in every module with a hardware fallback — its absence never blocks the manual fallback path in the owning module. |
| `BR-INT-04-010` | The OpenAPI specification is generated from the same route and validation definitions the application actually runs, never hand-maintained separately, so it cannot drift from reality. |
| `BR-INT-04-011` | API usage analytics are visible to the school for its own clients and to the vendor in aggregate; per-tenant usage data is never visible across tenants. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| API clients | `Integrations\Clients\Index` | `integration.manage` ⚠ — issue, scope, revoke |
| Webhook subscriptions | `Integrations\Webhooks\Index` | `integration.webhook.manage` |
| Webhook delivery log | `Integrations\Webhooks\Log` | `integration.view` |
| SSO configuration | `Integrations\Sso\Index` | `integration.sso.manage` ⚠⚠ |
| **Hardware devices** | `Integrations\Hardware\Index` | `integration.hardware.manage` — status, heartbeat, credential rotation |
| API usage | `Integrations\Usage\Dashboard` | `integration.view` |

```
GET  /api/v1/openapi.json                  PUBLIC — the machine-readable spec
POST /api/v1/hardware/scan                 device-scoped token
POST /api/v1/hardware/{ulid}/heartbeat
GET  /developers                            PUBLIC — documentation portal
```

| Setting | Type | Default |
|---|---|---|
| `integration.default_rate_limit_per_minute` | int | `60` |
| `integration.webhook_max_retries` | int | `8` |
| `integration.webhook_disable_after_failures` | int | `20` |
| `integration.hardware_heartbeat_window_minutes` | int | `15` |

Events: `ApiClientIssued` · `ApiClientRevoked` · `WebhookAutoDisabled` ⚠ · `HardwareDeviceOffline` ⚠ · `RateLimitExceeded`

### 6. Acceptance criteria

```gherkin
AC-INT-04-001
  Given an API client is scoped to ['attendance:write'] only
  When it attempts to call a payroll endpoint
  Then it is refused regardless of any other configuration

AC-INT-04-002
  Given a webhook subscription fails 20 consecutive deliveries
  Then it auto-disables
  And the school administrator is alerted

AC-INT-04-003
  Given a gate RFID reader posts a scan event
  Then it is routed through the same domain Action a manual gate entry would use
  And no lighter-touch validation path exists for it

AC-INT-04-004
  Given a hardware device goes silent beyond its heartbeat window
  Then it is marked offline
  And manual marking in the owning module remains fully available

AC-INT-04-005
  Given the OpenAPI spec is generated
  Then it reflects the actual running route definitions
  And cannot diverge from them by manual edit
```

---

# SAA-01 · Licensing, Subscription & Entitlement

### 1. Scope

**In scope.** Plan and module-bundle definition, the subscription lifecycle, usage metering, tenant billing and payment collection, graceful degradation on non-payment, upgrade/downgrade/proration, on-premise licence validation, renewal and churn tracking.

**Out of scope.** A school's own financial books (`FIN-01`) — this is the **vendor's own ledger**, entirely separate. `CORE-02`'s `school_modules` table, which this module writes to but does not own.

### 2. Data model

```sql
subscription_plans
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
code                     VARCHAR(30)  NOT NULL UNIQUE   -- 'FOUNDATION','BOARDING'
name                       VARCHAR(120) NOT NULL
tier                          VARCHAR(20)  NOT NULL   -- foundation|professional|
                                                       -- boarding|enterprise_group
price_per_learner_minor          BIGINT       NULL
flat_monthly_minor                  BIGINT       NULL
currency                               CHAR(3)      NOT NULL
included_modules                          JSON         NOT NULL   -- module codes
learner_band_min                             INT          NULL
learner_band_max                                INT          NULL
seat_limit_admin                                   INT          NULL
storage_quota_gb                                      INT          NULL
message_quota_monthly                                    INT          NULL
is_active                                                  TINYINT(1)   NOT NULL DEFAULT 1

subscriptions                         -- ⭐ the vendor's contract with a TENANT
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
tenant_id                BIGINT       FK INDEX
plan_id                    BIGINT       FK
covered_school_ids            JSON         NOT NULL   -- which schools this covers
billing_currency                 CHAR(3)      NOT NULL
learner_count_at_billing            INT          NULL
status                                 VARCHAR(20)  NOT NULL   -- trial|active|
                                                                -- past_due|grace|
                                                                -- suspended|cancelled
trial_ends_at                             TIMESTAMP    NULL
current_period_start                         DATE         NOT NULL
current_period_end                              DATE         NOT NULL
grace_period_ends_at                               TIMESTAMP    NULL
cancelled_at                                          TIMESTAMP    NULL
cancellation_reason                                      VARCHAR(60)  NULL
auto_renew                                                  TINYINT(1)   NOT NULL DEFAULT 1
created_at, updated_at
  INDEX (tenant_id, status)
  INDEX (status, current_period_end)

subscription_changes                  -- APPEND-ONLY, upgrade/downgrade history
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
subscription_id            BIGINT       FK INDEX
change_type                   VARCHAR(20)  NOT NULL   -- upgrade|downgrade|
                                                       -- module_added|
                                                       -- module_removed|
                                                       -- renewed|suspended|
                                                       -- reactivated|cancelled
from_plan_id                    BIGINT       NULL FK
to_plan_id                        BIGINT       NULL FK
proration_credit_minor               BIGINT       NULL
effective_from                          DATE         NOT NULL
reason                                     VARCHAR(255) NULL
performed_by                                  BIGINT       NULL FK
occurred_at                                      TIMESTAMP    NOT NULL

usage_meters                          -- per period, per tenant
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
tenant_id                BIGINT       FK INDEX
subscription_id             BIGINT       FK
period_month                   CHAR(7)      NOT NULL
metric                            VARCHAR(30)  NOT NULL   -- active_learners|
                                                          -- storage_gb|
                                                          -- messages_sent|
                                                          -- api_calls
usage_value                          DECIMAL(14,2) NOT NULL
limit_value                             DECIMAL(14,2) NULL
soft_warning_sent                          TINYINT(1)   NOT NULL DEFAULT 0
hard_limit_reached                            TINYINT(1)   NOT NULL DEFAULT 0
  UNIQUE (tenant_id, period_month, metric)

tenant_invoices                       -- ⭐ the VENDOR's AR, not any school's books
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
tenant_id                BIGINT       FK INDEX
subscription_id             BIGINT       FK
invoice_number                 VARCHAR(40)  NOT NULL
period_month                      CHAR(7)      NOT NULL
line_items                           JSON         NOT NULL
subtotal_minor                          BIGINT       NOT NULL
tax_minor                                  BIGINT       NOT NULL DEFAULT 0
total_minor                                   BIGINT       NOT NULL
currency                                         CHAR(3)      NOT NULL
due_date                                           DATE         NOT NULL
status                                                VARCHAR(20)  NOT NULL   -- draft|
                                                                              -- issued|
                                                                              -- paid|
                                                                              -- overdue|
                                                                              -- written_off
  UNIQUE (tenant_id, invoice_number)
  INDEX  (tenant_id, status, due_date)

tenant_payments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
tenant_id                BIGINT       FK INDEX
invoice_id                  BIGINT       FK
amount_minor                   BIGINT       NOT NULL
currency                          CHAR(3)      NOT NULL
payment_method                       VARCHAR(30)  NOT NULL   -- gateway|bank_transfer|
                                                              -- manual
gateway_reference                       VARCHAR(150) NULL   -- reuses FIN-05's driver layer
received_at                                TIMESTAMP    NOT NULL

licence_keys                          -- on-premise deployments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
tenant_id                BIGINT       FK INDEX
subscription_id             BIGINT       FK
key_value                      VARCHAR(255) NOT NULL UNIQUE
installation_uuid                 CHAR(36)     NULL   -- from CORE-01
last_validated_at                    TIMESTAMP    NULL
offline_grace_days                      INT          NOT NULL DEFAULT 14
status                                     VARCHAR(20)  NOT NULL   -- active|
                                                                    -- expired|revoked
```

### 3. ⭐ Graceful degradation — never a hard lockout

Book A's middleware stack promised this; here is exactly what it does.

```php
final class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next)
    {
        $subscription = SubscriptionContext::current();

        return match ($subscription->status) {
            'active', 'trial' => $next($request),

            'past_due', 'grace' => $this->allowReadOnly($request, $next),

            'suspended' => $this->blockExceptSafety($request, $next),

            'cancelled' => $this->readOnlyExportPathOnly($request, $next),
        };
    }

    private function allowReadOnly(Request $request, Closure $next)
    {
        if ($request->isReadOperation()) {
            return $next($request)->withHeader(
                'X-Subscription-Notice',
                'Payment is overdue. Contact your account manager to restore full access.'
            );
        }
        // ⭐ Safeguarding reporting and viewing is NEVER blocked by non-payment.
        if ($request->routeIsSafeguardingCritical()) {
            return $next($request);
        }
        throw new SubscriptionPastDueException($this->paymentPortalUrl());
    }
}
```

| # | Rule |
|---|---|
| A suspended school can still **read** everything — no data becomes invisible for non-payment. |
| Writes are blocked with a clear, specific notice and a direct link to resolve payment — never a generic error. |
| `BRD-08` safeguarding reporting and case access is **never** gated by subscription status, at any stage of non-payment, up to and including full cancellation. A child's safety cannot depend on an invoice. |
| A cancelled subscription retains read and `CORE-13` export access for the contractual data-retention window, so a school that leaves can still get its data out. |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-SAA-01-001` ⭐ | `tenant_invoices` and `tenant_payments` are the vendor's own books, structurally separate from every school's `FIN-01` ledger. A school cannot query or see another entity's commercial relationship with the vendor, and its own subscription invoices are visible only through a narrow "my subscription" view, not the full vendor AR system. |
| `BR-SAA-01-002` | Plan changes write to `subscription_changes` (append-only) and immediately synchronise `CORE-02.school_modules` for every covered school — entitlement always reflects the current plan within one transaction. |
| `BR-SAA-01-003` | Usage metering runs nightly per tenant per metric. A soft-warning threshold notifies the tenant's admin before any hard limit is enforced. |
| `BR-SAA-01-004` | A hard usage limit (e.g., learner count band exceeded) blocks the specific over-limit action (new enrolment) while leaving everything else fully functional — it is never a blanket lockout. |
| `BR-SAA-01-005` ⭐ | Non-payment degrades to read-only per §3, never to data loss or invisibility, and safeguarding access is exempted at every stage without exception. |
| `BR-SAA-01-006` | Upgrade takes effect immediately with prorated billing for the remainder of the period; downgrade takes effect at the next renewal, never mid-period, so a school does not lose access to data created under a higher tier partway through using it. |
| `BR-SAA-01-007` | On-premise licence validation checks online where possible and falls back to `offline_grace_days` of continued operation when it cannot reach the licence server — consistent with `CORE-01`'s installation-time grace period. |
| `BR-SAA-01-008` | Cancellation retains full read access and export capability for the configured retention window before any account closure step. |
| `BR-SAA-01-009` | Tenant payment collection reuses `FIN-05`'s driver abstraction and gateway layer entirely — this module does not implement a second payment integration. |

### 5. Screens · API

| Screen | Component | Access |
|---|---|---|
| My subscription | `Saas\Subscription\MySubscription` | tenant owner — plan, usage, invoices, upgrade |
| Plan catalogue | `Saas\Subscription\Plans` | vendor console |
| Subscription lifecycle | `Saas\Subscription\Manage` | vendor console — status, grace, suspension |
| Tenant billing | `Saas\Billing\Invoices` | vendor console |
| Licence keys | `Saas\Licensing\Keys` | vendor console |

```
GET  /api/v1/subscription/mine           tenant owner
POST /api/v1/subscription/upgrade
GET  /api/v1/subscription/usage
```

### 6. Acceptance criteria

```gherkin
AC-SAA-01-001
  Given a tenant's subscription is past_due
  Then every read operation continues to succeed with a notice header
  And write operations are refused with a specific payment-resolution link

AC-SAA-01-002
  Given a tenant's subscription is suspended
  When a BRD-08 safeguarding concern is reported or a case is accessed
  Then it succeeds regardless of subscription status

AC-SAA-01-003
  Given a plan upgrade is applied mid-period
  Then it takes effect immediately with a prorated charge
  And a downgrade requested the same day takes effect only at next renewal

AC-SAA-01-004
  Given a tenant exceeds its learner-count band
  Then only new enrolment is blocked
  And every other function remains available

AC-SAA-01-005
  Given a subscription is cancelled
  Then read and export access continue through the contractual retention window

AC-SAA-01-006
  Given School A views its own subscription
  Then no other tenant's invoices, usage, or plan details are reachable
```

---

# SAA-02 · Vendor Control Centre

### 1. Scope

**In scope.** The cross-tenant vendor console: tenant/school health registry, feature-flag rollout control, release and migration staging, broadcast announcements, incident status page.

**Out of scope.** Impersonation mechanics (`CORE-05`), feature-flag mechanics (`CORE-04`) — this module operates them cross-tenant, it does not rebuild them.

### 2. Data model

```sql
                                       -- ⭐ separate `vendor` guard; not reachable
                                       -- from any school-facing role or permission

tenant_health_snapshots                -- nightly, cross-tenant
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
tenant_id                BIGINT       FK INDEX
snapshot_date               DATE         NOT NULL
active_schools                 INT          NOT NULL
active_learners                   INT          NOT NULL
subscription_status                  VARCHAR(20)  NOT NULL
last_login_days_ago                     INT          NULL
module_adoption_percent                    DECIMAL(5,2) NULL   -- from SAA-03
open_support_tickets                          INT          NOT NULL DEFAULT 0
integrity_check_failures                         INT          NOT NULL DEFAULT 0
health_score                                        DECIMAL(5,2) NULL
  UNIQUE (tenant_id, snapshot_date)

feature_rollouts                      -- operates CORE-04's feature_flags cross-tenant
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
feature_flag_key           VARCHAR(80)  FK
rollout_stage                 VARCHAR(20)  NOT NULL   -- pilot|cohort|
                                                       -- percentage|general
pilot_tenant_ids                 JSON         NULL
percentage                          TINYINT      NULL
started_at                             TIMESTAMP    NOT NULL
started_by                                BIGINT       FK
notes                                        VARCHAR(500) NULL

release_deployments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
version                    VARCHAR(20)  NOT NULL
deployment_stage              VARCHAR(20)  NOT NULL   -- staging|canary|
                                                       -- general
canary_tenant_ids                JSON         NULL
migration_status                    VARCHAR(20)  NOT NULL
started_at                              TIMESTAMP    NOT NULL
completed_at                               TIMESTAMP    NULL
rollback_available                            TINYINT(1)   NOT NULL DEFAULT 1

broadcast_announcements
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
title                    VARCHAR(200) NOT NULL
body                       TEXT         NOT NULL
severity                     VARCHAR(20)  NOT NULL   -- info|maintenance|incident
target_tenant_ids               JSON         NULL   -- null = all
starts_at                          TIMESTAMP    NOT NULL
ends_at                               TIMESTAMP    NULL
posted_by                               BIGINT       FK

incident_status_entries
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
title                     VARCHAR(200) NOT NULL
affected_components          JSON         NOT NULL   -- ['payments','fiscalisation']
severity                        VARCHAR(20)  NOT NULL
status                             VARCHAR(20)  NOT NULL   -- investigating|
                                                            -- identified|
                                                            -- monitoring|resolved
updates                                JSON         NOT NULL   -- timeline entries
is_public                                 TINYINT(1)   NOT NULL DEFAULT 1
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-SAA-02-001` ⭐ | The vendor console runs under a **third guard**, entirely separate from `web` and `sanctum`, IP-allowlisted, with its own MFA requirement. No path from any school-facing role reaches it. |
| `BR-SAA-02-002` | Impersonation is initiated from this console but executes entirely within `CORE-05`'s existing guardrails — time-boxed, consent-gated, financial-action-locked, fully audited. This module adds nothing to that mechanism beyond the entry point. |
| `BR-SAA-02-003` | Health scores compute from objective signals — login recency, module adoption, support ticket volume, integrity check failures — and are shown with their components, not as an opaque number, for the same reason `INT-03`'s risk scores are decomposed. |
| `BR-SAA-02-004` | Feature rollout stages progress deliberately: pilot on named tenants, then a cohort, then a percentage, then general — and each stage requires explicit advancement, never an automatic escalation. |
| `BR-SAA-02-005` | A canary deployment runs against named tenants before general release, with rollback available until the deployment is explicitly confirmed stable. |
| `BR-SAA-02-006` | Broadcast announcements and incident status entries are visible to affected tenants' administrators; targeting is explicit, never inferred. |
| `BR-SAA-02-007` | Every vendor-console action against a specific tenant is logged with the same rigour as `CORE-08`'s financial audit stream — this is, after all, the console with the widest blast radius in the entire platform. |

### 4. Screens · API

| Screen | Component |
|---|---|
| Tenant registry | `Vendor\Tenants\Index` — health, status, at-a-glance |
| Tenant detail | `Vendor\Tenants\Show` — full health snapshot, support history |
| Feature rollout | `Vendor\Rollouts\Index` |
| Release management | `Vendor\Releases\Index` — canary tenants, migration status |
| Broadcasts | `Vendor\Broadcasts\Compose` |
| Incident status page | `Vendor\Incidents\Manage` — public-facing status page content |

```
GET /api/v1/vendor/tenants/{id}/health      vendor guard only
GET /status                                  PUBLIC incident status page
```

### 5. Acceptance criteria

```gherkin
AC-SAA-02-001
  Given a user holding the highest school-level role at School A
  When they attempt to reach any vendor console route
  Then they are refused — no permission grants this path

AC-SAA-02-002
  Given a feature flag is in pilot stage on three named tenants
  Then no other tenant sees the feature
  And advancing to cohort stage requires an explicit action

AC-SAA-02-003
  Given a canary release is deployed to two tenants
  Then rollback remains available until the deployment is explicitly confirmed

AC-SAA-02-004
  Given a vendor operator views a tenant's health score
  Then its component signals are shown, not a bare number
```

---

# SAA-03 · Onboarding, Support & Customer Success

### 1. Scope

**In scope.** Per-school onboarding checklists, configuration-template cloning, vendor-facing support ticketing, knowledge base, product tours, training completion tracking, adoption analytics, health scoring inputs, churn-risk alerting, release note distribution.

**Out of scope.** `COM-08`'s complaint system (school-facing, different audience — see §0.2). `CORE-04`'s configuration profile mechanics, which this module's templates are built from.

### 2. Data model

```sql
onboarding_checklists
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
tenant_id                BIGINT       FK INDEX
school_id                   BIGINT       FK
started_at                     TIMESTAMP    NOT NULL
target_go_live_date               DATE         NULL
steps                                JSON         NOT NULL   -- [{key, label,
                                                              --  completed_at,
                                                              --  owner}]
status                                   VARCHAR(20)  NOT NULL   -- in_progress|
                                                                  -- go_live|
                                                                  -- stalled
assigned_success_manager                    BIGINT       NULL FK
  UNIQUE (school_id)

onboarding_template_library            -- built on CORE-04's configuration_profiles
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
configuration_profile_id   BIGINT       FK   -- CORE-04
template_name                 VARCHAR(150) NOT NULL
suited_for                       VARCHAR(60)  NULL   -- 'boarding_secondary',
                                                      -- 'day_primary'
used_count                          INT          NOT NULL DEFAULT 0

support_tickets                       -- ⭐ vendor-facing, distinct from COM-08
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
tenant_id                BIGINT       FK INDEX
school_id                   BIGINT       NULL FK
raised_by_user_id              BIGINT       FK   -- a SCHOOL user, raising to VENDOR
category                          VARCHAR(40)  NOT NULL   -- bug|how_to|
                                                          -- billing|feature_request
priority                             VARCHAR(20)  NOT NULL   -- low|normal|
                                                              -- high|urgent
sla_due_at                              TIMESTAMP    NOT NULL
assigned_vendor_staff_id                   BIGINT       NULL
status                                        VARCHAR(20)  NOT NULL   -- open|
                                                                      -- in_progress|
                                                                      -- waiting_on_customer|
                                                                      -- resolved|closed
  INDEX (tenant_id, status)
  INDEX (status, sla_due_at)

knowledge_base_articles
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
slug                    VARCHAR(150) UNIQUE
title                     VARCHAR(200) NOT NULL
module_code                  VARCHAR(20)  NULL
content                          LONGTEXT     NOT NULL
view_count                          INT          NOT NULL DEFAULT 0
helpful_votes                          INT          NOT NULL DEFAULT 0
last_reviewed_on                          DATE         NULL

product_tours
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
key                       VARCHAR(60)  UNIQUE
persona                     VARCHAR(20)  NOT NULL   -- parent|teacher|bursar|admin
steps                          JSON         NOT NULL

tour_completions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
user_id                    BIGINT       FK
tour_key                      VARCHAR(60)  FK
completed_at                     TIMESTAMP    NULL
skipped_at                          TIMESTAMP    NULL
  UNIQUE (user_id, tour_key)

training_completions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
user_id                    BIGINT       FK
material_key                  VARCHAR(80)  NOT NULL
completed_at                     TIMESTAMP    NULL

module_adoption_scores                -- ⭐ derived, not self-reported
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
module_code                VARCHAR(20)  NOT NULL
period_month                  CHAR(7)      NOT NULL
activity_signal                  VARCHAR(60)  NOT NULL   -- 'journal_posted',
                                                          -- 'roll_call_taken'
activity_count                      INT          NOT NULL DEFAULT 0
is_actively_used                       TINYINT(1)   NOT NULL DEFAULT 0
  UNIQUE (school_id, module_code, period_month)

churn_risk_flags
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
tenant_id                BIGINT       FK INDEX
flagged_at                  TIMESTAMP    NOT NULL
contributing_factors           JSON         NOT NULL   -- ⭐ same explainability
                                                        -- discipline as INT-03
status                             VARCHAR(20)  NOT NULL   -- open|
                                                            -- intervention_logged|
                                                            -- resolved|churned
assigned_to                            BIGINT       NULL FK
```

### 3. ⭐ Adoption is measured, not asked

A renewal conversation grounded in *"do you use the Finance module?"* is worth less than one grounded in real activity. Adoption is derived from the same signal every other module in this specification already produces — a posted journal, a taken roll call, a generated report card — never from a self-report survey.

```
module_adoption_scores for FIN-01, School X, 2026-08:
   activity_signal = 'journal_posted'
   activity_count  = 412      → is_actively_used = true

module_adoption_scores for BRD-06, School X, 2026-08:
   activity_signal = 'sick_bay_admission_recorded'
   activity_count  = 0        → is_actively_used = false

   → School X is entitled to the Health module (it's on their plan)
     but genuinely isn't using it. That is a real, actionable
     customer-success conversation — not a guess.
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-SAA-03-001` | Onboarding checklist steps are per-school, tracked against a target go-live date, and a checklist stalled beyond the configured threshold alerts the assigned success manager. |
| `BR-SAA-03-002` | Onboarding templates are `CORE-04` configuration profiles with a curated name and suitability tag; this module never stores a second copy of school configuration. |
| `BR-SAA-03-003` ⭐ | Support tickets flow **from a school's users to the vendor**. `COM-08` complaints flow from a school's parents/staff/learners **to the school itself**. A ticket accidentally routed to the wrong system is a defect, not a style choice — the two must never share a queue. |
| `BR-SAA-03-004` | Ticket SLA is set by priority at intake and tracked exactly as `COM-08`'s complaint SLA is, reusing the pattern, not a second implementation. |
| `BR-SAA-03-005` | Product tour completion and skip are both recorded; a skipped tour remains accessible from help, mirroring `COM-03`'s onboarding-step pattern. |
| `BR-SAA-03-006` ⭐ | Module adoption is computed from real activity signals registered by each owning module, never from a self-reported checklist or survey. |
| `BR-SAA-03-007` | A module the school is entitled to but shows zero activity for a sustained period surfaces as a customer-success opportunity, not a billing dispute — the response is engagement, not an upsell email. |
| `BR-SAA-03-008` | Churn risk factors are decomposed exactly as `INT-03`'s learner risk factors are — plain language, weighted, sourced — for the same reason: a renewal conversation grounded in specifics beats one grounded in a mystery score. |
| `BR-SAA-03-009` | Release notes distribute to tenant administrators through `CORE-09`, scoped to the modules that tenant actually has enabled, so a school on the Foundation tier is not notified about a Boarding-module feature it cannot use. |

### 5. Screens · API

| Screen | Component | Access |
|---|---|---|
| Onboarding tracker | `Success\Onboarding\Index` | vendor console — per school progress |
| Template library | `Success\Onboarding\Templates` | vendor console |
| **Support queue** | `Success\Support\Queue` | vendor console — SLA-sorted |
| Raise a ticket | `Success\Support\Raise` | school admin — school-facing entry point into the vendor queue |
| Knowledge base | `Success\KnowledgeBase\Index` | public / in-app |
| **Adoption dashboard** | `Success\Adoption\Index` | vendor console — per tenant, per module, real usage |
| Churn risk | `Success\ChurnRisk\Queue` | vendor console |

```
POST /api/v1/support/tickets              school admin raises to vendor
GET  /api/v1/support/tickets/mine
GET  /api/v1/help/articles                ?search=
POST /api/v1/tours/{key}/complete
```

### 6. Acceptance criteria

```gherkin
AC-SAA-03-001
  Given a school user raises a support ticket
  Then it appears in the vendor's SAA-03 queue
  And it never appears in that school's own COM-08 complaint queue

AC-SAA-03-002
  Given a school posts 400 journals in FIN-01 this month and zero sick bay
       admissions in BRD-06
  Then FIN-01 shows is_actively_used = true
  And BRD-06 shows is_actively_used = false
  Based entirely on recorded activity, not a survey

AC-SAA-03-003
  Given an onboarding checklist has had no progress for the configured
       stall threshold
  Then the assigned success manager is alerted

AC-SAA-03-004
  Given a tenant is on the Foundation tier with Boarding disabled
  When release notes for a Boarding-only feature are distributed
  Then that tenant does not receive them

AC-SAA-03-005
  Given a churn risk flag is raised
  Then its contributing factors are shown in plain language with sources,
       not as a bare risk score
```

---

## Part 3 — Book J Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **J1** | `INT-01` field registry, permission-scoped query builder | `AC-INT-01-001/002` green — no field leak under any test |
| **J2** | `INT-01` saved/shared reports, scheduling, warehouse | Cross-school consolidation gated correctly |
| **J3** | `INT-02` executive widgets on `COM-03`'s registry, KPI targets | Zero duplicate widget systems |
| **J4** | `INT-02` digest, comprehensive board pack | `FIN-12` section included verbatim |
| **J5** | `INT-03` indicator registry, composite scoring, explainability ⭐ | **Every score decomposes; none reaches a learner/guardian endpoint** |
| **J6** | `INT-03` fee risk, forecasting, staff wellbeing | Surfaces `FIN-09`/`OPS-01` anomalies read-only, no duplication |
| **J7** | `INT-04` API clients, scoping, rate limiting, OpenAPI | Generated spec matches live routes |
| **J8** | `INT-04` webhooks, SSO provisioning | Auto-disable on sustained failure proven |
| **J9** | `INT-04` hardware device registry ⭐ | **Every `device_source` value across the whole spec has a real credential path** |
| **J10** | `SAA-01` plans, subscriptions, entitlement sync | Writes to `CORE-02.school_modules` atomically |
| **J11** | `SAA-01` graceful degradation ⭐ | **`AC-SAA-01-001/002` green — safeguarding never gated by payment** |
| **J12** | `SAA-01` billing, usage metering, licence keys | Vendor AR fully separate from any school's `FIN-01` |
| **J13** | `SAA-02` vendor guard, tenant registry, health scoring | Third guard proven unreachable from any school role |
| **J14** | `SAA-02` feature rollout, release staging, incidents | Canary rollback proven |
| **J15** | `SAA-03` onboarding, templates, support queue | Queue separation from `COM-08` proven |
| **J16** | `SAA-03` adoption analytics, churn risk, release notes | Adoption derived from activity, not survey |

---

## Part 4 — Book J Acceptance Gate

### Boundary discipline

- [ ] `INT-01`'s query builder issues zero raw SQL; every query runs through an existing Eloquent model with its existing scopes
- [ ] `INT-02` registers zero new widgets outside `COM-03`'s registry
- [ ] `INT-03` contains zero duplicate anomaly-detection logic for stock, fuel, or any commodity already covered
- [ ] `INT-04` never overlaps `CORE-13`'s contract-exit export or `FIN-12`'s accounting export in purpose
- [ ] `SAA-01`'s tenant ledger is structurally separate from every school's `FIN-01`
- [ ] `SAA-02` impersonation adds no mechanism beyond `CORE-05`'s existing guardrails
- [ ] `SAA-03` support tickets and `COM-08` complaints never share a queue, a table, or a screen

### Explainability and ethics

- [ ] Every `INT-03` risk score decomposes into sourced, plain-language factors
- [ ] No risk score, factor, or churn flag is reachable by a learner or guardian token, under any permission
- [ ] No automated adverse action fires from any `INT-03` score without a human decision
- [ ] `SAA-03` adoption scores derive from real recorded activity, never a self-report

### Vendor/school separation

- [ ] The vendor guard is unreachable from any school-facing role, permission, or route — verified by an automated test attempting every school role against every vendor route
- [ ] A school can see only its own subscription, invoices, and usage
- [ ] Safeguarding access (`BRD-08`) is verified unaffected by every subscription status including `cancelled`

### Hardware and integrations

- [ ] Every hardware device carries its own scoped credential, distinct from any human token
- [ ] A hardware scan routes through the same domain Action a manual entry would use
- [ ] Manual fallback remains available in every owning module when its hardware is offline
- [ ] The OpenAPI spec is generated, not hand-maintained, and a route change is reflected without manual edit

### Quality

- [ ] Coverage ≥ 90% for `INT-01`'s permission-scoping and `SAA-01`'s degradation logic — the two modules in this book with the highest blast radius if wrong
- [ ] Tenancy isolation suite passes for every model in this book, plus a dedicated cross-tenant isolation suite for `SAA-*`
- [ ] Every business rule has a named test referencing its rule ID

---

## Appendix A — Interfaces Closed

| Interface | Owner | Consumer | Status |
|---|---|---|---|
| Field registry for ad hoc reporting | `INT-01` | every module | ✅ opened and closed here |
| Hardware device credentialing | `INT-04` | `BRD-02`, `ACA-04`, `FIN-04`, `OPS-01`, `BRD-03` — every `device_source` column in the specification | ✅ closed |
| `EnsureSubscriptionActive` middleware | `SAA-01` | Book A's middleware stack, referenced but not implemented until now | ✅ closed |
| `school_modules` entitlement sync | `SAA-01` writes → `CORE-02` owns | — | ✅ |

---

## Appendix B — ⭐ A Correction Before Calling This Complete

Volume 1 promised 78 modules across 10 domains. Before writing this appendix, every module header actually built across Books A through J was audited against that catalogue. **Six modules were never given a Volume 2 specification:**

| Module | Domain | Why it was missed |
|---|---|---|
| `PPL-06` | Alumni & Institutional Development | Volume 1's roadmap placed it in Phase 12, alongside `INT`/`SAA` — it should have been queued for this book and was not |
| `ACA-08` | Online Assignments & E-Learning Exams (LMS) | Deferred to Phase 9 in Volume 1's roadmap; Book E covered `ACA-03/06/07` from that phase but stopped short of `08` |
| `ACA-09` | Computer-Based Testing (CBT) | Same phase, same gap |
| `ACA-10` | Library & Textbook Management | Same phase, same gap |
| `ACA-11` | Teaching Quality, Lesson Planning & Supervision | Same phase, same gap |
| `FIN-07` | Scholarships, Bursaries & Discounts | Referenced constantly — sibling discounts, staff-child fee offsets, sponsor liability — but the module that actually defines the discount catalogue, award workflow, and budget envelopes was never itself specified |

The `FIN-07` gap is the more serious of the two: `FIN-02`'s billing pipeline, `FIN-03`'s credit notes, and `PPL-03`'s sibling-discount rules all assume a discount engine exists and post against it, but that engine's own table structure, approval workflow, and budget-envelope mechanics were never written down. A developer implementing `FIN-02` today would hit a wall at the exact point Book B's pipeline diagram says *"apply matching discounts"* with no specification of what a discount record actually is.

**This is being corrected immediately, in the same delivery, as Book K — not deferred.** A specification that silently ships six gaps under the label "complete" is worse than one that is honest about needing one more book.

---

*End of Volume 2, Book J. Continued in Book K.*

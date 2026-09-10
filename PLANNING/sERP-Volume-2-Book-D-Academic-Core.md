# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book D — Domain C: Academic Core (`ACA-01`, `ACA-02`, `ACA-04`, `ACA-05`)

| Field | Value |
|---|---|
| Document | Volume 2, Book D of 10 |
| Covers | Curriculum & Pathways · Class & Subject Enrolment · Attendance · Assessment, Grading & Report Cards |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A, B and C complete.** `ACA-02` supplies the subject count `FIN-02` was specified against. |
| Next book | Book E — Academic Depth (`ACA-03` Timetable, `ACA-06` SBP, `ACA-07` Examinations) |

---

## Part 0 — What This Book Closes

### 0.1 The last open dependency

`FIN-02` was written against an interface that does not yet exist. Book B, `BR-FIN-02-004`, states:

> `PER_SUBJECT` quantity is derived from `ACA-02` subject enrolments effective on the billing date. **There is no separate "billable subject count" field.**

This book builds that. When `ACA-02` ships, the fee cycle is complete end to end for both enrolment types, and the P1–P5 minimum sellable product from Volume 1 is done.

### 0.2 Build order

```
ACA-01  Curriculum, Learning Areas & Pathways   ← defines what can be taught
   ↓
ACA-02  Class, Stream & Subject Enrolment       ← ⭐ closes the billing dependency
   ↓
ACA-04  Attendance                              ← needs classes; feeds report cards
   ↓
ACA-05  Assessment, Grading & Report Cards      ← needs all three
```

### 0.3 🇿🇼 The curriculum position, stated carefully

Zimbabwe is mid-transition, and this matters for how the module is built.

**What is established.** The Ministry of Primary and Secondary Education introduced the **Heritage-Based Curriculum Framework 2024–2030**. From May 2024, **School-Based Projects (SBPs) replaced Continuous Assessment Learning Activities (CALA)** at all levels from ECD A upwards, other than examination classes in the transition year, at **one project per learning area per year**. Implementation guidance was issued in **Circular No. 9 of 2024**. Primary education is streamlined to **six core learning areas**. Secondary learners follow a **two-route pathway** — academic, or vocational/skills.

**What further research resolves.** An initial pass found A-Level subject counts reported two ways — minimum three and maximum four in some accounts, a flat maximum of three in others. A second, more targeted pass resolves this with reasonable confidence: the ministry-cited figure, reported at the framework's April 2024 unveiling and repeated in subsequent coverage, is **minimum three subjects, determined by career pathway, with a ceiling of four**. The single conflicting report reads, on balance, as an imprecise paraphrase rather than a distinct later policy change — no source describes a flat maximum of three independently of that one article, while the "ceiling of four" framing recurs across separate pieces citing the ministry directly. O-Level is undisputed across every source found: **five compulsory learning areas, at least three electives, eight in total**. Primary's **six core learning areas** is likewise consistent everywhere it appears and was never genuinely contested.

**The engineering consequence is unchanged by resolving this.** Every one of these numbers is **configuration, not code**, regardless of how confident today's default is. The system ships with a seeded Heritage-Based profile carrying the best-supported values below, and a school administrator can change any of them without a deployment. The framework itself is versioned, so a 2024 record renders under 2024 rules forever even after the numbers change. That matters independently of confidence level: the Heritage-Based rollout phases in through 2026, and a framework still being implemented is exactly the kind of policy that gets locally clarified or amended. A hard-coded number would need a deployment to follow it; a setting does not.

A system that hard-codes "maximum 8 subjects" will be wrong within a year. A system that treats it as a validated setting will not.

---

# ACA-01 · Curriculum, Learning Areas & Pathways 🇿🇼

### 1. Scope

**In scope.** Curriculum framework versioning, learning area and subject catalogue, subject groups, level-to-subject offerings, pathway definitions, subject selection rules and constraints, prerequisite chains, syllabus repository.

**Out of scope.** Who takes which subject (`ACA-02`). Timetabling (`ACA-03`). SBP execution (`ACA-06`).

### 2. Data model

```sql
curriculum_frameworks                 -- versioned; historical records keep their version
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL   -- 'HBC_2024','CBC_2015','CAMBRIDGE'
name                    VARCHAR(150) NOT NULL   -- 'Heritage-Based Curriculum 2024-2030'
authority               VARCHAR(80)  NOT NULL   -- 'MoPSE','Cambridge International'
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
status                  VARCHAR(20)  NOT NULL   -- draft|active|superseded|archived
continuous_assessment_model VARCHAR(20) NOT NULL -- sbp | cala | none | coursework
reference_circular      VARCHAR(120) NULL       -- 'Circular No. 9 of 2024'
notes                   TEXT         NULL
created_by, created_at, updated_at
  UNIQUE (school_id, code)
  INDEX  (school_id, status)

pathways                              -- 🇿🇼 two-route model
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
framework_id            BIGINT       FK
code                    VARCHAR(20)  NOT NULL   -- 'ACADEMIC','VOCATIONAL'
name                    VARCHAR(120) NOT NULL
description             TEXT         NULL
applies_from_level_ordinal SMALLINT  NOT NULL   -- e.g. Form 1
is_default              TINYINT(1)   NOT NULL DEFAULT 0
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, framework_id, code)

subject_groups                        -- ⭐ drives per-subject fee rates (FIN-02)
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL   -- 'SCIENCES_PRACTICAL','COMMERCIALS'
name                    VARCHAR(120) NOT NULL
description             VARCHAR(255) NULL
requires_laboratory     TINYINT(1)   NOT NULL DEFAULT 0
requires_workshop       TINYINT(1)   NOT NULL DEFAULT 0
sort_order              SMALLINT
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

subjects                              -- learning areas
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
framework_id            BIGINT       FK INDEX
subject_group_id        BIGINT       NULL FK
code                    VARCHAR(30)  NOT NULL   -- internal code
name                    VARCHAR(150) NOT NULL   -- 'Combined Science'
short_name              VARCHAR(40)  NOT NULL   -- report card column header
zimsec_subject_code     VARCHAR(20)  NULL       -- 🇿🇼 e.g. '4003'
cambridge_subject_code  VARCHAR(20)  NULL
subject_type            VARCHAR(20)  NOT NULL   -- core|elective|practical|vocational|
                                                -- co_curricular
is_examinable           TINYINT(1)   NOT NULL DEFAULT 1
has_practical_component TINYINT(1)   NOT NULL DEFAULT 0
has_coursework          TINYINT(1)   NOT NULL DEFAULT 0
coursework_weight_percent DECIMAL(5,2) NULL     -- SBP / coursework contribution
default_periods_per_week SMALLINT    NULL
requires_sbp            TINYINT(1)   NOT NULL DEFAULT 1   -- 🇿🇼 one project per year
department_id           BIGINT       NULL FK    -- PPL-04
grading_scale_id        BIGINT       NULL FK    -- overrides the level default
sort_order              SMALLINT
is_active               TINYINT(1)   NOT NULL DEFAULT 1
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, framework_id, code)
  INDEX  (school_id, subject_group_id, is_active)

level_subject_offerings               -- which subjects exist at which level
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK INDEX
grade_level_id          BIGINT       FK INDEX
subject_id              BIGINT       FK INDEX
pathway_id              BIGINT       NULL FK    -- null = both pathways
is_compulsory           TINYINT(1)   NOT NULL DEFAULT 0
is_available            TINYINT(1)   NOT NULL DEFAULT 1
periods_per_week        SMALLINT     NULL
max_learners            SMALLINT     NULL       -- lab or workshop capacity
option_block            VARCHAR(20)  NULL       -- 'A','B','C' — A-Level blocks
sort_order              SMALLINT
  UNIQUE (school_id, academic_year_id, grade_level_id, subject_id, pathway_id)
  INDEX  (school_id, academic_year_id, grade_level_id, is_compulsory)

subject_selection_rules               -- ⭐ the constraint engine
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
framework_id            BIGINT       FK
grade_level_id          BIGINT       NULL FK    -- null = applies to all levels
pathway_id              BIGINT       NULL FK
rule_type               VARCHAR(30)  NOT NULL   -- min_total|max_total|min_compulsory|
                                                -- min_from_group|max_from_group|
                                                -- required_subject|mutually_exclusive|
                                                -- prerequisite|one_per_option_block
subject_group_id        BIGINT       NULL FK
subject_ids             JSON         NULL       -- for required / exclusive rules
min_count               SMALLINT     NULL
max_count               SMALLINT     NULL
severity                VARCHAR(20)  NOT NULL   -- block | warn
message                 VARCHAR(255) NOT NULL   -- shown to the user verbatim
source_reference        VARCHAR(150) NULL       -- 'MoPSE HBC Framework 2024-2030'
requires_confirmation   TINYINT(1)   NOT NULL DEFAULT 0  -- ⚠ flag unconfirmed values
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, framework_id, grade_level_id, is_active)

subject_prerequisites
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
subject_id              BIGINT       FK          -- the subject being taken
prerequisite_subject_id BIGINT       FK          -- must have studied this
minimum_grade           VARCHAR(10)  NULL        -- 'C' at O-Level
examination             VARCHAR(60)  NULL        -- 'ZIMSEC O-Level'
severity                VARCHAR(20)  NOT NULL    -- block | warn
  UNIQUE (school_id, subject_id, prerequisite_subject_id)

syllabi
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
subject_id              BIGINT       FK INDEX
grade_level_id          BIGINT       NULL FK
framework_id            BIGINT       FK
title                   VARCHAR(200) NOT NULL
version                 VARCHAR(30)  NULL
effective_from          DATE         NULL
effective_to            DATE         NULL
file_id                 BIGINT       NULL FK → files.id
topics                  JSON         NULL        -- structured topic tree for coverage
is_active               TINYINT(1)   NOT NULL DEFAULT 1
```

### 3. ⭐ The subject selection rule engine

Every constraint the Ministry sets, and every constraint a school adds, is a row in `subject_selection_rules`. Nothing is hard-coded.

**Seeded Heritage-Based rules**, with confidence reflecting the research in §0.3:

| Level | Rule type | Values | Severity | Confirm |
|---|---|---|---|---|
| Primary (Grade 1–7) | `max_total` | 6 | `warn` | — |
| Form 1–4 | `min_compulsory` | 5 | `block` | — |
| Form 1–4 | `max_total` | 8 | `block` | — |
| Form 5–6 | `min_total` | 3 | `block` | ⚠ |
| Form 5–6 | `max_total` | 4 | `block` | ⚠ |
| Form 5–6 | `one_per_option_block` | — | `block` | — |
| Sciences (practical) | `max_from_group` | lab capacity | `warn` | — |

Both A-Level rules changed from the original draft. `max_total` moves from `warn` to `block` — every source describing it calls it a ceiling, which is the same treatment O-Level's maximum gets. Both retain `requires_confirmation = 1`, not because the values are contested anymore, but because a rollout still phasing in through 2026 is exactly the kind of policy that gets locally clarified. The flag's meaning has shifted from *"we found conflicting numbers, pick carefully"* to *"verify this is still current before first use each year."*

The `requires_confirmation` flag drives a persistent banner on the curriculum setup screen:

> **⚠ Confirm against your current MoPSE circular.** A-Level subject-count limits (minimum 3, maximum 4, career-pathway determined) are well-supported but this framework is still phasing in through 2026. Review before the first subject selection of the year.

A confirmation banner on a well-sourced default costs an administrator a moment. A hard-coded number that quietly falls out of date costs a wrongly rejected subject combination on results day.

**Evaluation:**

```php
public function validate(Student $s, Collection $subjectIds, ?Term $term = null): SelectionResult
{
    $rules = $this->rulesFor($s->grade_level_id, $s->pathway, $s->framework());
    $violations = collect();

    foreach ($rules as $rule) {
        $ok = match ($rule->rule_type) {
            'min_total'            => $subjectIds->count() >= $rule->min_count,
            'max_total'            => $subjectIds->count() <= $rule->max_count,
            'min_compulsory'       => $this->compulsoryCount($subjectIds) >= $rule->min_count,
            'min_from_group'       => $this->groupCount($subjectIds, $rule) >= $rule->min_count,
            'max_from_group'       => $this->groupCount($subjectIds, $rule) <= $rule->max_count,
            'required_subject'     => $this->containsAll($subjectIds, $rule->subject_ids),
            'mutually_exclusive'   => $this->atMostOneOf($subjectIds, $rule->subject_ids),
            'one_per_option_block' => $this->noBlockClash($subjectIds),
            'prerequisite'         => $this->prerequisitesMet($s, $subjectIds),
        };

        if (!$ok) {
            $violations->push(new Violation($rule->severity, $rule->message, $rule));
        }
    }

    return new SelectionResult(
        isValid:  $violations->where('severity', 'block')->isEmpty(),
        warnings: $violations->where('severity', 'warn'),
        blocks:   $violations->where('severity', 'block'),
    );
}
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-01-001` | A school may run multiple frameworks concurrently — Heritage-Based for local streams, Cambridge for an international stream — with subjects belonging to exactly one. |
| `BR-ACA-01-002` | Frameworks are versioned by `effective_from`/`effective_to`. A record created under a framework retains that framework reference permanently. |
| `BR-ACA-01-003` | Superseding a framework does not alter historical records. A 2023 report card renders under the 2015 competence-based rules forever. |
| `BR-ACA-01-004` | `subject_group_id` is the join point for per-subject fee rates in `FIN-02`. A subject with no group falls back to the structure item's base `unit_rate_minor`. |
| `BR-ACA-01-005` | A subject cannot be deactivated while any active enrolment or unpublished mark references it. |
| `BR-ACA-01-006` | Level offerings are per academic year. Subject availability changes year to year without disturbing history. |
| `BR-ACA-01-007` | Every subject-count constraint is a `subject_selection_rules` row. **No count is hard-coded anywhere in the codebase.** |
| `BR-ACA-01-008` | Rules with `severity = block` prevent saving. Rules with `severity = warn` require explicit acknowledgement, which is recorded with the acknowledging user. |
| `BR-ACA-01-009` | Rules with `requires_confirmation = 1` display a persistent banner until an administrator marks them reviewed for the current academic year. |
| `BR-ACA-01-010` | Compulsory subjects auto-enrol on class allocation. A learner is never expected to opt in to a compulsory learning area. |
| `BR-ACA-01-011` | Option blocks enforce one subject per block per learner. Violations block, because a timetable cannot be built around them. |
| `BR-ACA-01-012` | Prerequisites validate against `student_prior_results` and internal results. A missing prerequisite warns by default and blocks only when configured to. |
| `BR-ACA-01-013` | 🇿🇼 Pathway assignment applies from the configured level ordinal upward. Below that level, `pathway` is null and no pathway rules apply. |
| `BR-ACA-01-014` | Changing a learner's pathway re-validates their entire subject selection and lists every resulting violation before the change is committed. |
| `BR-ACA-01-015` | Subjects with `requires_sbp = 1` generate one SBP requirement per learner per academic year (executed in `ACA-06`). |
| `BR-ACA-01-016` | ZIMSEC subject codes are validated for uniqueness within a framework and are required before a subject can appear on a `CMP-01` candidate registration export. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Framework manager | `Academic\Curriculum\Frameworks` | `curriculum.manage` — with the confirmation banner |
| Subject catalogue | `Academic\Curriculum\Subjects` | `curriculum.view` |
| Subject editor | `Academic\Curriculum\SubjectEditor` | `curriculum.manage` — group, codes, weighting, SBP flag |
| Subject groups | `Academic\Curriculum\Groups` | `curriculum.manage` — **shows the fee rate mapped to each group** |
| Level offerings | `Academic\Curriculum\Offerings` | `curriculum.manage` — level × subject grid with compulsory toggles and option blocks |
| Pathways | `Academic\Curriculum\Pathways` | `curriculum.manage` |
| **Selection rules** | `Academic\Curriculum\SelectionRules` | `curriculum.manage` ⚠ — rule builder with a live tester: pick a level and a subject set, see pass or fail |
| Prerequisites | `Academic\Curriculum\Prerequisites` | `curriculum.manage` |
| Syllabus repository | `Academic\Curriculum\Syllabi` | `curriculum.view` |

The subject-groups screen showing the mapped fee rate is a small thing that prevents a large error: a bursar who renames a subject group without realising it is the key `FIN-02` prices against.

### 6. API endpoints

```
GET /api/v1/academic/frameworks                     active framework for this school
GET /api/v1/academic/subjects                       ?level=&pathway=&framework=
GET /api/v1/academic/subject-groups
GET /api/v1/academic/offerings                      ?level=&year=&pathway=
GET /api/v1/academic/selection-rules                ?level=&pathway=
POST /api/v1/academic/selection-rules/validate      { subject_ids } → SelectionResult
```

`POST /selection-rules/validate` is what lets the Next.js subject-choice form and the public application form both show live validation as a learner picks subjects, using exactly the same engine the server enforces with.

### 7. Permissions · Settings · Events

```
curriculum.view              curriculum.manage ⚠
curriculum.rule.manage ⚠     curriculum.syllabus.manage
```

| Setting | Type | Default |
|---|---|---|
| `curriculum.default_framework` | string | `HBC_2024` |
| `curriculum.pathway_applies_from_ordinal` | int | `8` (Form 1) |
| `curriculum.enforce_prerequisites` | bool | `false` |
| `curriculum.auto_enrol_compulsory` | bool | `true` |
| `curriculum.warn_unconfirmed_rules` | bool | `true` |

Events: `FrameworkActivated` · `SubjectCreated` · `SubjectDeactivated` · `OfferingChanged` · `SelectionRuleChanged` · `PathwayDefined`

### 8. Acceptance criteria

```gherkin
AC-ACA-01-001
  Given the Heritage-Based framework is active
  And a max_total rule of 8 with severity 'block' exists for Form 1-4
  When a learner is given 9 subjects
  Then the selection is refused with the rule's message

AC-ACA-01-002
  Given a max_total rule of 4 for Form 5-6 with severity 'block'
  When a learner is given 5 subjects
  Then the selection is refused with the rule's message
  And no acknowledgement path exists to override it

AC-ACA-01-002B
  Given a max_total rule of 6 for Primary with severity 'warn'
  When a learner is given 7 learning areas
  Then the selection saves after explicit acknowledgement
  And the acknowledging user is recorded

AC-ACA-01-003
  Given a selection rule has requires_confirmation = 1
  When an administrator opens the curriculum setup screen
  Then a banner names that rule
  And it persists until marked reviewed for the current year

AC-ACA-01-004
  Given a subject count limit changes mid-year
  When I edit the rule
  Then no deployment is required
  And existing selections are re-validated and violations reported, not auto-corrected

AC-ACA-01-005
  Given a 2023 report card created under the competence-based framework
  When it is regenerated in 2026 under Heritage-Based
  Then it renders under the 2015 framework rules

AC-ACA-01-006
  Given two A-Level subjects share option block B
  When a learner selects both
  Then the selection is blocked

AC-ACA-01-007
  Given a subject belongs to the SCIENCES_PRACTICAL group
  And FIN-02 maps that group to a rate of 11000 minor units
  When a part-time learner enrols in it
  Then they are billed at that rate, not the base rate
```

---

# ACA-02 · Class, Stream & Subject Enrolment ⭐

> The module Book B was written against. `learner_subject_enrolments` is the **single authoritative source** of a learner's subject count, and there is no second place where that number lives.

### 1. Scope

**In scope.** Class allocation within a term, per-learner subject enrolment with dated add and drop history, option block validation, subject set capacity, bulk enrolment, the subject-count query interface consumed by `FIN-02`.

**Out of scope.** Class creation (`CORE-02`). Subject definitions (`ACA-01`). Timetabling (`ACA-03`).

### 2. Data model

```sql
learner_subject_enrolments            -- ⭐ THE billing source of truth
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK INDEX
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
subject_id              BIGINT       FK INDEX
class_id                BIGINT       NULL FK    -- teaching group; may differ from form class
subject_group_id        BIGINT       NULL FK    -- denormalised from ACA-01 for fee rate
enrolment_reason        VARCHAR(30)  NOT NULL   -- compulsory|elective|added_by_request|
                                                -- repeat|remedial|transferred_in
status                  VARCHAR(20)  NOT NULL   -- active|dropped|completed|transferred
-- ⭐ THE DATES BILLING DEPENDS ON
effective_from          DATE         NOT NULL   -- when the learner started this subject
effective_to            DATE         NULL       -- when they stopped
-- billing linkage
is_billable             TINYINT(1)   NOT NULL DEFAULT 1
billing_status          VARCHAR(20)  NOT NULL DEFAULT 'pending'
                                     -- pending|billed|credited|not_applicable
fee_line_id             BIGINT       NULL FK → learner_fee_lines.id
credit_note_id          BIGINT       NULL FK
-- audit
added_by                BIGINT       FK → users.id
added_at                TIMESTAMP    NOT NULL
dropped_by              BIGINT       NULL FK
dropped_at              TIMESTAMP    NULL
drop_reason             VARCHAR(255) NULL
approval_request_id     BIGINT       NULL FK    -- late changes need approval
created_at, updated_at
  UNIQUE (school_id, term_id, student_id, subject_id, effective_from)
  INDEX  (school_id, term_id, student_id, status)      -- ⭐ the billing count query
  INDEX  (school_id, term_id, subject_id, status)      -- class lists
  INDEX  (school_id, student_id, effective_from)
  INDEX  (school_id, billing_status)

subject_enrolment_changes             -- APPEND-ONLY history
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
term_id                 BIGINT       FK
subject_id              BIGINT       FK
change_type             VARCHAR(20)  NOT NULL   -- added|dropped|transferred|reinstated
effective_from          DATE         NOT NULL   -- ⭐ drives proration
teaching_days_remaining SMALLINT     NULL       -- snapshot at change time
term_teaching_days      SMALLINT     NULL       -- snapshot; the proration denominator
proration_factor        DECIMAL(8,6) NULL       -- computed and stored, not recomputed later
reason                  VARCHAR(255) NULL
changed_by              BIGINT       FK → users.id
changed_at              TIMESTAMP    NOT NULL
billing_event_dispatched TINYINT(1)  NOT NULL DEFAULT 0
billing_event_result    VARCHAR(30)  NULL       -- charged|credited|no_change|failed
billing_reference       VARCHAR(80)  NULL       -- fee line or credit note number
  INDEX (school_id, student_id, term_id)
  INDEX (school_id, billing_event_dispatched)

class_allocations                     -- form class per term (distinct from teaching groups)
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
class_id                BIGINT       FK INDEX
allocation_type         VARCHAR(20)  NOT NULL   -- initial|promoted|transferred|
                                                -- repeated|manual
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
status                  VARCHAR(20)  NOT NULL   -- draft|confirmed|superseded
allocated_by            BIGINT       FK → users.id
confirmed_by            BIGINT       NULL FK
notes                   VARCHAR(255) NULL
  UNIQUE (school_id, term_id, student_id, effective_from)
  INDEX  (school_id, term_id, class_id, status)

teaching_groups                       -- sets for a subject, independent of form class
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
subject_id              BIGINT       FK INDEX
grade_level_id          BIGINT       FK
code                    VARCHAR(30)  NOT NULL   -- 'F3-MATH-SET1'
name                    VARCHAR(120) NOT NULL
set_level               VARCHAR(20)  NULL       -- top|middle|foundation
teacher_staff_id        BIGINT       NULL FK    -- PPL-04
room_id                 BIGINT       NULL FK
capacity                SMALLINT     NULL
current_count           SMALLINT     NOT NULL DEFAULT 0
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, term_id, code)
  INDEX  (school_id, term_id, subject_id)

subject_selection_submissions         -- option-choice workflow (Form 3, Lower 6)
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
student_id              BIGINT       FK INDEX
grade_level_id          BIGINT       FK
pathway_id              BIGINT       NULL FK
selected_subject_ids    JSON         NOT NULL
reserve_subject_ids     JSON         NULL
validation_result       JSON         NULL       -- rule outcomes at submission
indicative_fee_minor    BIGINT       NULL       -- ⭐ shown to part-time families
indicative_fee_currency CHAR(3)      NULL
status                  VARCHAR(20)  NOT NULL   -- draft|submitted|guardian_approved|
                                                -- school_approved|allocated|rejected
submitted_by            BIGINT       NULL FK    -- learner or guardian
guardian_approved_by    BIGINT       NULL FK
school_approved_by      BIGINT       NULL FK
rejection_reason        VARCHAR(255) NULL
submitted_at, approved_at, allocated_at
```

### 3. ⭐ The billing interface

This is the contract `FIN-02` calls. It is the only sanctioned way to obtain a subject count.

```php
final class SubjectEnrolmentQuery
{
    /**
     * The billable subject count for a learner on a date.
     * Called by FIN-02 for every PER_SUBJECT billing basis evaluation.
     */
    public function billableCountOn(
        Student $student,
        Term $term,
        CarbonImmutable $on,
    ): int {
        return LearnerSubjectEnrolment::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->where('is_billable', true)
            ->where('effective_from', '<=', $on)
            ->where(fn ($q) => $q->whereNull('effective_to')
                                 ->orWhere('effective_to', '>', $on))
            ->count();
    }

    /** With subject group, so FIN-02 can apply per-group rates. */
    public function billableSubjectsOn(
        Student $student,
        Term $term,
        CarbonImmutable $on,
    ): Collection {
        // returns [{ subject_id, subject_name, subject_group_code, effective_from }]
    }

    /** Every dated change in the term — the proration inputs. */
    public function changesInTerm(Student $student, Term $term): Collection;
}
```

**There is no `students.subject_count` column, and there never will be.** Denormalising this number is the single most tempting optimisation in the system and the single most reliable way to produce a fee book that disagrees with the class register.

### 4. ⭐ The add/drop billing flow

```
TEACHER OR REGISTRAR ADDS A SUBJECT
  │
  1  Validate against ACA-01 selection rules for the learner's level and pathway
  │     blocks → refuse; warns → require acknowledgement
  2  Validate teaching group capacity (warn or block per setting)
  3  Determine effective_from
  │     default: today
  │     backdated: allowed within the term, requires reason
  │     late in term: past the cutoff → requires approval (CORE-07)
  4  Create learner_subject_enrolments (status=active, billing_status=pending)
  5  Snapshot proration inputs into subject_enrolment_changes:
  │     term_teaching_days       ← from CORE-03, AT THIS MOMENT
  │     teaching_days_remaining  ← from effective_from to term end
  │     proration_factor         ← remaining / total, computed and STORED
  6  Emit SubjectEnrolmentAdded
  │
  ▼
FIN-02 LISTENER
  7  Is the learner PART_TIME? If FULL_TIME → billing_status = not_applicable, stop.
  8  Resolve the fee structure item with basis = per_subject
  9  Rate = subject_rate_map[subject_group] ?? unit_rate_minor
 10  Gross = rate × stored proration_factor
 11  Re-apply tier bands against the NEW total subject count
 12  Raise the charge line; link fee_line_id back onto the enrolment
 13  billing_status = billed; record billing_reference
 14  Notify the fee-responsible guardian with the calculation note
```

**Why the proration factor is stored rather than recomputed.** If `term.teaching_days` is later corrected — a public holiday added, an unplanned closure — recomputing would silently change an already-issued charge. Storing the factor at the moment of the change makes the original charge defensible forever. `BR-FIN-02-006` already requires that such a change be flagged for review rather than silently applied; this is the mechanism.

**Drop is the mirror:** `effective_to` set, `proration_factor` computed on the *unused* remainder, a credit note raised, tier bands re-applied downward.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-02-001` ⭐ | `learner_subject_enrolments` is the sole source of subject count. No other table or column stores it, and any code computing it another way fails review. |
| `BR-ACA-02-002` | Every enrolment carries `effective_from`. Every drop carries `effective_to`. Both are dates, not timestamps, and both drive proration. |
| `BR-ACA-02-003` | Adding or dropping a subject validates against `ACA-01` rules. Blocking violations refuse; warnings require recorded acknowledgement. |
| `BR-ACA-02-004` | Compulsory subjects auto-enrol on class allocation with `enrolment_reason = compulsory`, and cannot be dropped without `academic.drop_compulsory_subject`. |
| `BR-ACA-02-005` | An add or drop after `academic.subject_change_cutoff_week` requires approval through `CORE-07`, because it changes an already-issued invoice. |
| `BR-ACA-02-006` | Backdating within the term is permitted with a reason. Backdating into a locked financial period requires the same permission and flagging as `FIN-01` prior-period adjustment. |
| `BR-ACA-02-007` | Proration inputs are snapshotted at change time into `subject_enrolment_changes` and never recomputed. |
| `BR-ACA-02-008` | Every change emits `SubjectEnrolmentAdded` or `SubjectEnrolmentDropped`. `billing_event_dispatched` and `billing_event_result` are tracked; a failure is retried and reported, never silently dropped. |
| `BR-ACA-02-009` | For `FULL_TIME` learners the billing listener records `not_applicable` and posts nothing. The event still fires, so the audit trail is uniform. |
| `BR-ACA-02-010` | Adding a subject re-evaluates tier bands against the new total count. A learner crossing from four to five subjects gets the lower band rate applied per the structure. |
| `BR-ACA-02-011` | Dropping to zero billable subjects for a `PART_TIME` learner raises an exception on the billing report; it does not silently produce a zero invoice. |
| `BR-ACA-02-012` | Teaching group capacity is enforced when `academic.enforce_teaching_group_capacity` is on, and always warned. Laboratory capacity is a physical constraint, not a preference. |
| `BR-ACA-02-013` | A learner may belong to exactly one teaching group per subject per term. |
| `BR-ACA-02-014` | Class allocation is per term and dated. Mid-term class movement supersedes rather than overwrites; the history remains queryable. |
| `BR-ACA-02-015` | Subject selection submissions require guardian approval before school approval when `academic.require_guardian_subject_approval` is on — appropriate because for a part-time family the choice determines the bill. |
| `BR-ACA-02-016` | A subject selection submission displays the indicative termly fee for part-time learners **before** submission, computed through `FIN-02`'s simulator. |
| `BR-ACA-02-017` | Bulk enrolment validates every learner independently and reports per-learner outcomes. One failure does not abort the batch. |
| `BR-ACA-02-018` | Term roll-over clones active subject enrolments into the new term as drafts, honouring level progression. Subjects not offered at the new level are dropped and reported. |
| `BR-ACA-02-019` | A withdrawn or transferred learner has all active subject enrolments closed with `effective_to` set to their exit date, generating the pro-rata credit through the normal path. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Class allocation | `Academic\Allocation\Classes` | `academic.allocate` — drag between streams, live counts, gender balance |
| **Subject enrolment** | `Academic\Enrolment\LearnerSubjects` | `academic.enrolment.manage` ⭐ — current subjects, add/drop with **effective date and live fee impact**, full dated history |
| Bulk subject enrolment | `Academic\Enrolment\Bulk` | `academic.enrolment.manage` — by class or level, validation report before commit |
| Teaching groups | `Academic\Groups\Index` | `academic.group.manage` — sets, capacity, teacher, current count |
| Set allocation | `Academic\Groups\Allocate` | `academic.group.manage` — move learners between sets |
| **Subject selection** | `Academic\Selection\Form` | learner/guardian via API; staff view here — live rule validation and indicative fee |
| Selection approvals | `Academic\Selection\Approvals` | `academic.selection.approve` |
| Subject registers | `Academic\Enrolment\Registers` | `academic.enrolment.view` — who takes what, printable |
| **Billing reconciliation** | `Academic\Enrolment\BillingCheck` | `academic.enrolment.view` ⭐ — every part-time learner: subject count vs billed count, mismatches highlighted |

The billing reconciliation screen is the safety net for the entire part-time model. It answers one question: *does every part-time learner's billed subject count equal their actual subject count?* Any row where it does not is a defect, and the screen surfaces it before a parent does.

### 7. API endpoints

```
GET  /api/v1/students/{ulid}/subjects              ?term=  → current with effective dates
GET  /api/v1/students/{ulid}/subject-history       ?term=  → dated add/drop log
GET  /api/v1/me/subjects                           learner or guardian view
GET  /api/v1/academic/teaching-groups              ?subject=&term=
GET  /api/v1/academic/teaching-groups/{ulid}/roll  teacher's class list

POST /api/v1/academic/selections                   submit choices
GET  /api/v1/academic/selections/{ulid}
POST /api/v1/academic/selections/{ulid}/approve    guardian approval
POST /api/v1/academic/selections/preview-fee       ⭐ { subject_ids } → indicative fee
```

`POST /selections/preview-fee` is the endpoint that lets a part-time family see what four subjects will cost before they commit to four subjects. It calls the same `FIN-02` engine that will later bill them, so the preview and the invoice cannot disagree.

### 8. Permissions · Settings

```
academic.enrolment.view             academic.enrolment.manage
academic.enrolment.backdate ⚠       academic.drop_compulsory_subject ⚠
academic.allocate                   academic.group.manage
academic.selection.submit           academic.selection.approve
```

| Setting | Type | Default |
|---|---|---|
| `academic.subject_change_cutoff_week` | int | `3` |
| `academic.enforce_teaching_group_capacity` | bool | `false` |
| `academic.require_guardian_subject_approval` | bool | `true` |
| `academic.auto_enrol_compulsory_on_allocation` | bool | `true` |
| `academic.allow_backdated_enrolment` | bool | `true` |
| `academic.backdate_limit_days` | int | `30` |
| `academic.show_indicative_fee_on_selection` | bool | `true` |

### 9. Events

**Published:** `SubjectEnrolmentAdded` ⭐ · `SubjectEnrolmentDropped` ⭐ · `SubjectEnrolmentBackdated` · `ClassAllocationConfirmed` · `TeachingGroupAssigned` · `SubjectSelectionSubmitted` · `SubjectSelectionApproved` · `PartTimeLearnerHasNoSubjects` ⚠

**Consumed:** `LearnerEnrolled` (auto-enrol compulsory) · `LearnerGradeLevelChanged` (re-evaluate offerings) · `LearnerPathwayChanged` (re-validate selection) · `LearnerWithdrawn` (close enrolments at exit date) · `PeriodRollover` (clone as drafts)

### 10. Acceptance criteria

```gherkin
AC-ACA-02-001
  Given a PART_TIME learner with three subjects
  When FIN-02 computes their term charge
  Then the quantity is 3
  And it is derived from learner_subject_enrolments, not from any stored count

AC-ACA-02-002
  Given a 13-week term with 65 teaching days
  And a PART_TIME learner adds a SCIENCES subject effective in week 4
  Then a charge is raised at the SCIENCES group rate × the stored proration factor
  And the fee line's calculation note states the rate, the day fraction,
      and the effective date

AC-ACA-02-003
  Given a subject was added with a stored proration factor of 45/65
  And term.teaching_days is later corrected to 63
  Then the already-issued charge is unchanged
  And the change is flagged for review

AC-ACA-02-004
  Given a PART_TIME learner drops a subject in week 6
  Then a credit note is raised for the unused remainder
  And their billed subject count decreases by one

AC-ACA-02-005
  Given a FULL_TIME learner adds a subject
  Then billing_status is not_applicable
  And no charge is raised
  And the event is still recorded in the audit trail

AC-ACA-02-006
  Given a fee structure with a tier band lowering the rate from the fifth subject
  When a learner moves from four to five subjects
  Then the band is re-applied
  And the total reflects the lower rate per the structure

AC-ACA-02-007
  Given the subject change cutoff is week 3
  When a change is attempted in week 7
  Then it requires approval before taking effect

AC-ACA-02-008
  Given a PART_TIME learner drops their last billable subject
  Then they appear on the billing exception report
  And no zero-value invoice is issued

AC-ACA-02-009
  Given a learner is withdrawn on 2026-10-15
  Then every active subject enrolment closes with effective_to 2026-10-15
  And a single consolidated pro-rata credit is raised

AC-ACA-02-010
  Given the billing reconciliation screen is opened
  When any part-time learner's enrolled count differs from their billed count
  Then that learner is highlighted with both figures shown

AC-ACA-02-011
  Given a family selects four subjects on the selection form
  When they request the fee preview
  Then the indicative fee equals what FIN-02 will actually bill
```

---

# ACA-04 · Attendance

> Operationally the highest-frequency write in the system — 1,400 learners × 8 periods × 5 days is 56,000 records a week — and the feature parents notice first.

### 1. Scope

**In scope.** Attendance modes, register marking (web, mobile, offline, hardware), absence reasons, late tracking, parent notification, percentages feeding report cards, statutory registers, teacher compliance monitoring.

**Out of scope.** Boarding roll call (`BRD-02`, which reuses this engine with different roll points). Staff attendance (`PPL-04`).

### 2. Data model

```sql
attendance_sessions                   -- a markable occasion
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
session_date            DATE         NOT NULL
mode                    VARCHAR(20)  NOT NULL   -- daily|period|subject|activity|assembly
class_id                BIGINT       NULL FK
teaching_group_id       BIGINT       NULL FK
subject_id              BIGINT       NULL FK
period_number           SMALLINT     NULL
timetable_slot_id       BIGINT       NULL FK    -- ACA-03
expected_count          SMALLINT     NOT NULL DEFAULT 0
present_count           SMALLINT     NOT NULL DEFAULT 0
absent_count            SMALLINT     NOT NULL DEFAULT 0
late_count              SMALLINT     NOT NULL DEFAULT 0
excused_count           SMALLINT     NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL   -- pending|partial|completed|locked
marked_by               BIGINT       NULL FK → users.id
marked_at               TIMESTAMP    NULL
locked_at               TIMESTAMP    NULL
device_source           VARCHAR(20)  NULL       -- web|mobile|rfid|biometric|import
  UNIQUE (school_id, session_date, mode, class_id, teaching_group_id, period_number)
  INDEX  (school_id, term_id, session_date, status)
  INDEX  (school_id, class_id, session_date)

attendance_records
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
session_id              BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
term_id                 BIGINT       FK          -- denormalised for aggregation speed
session_date            DATE         NOT NULL    -- denormalised
status                  VARCHAR(20)  NOT NULL    -- present|absent|late|excused|
                                                 -- sick_bay|exeat|suspended|activity
reason_code_id          BIGINT       NULL FK
minutes_late            SMALLINT     NULL
note                    VARCHAR(255) NULL
marked_by               BIGINT       NULL FK
marked_at               TIMESTAMP    NOT NULL
amended_by              BIGINT       NULL FK
amended_at              TIMESTAMP    NULL
amendment_reason        VARCHAR(255) NULL
guardian_notified_at    TIMESTAMP    NULL
notification_id         BIGINT       NULL FK
  UNIQUE (session_id, student_id)
  INDEX  (school_id, student_id, session_date)
  INDEX  (school_id, term_id, student_id, status)
  INDEX  (school_id, session_date, status)

attendance_reason_codes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL    -- SICK|FUNERAL|EXEAT|SPORT|TRANSPORT|
                                                 -- FEES|UNEXPLAINED|SUSPENDED|MEDICAL
name                    VARCHAR(80)  NOT NULL
counts_as_present       TINYINT(1)   NOT NULL DEFAULT 0   -- school activity absences
counts_toward_percentage TINYINT(1)  NOT NULL DEFAULT 1
is_authorised           TINYINT(1)   NOT NULL DEFAULT 1
requires_document       TINYINT(1)   NOT NULL DEFAULT 0
suppresses_notification TINYINT(1)   NOT NULL DEFAULT 0   -- already-known absences
triggers_welfare_flag   TINYINT(1)   NOT NULL DEFAULT 0
sort_order              SMALLINT
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

attendance_summaries                  -- CACHE, rebuilt nightly and at term close
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
term_id                 BIGINT       FK INDEX
scope                   VARCHAR(20)  NOT NULL    -- term|subject
subject_id              BIGINT       NULL FK
sessions_expected       SMALLINT     NOT NULL DEFAULT 0
present_count           SMALLINT     NOT NULL DEFAULT 0
absent_authorised       SMALLINT     NOT NULL DEFAULT 0
absent_unauthorised     SMALLINT     NOT NULL DEFAULT 0
late_count              SMALLINT     NOT NULL DEFAULT 0
attendance_percent      DECIMAL(5,2) NULL
consecutive_absent_max  SMALLINT     NOT NULL DEFAULT 0
is_chronic_absentee     TINYINT(1)   NOT NULL DEFAULT 0
rebuilt_at              TIMESTAMP
  UNIQUE (school_id, student_id, term_id, scope, subject_id)

attendance_marking_compliance         -- did teachers actually mark?
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
staff_id                BIGINT       FK INDEX
session_date            DATE         NOT NULL
expected_sessions       SMALLINT     NOT NULL DEFAULT 0
marked_sessions         SMALLINT     NOT NULL DEFAULT 0
marked_late_sessions    SMALLINT     NOT NULL DEFAULT 0
compliance_percent      DECIMAL(5,2) NULL
  UNIQUE (school_id, staff_id, session_date)
```

### 3. Offline marking — the Zimbabwean constraint

A teacher in a laboratory block with no signal must be able to mark a register, and that register must arrive intact when they walk back to the staff room.

```
FLUTTER / PWA
  1  On login, cache today's sessions and class lists for this teacher
  2  Teacher marks; writes go to a local queue with:
       - a client-generated idempotency key per session
       - the device clock time AND the session date
  3  On reconnect, the queue drains in order
  4  Server applies each mark idempotently:
       already marked by someone else → the FIRST mark stands;
       the conflicting later mark is recorded as an amendment attempt
       and surfaced to the teacher, never silently discarded
  5  Client reconciles and shows what was accepted and what conflicted
```

| # | Rule |
|---|---|
| Marks are idempotent on `(session_id, student_id, idempotency_key)`. |
| The **server** determines `session_date` from the session, never from the device clock — a phone with a wrong date must not create a register for the wrong day. |
| Queue depth and oldest-queued-item age are surfaced in the app so a teacher knows their marks have not yet reached the school. |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-04-001` | Attendance modes are configurable per section. Primary typically marks daily; secondary marks per period or per subject. |
| `BR-ACA-04-002` | Sessions generate automatically from the timetable (`ACA-03`) or from a daily schedule where no timetable exists. |
| `BR-ACA-04-003` | Only learners with an active enrolment for that class or teaching group on that date appear on a register. |
| `BR-ACA-04-004` | A learner on approved exeat, in the sick bay, on a school activity, or suspended is pre-populated with the corresponding status and is **not** counted as an unexplained absence. |
| `BR-ACA-04-005` | Reason codes with `counts_as_present = 1` (sports fixture, school trip, ZIMSEC examination) do not reduce the attendance percentage. |
| `BR-ACA-04-006` ⭐ | Unexplained absence at the daily registration cut-off notifies the primary contact within `attendance.notification_delay_minutes` (default 30). This is the single highest-value notification in the product for parent perception. |
| `BR-ACA-04-007` | Notification is suppressed where the reason code has `suppresses_notification = 1`, and deduplicated so a parent receives one message per learner per day, not one per period. |
| `BR-ACA-04-008` | Amending a mark records the amender, the timestamp, and a reason. The original value remains visible. |
| `BR-ACA-04-009` | A session locks after `attendance.lock_after_hours` (default 48). Amending a locked session requires `academic.attendance.amend_locked`. |
| `BR-ACA-04-010` | Attendance in a term whose academic period is `LOCKED` cannot be marked or amended at all. |
| `BR-ACA-04-011` | Percentages are derived from records and cached in `attendance_summaries`, rebuilt nightly and recomputed from source at term close before appearing on a report card. |
| `BR-ACA-04-012` | Consecutive unexplained absence exceeding `attendance.chronic_threshold_days` (default 3) escalates to the class teacher and, where the learner is flagged vulnerable, to the counsellor. |
| `BR-ACA-04-013` | Attendance below `attendance.chronic_percent_threshold` (default 80%) sets `is_chronic_absentee` and feeds `INT-03` early warning. |
| `BR-ACA-04-014` | Teacher marking compliance is computed daily. Unmarked registers appear on the deputy head's dashboard the same day, not at term end. |
| `BR-ACA-04-015` | Hardware sources (RFID, biometric) create records with `device_source` set. A hardware failure never blocks manual marking. |
| `BR-ACA-04-016` | Statutory registers export in the MoPSE inspection format, per class per term, with the school stamp and the class teacher's name. |
| `BR-ACA-04-017` | Bulk-marking a whole class present is permitted with one action, but each individual record is still written, so an amendment later is per learner. |
| `BR-ACA-04-018` | Attendance must never be inferred. An unmarked register is `pending`, not "everyone present". |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| **Mark register** | `Academic\Attendance\Mark` | `academic.attendance.mark` — photo grid, one tap per learner, mark-all-present, keyboard shortcuts, autosave |
| Daily overview | `Academic\Attendance\Daily` | `academic.attendance.view` — every register today: marked, partial, missing |
| Learner attendance | `Academic\Attendance\Learner` | `academic.attendance.view` — calendar heatmap, percentage, patterns |
| Class attendance | `Academic\Attendance\ClassReport` | `academic.attendance.view` |
| Absence follow-up | `Academic\Attendance\FollowUp` | `academic.attendance.manage` — unexplained absences needing a reason |
| Chronic absentee list | `Academic\Attendance\Chronic` | `academic.attendance.view` |
| Marking compliance | `Academic\Attendance\Compliance` | `academic.attendance.view_compliance` — by teacher, by day |
| Statutory register | `Academic\Attendance\StatutoryExport` | `academic.attendance.export` |
| Amend record | `Academic\Attendance\Amend` | `academic.attendance.amend` |

### 6. API endpoints

```
GET  /api/v1/attendance/sessions              ?date=  → teacher's registers today
GET  /api/v1/attendance/sessions/{ulid}       → roll with pre-populated statuses
POST /api/v1/attendance/sessions/{ulid}/mark  Idempotency-Key REQUIRED
     { records: [{ student, status, reason_code?, minutes_late?, note? }] }
POST /api/v1/attendance/sync                  batch offline queue drain
GET  /api/v1/students/{ulid}/attendance       ?term=  → guardian view
GET  /api/v1/me/attendance                    learner view
```

### 7. Permissions · Settings · Events

```
academic.attendance.view            academic.attendance.mark
academic.attendance.manage          academic.attendance.amend
academic.attendance.amend_locked ⚠  academic.attendance.view_compliance
academic.attendance.export
```

| Setting | Type | Default |
|---|---|---|
| `attendance.default_mode_primary` | enum | `daily` |
| `attendance.default_mode_secondary` | enum | `period` |
| `attendance.registration_cutoff_time` | time | `08:15` |
| `attendance.notification_delay_minutes` | int | `30` |
| `attendance.notify_on_unexplained_only` | bool | `true` |
| `attendance.lock_after_hours` | int | `48` |
| `attendance.chronic_threshold_days` | int | `3` |
| `attendance.chronic_percent_threshold` | int | `80` |
| `attendance.show_percentage_on_report_card` | bool | `true` |

Events: `AttendanceMarked` · `LearnerAbsentUnexplained` ⭐ · `ConsecutiveAbsenceThresholdReached` · `ChronicAbsenteeIdentified` · `RegisterNotMarked` · `AttendanceAmended`

### 8. Acceptance criteria

```gherkin
AC-ACA-04-001
  Given a learner is marked absent with reason UNEXPLAINED at morning registration
  Then their primary contact is notified within 30 minutes
  And exactly one notification is sent for that learner that day

AC-ACA-04-002
  Given a learner is on approved exeat
  When the register loads
  Then their status is pre-populated as EXEAT
  And no absence notification is sent

AC-ACA-04-003
  Given a learner attends a school sports fixture
  And the reason code has counts_as_present = 1
  Then their attendance percentage is unaffected

AC-ACA-04-004
  Given a teacher marks a register offline on a device with an incorrect date
  When the queue syncs
  Then the record is attributed to the session's date, not the device's

AC-ACA-04-005
  Given two teachers mark the same session concurrently
  Then the first mark stands
  And the second is recorded as an amendment attempt and shown to that teacher

AC-ACA-04-006
  Given a session is 60 hours old and the lock window is 48 hours
  When a teacher attempts to amend it
  Then it is refused unless they hold attendance.amend_locked

AC-ACA-04-007
  Given a register is unmarked by 10:00
  Then it appears on the deputy head's dashboard the same morning
  And is never treated as all-present

AC-ACA-04-008
  Given a learner has three consecutive unexplained absences
  Then the class teacher is alerted
  And if the learner is flagged vulnerable, the counsellor is also alerted
```

---

# ACA-05 · Assessment, Grading & Report Cards

### 1. Scope

**In scope.** Assessment types and weighting, grading scale engine, mark entry with draft and submit, amendment versioning, aggregation, positions, comments, report card generation with the fee release gate, publication, historical retrieval, transcripts.

**Out of scope.** SBP execution (`ACA-06`, Book E — this module defines the interface and renders the outcome). Public examinations (`ACA-07`).

### 2. Data model

```sql
grading_scales
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
framework_id            BIGINT       NULL FK
code                    VARCHAR(30)  NOT NULL   -- 'ZIMSEC_OLEVEL','ZIMSEC_ALEVEL',
                                                -- 'GRADE7_UNITS','PRIMARY_DMCP','CAMBRIDGE'
name                    VARCHAR(120) NOT NULL
scale_type              VARCHAR(20)  NOT NULL   -- letter|unit|band|points|percentage
lower_is_better         TINYINT(1)   NOT NULL DEFAULT 0   -- Grade 7 unit grades
pass_grade              VARCHAR(10)  NULL
is_default_for_level    JSON         NULL       -- grade_level_ids
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

grade_bands
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
grading_scale_id        BIGINT       FK INDEX
grade                   VARCHAR(10)  NOT NULL   -- 'A','B','1','Distinction'
descriptor              VARCHAR(80)  NULL       -- 'Excellent'
min_percent             DECIMAL(5,2) NOT NULL
max_percent             DECIMAL(5,2) NOT NULL
points                  DECIMAL(5,2) NULL       -- A-Level points
is_pass                 TINYINT(1)   NOT NULL DEFAULT 1
colour                  CHAR(7)      NULL       -- report card highlighting
sort_order              SMALLINT     NOT NULL
  UNIQUE (grading_scale_id, grade)

assessment_types
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL   -- 'TOPIC_TEST','MIDTERM','ENDTERM',
                                                -- 'MOCK','PRACTICAL','ORAL','SBP'
name                    VARCHAR(120) NOT NULL
category                VARCHAR(20)  NOT NULL   -- coursework|examination|
                                                -- continuous_assessment
default_weight_percent  DECIMAL(5,2) NOT NULL
appears_on_report_card  TINYINT(1)   NOT NULL DEFAULT 1
is_examination          TINYINT(1)   NOT NULL DEFAULT 0
sort_order              SMALLINT
  UNIQUE (school_id, code)

assessments                           -- a concrete assessable event
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
assessment_type_id      BIGINT       FK
subject_id              BIGINT       FK INDEX
grade_level_id          BIGINT       NULL FK
class_id                BIGINT       NULL FK
teaching_group_id       BIGINT       NULL FK
title                   VARCHAR(150) NOT NULL
max_mark                DECIMAL(6,2) NOT NULL
weight_percent          DECIMAL(5,2) NOT NULL   -- within the term aggregate
assessed_on             DATE         NULL
grading_scale_id        BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- draft|open|submitted|moderated|
                                                -- approved|published|locked
created_by              BIGINT       FK → users.id
submitted_by, submitted_at
approved_by, approved_at
published_at            TIMESTAMP    NULL
  INDEX (school_id, term_id, subject_id, status)

assessment_marks                      -- current value
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
assessment_id           BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
term_id                 BIGINT       FK
raw_mark                DECIMAL(6,2) NULL
percent                 DECIMAL(5,2) NULL
grade                   VARCHAR(10)  NULL
points                  DECIMAL(5,2) NULL
is_absent               TINYINT(1)   NOT NULL DEFAULT 0
absence_reason          VARCHAR(60)  NULL
comment                 VARCHAR(255) NULL
version                 SMALLINT     NOT NULL DEFAULT 1   -- ⭐
entered_by              BIGINT       FK → users.id
entered_at              TIMESTAMP    NOT NULL
  UNIQUE (assessment_id, student_id)
  INDEX  (school_id, student_id, term_id)

assessment_mark_versions              -- APPEND-ONLY history ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
assessment_id           BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
version                 SMALLINT     NOT NULL
raw_mark                DECIMAL(6,2) NULL
percent                 DECIMAL(5,2) NULL
grade                   VARCHAR(10)  NULL
change_reason           VARCHAR(255) NULL
was_published           TINYINT(1)   NOT NULL DEFAULT 0   -- amended after publication?
approval_request_id     BIGINT       NULL FK
changed_by              BIGINT       FK → users.id
changed_at              TIMESTAMP    NOT NULL
  UNIQUE (assessment_id, student_id, version)

term_subject_results                  -- aggregated per learner per subject per term
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
subject_id              BIGINT       FK INDEX
coursework_percent      DECIMAL(5,2) NULL
examination_percent     DECIMAL(5,2) NULL
continuous_percent      DECIMAL(5,2) NULL       -- 🇿🇼 SBP contribution
final_percent           DECIMAL(5,2) NULL
grade                   VARCHAR(10)  NULL
points                  DECIMAL(5,2) NULL
class_position          SMALLINT     NULL
class_size              SMALLINT     NULL
level_position          SMALLINT     NULL
subject_average         DECIMAL(5,2) NULL
teacher_comment         VARCHAR(500) NULL
teacher_staff_id        BIGINT       NULL FK
sbp_outcome             VARCHAR(30)  NULL       -- 🇿🇼 from ACA-06
sbp_grade               VARCHAR(10)  NULL
is_finalised            TINYINT(1)   NOT NULL DEFAULT 0
computed_at             TIMESTAMP
  UNIQUE (school_id, term_id, student_id, subject_id)
  INDEX  (school_id, term_id, subject_id, final_percent)

term_results                          -- overall per learner per term
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
class_id                BIGINT       FK
subjects_taken          TINYINT      NOT NULL DEFAULT 0
subjects_passed         TINYINT      NOT NULL DEFAULT 0
total_marks             DECIMAL(8,2) NULL
average_percent         DECIMAL(5,2) NULL
total_points            DECIMAL(6,2) NULL
aggregate               SMALLINT     NULL        -- Grade 7 style
class_position          SMALLINT     NULL
class_size              SMALLINT     NULL
level_position          SMALLINT     NULL
level_size              SMALLINT     NULL
attendance_percent      DECIMAL(5,2) NULL        -- from ACA-04 at close
conduct_grade           VARCHAR(20)  NULL        -- from BRD-07
class_teacher_comment   TEXT         NULL
head_comment            TEXT         NULL
promotion_recommendation VARCHAR(30) NULL        -- promote|repeat|review
status                  VARCHAR(20)  NOT NULL    -- draft|computed|reviewed|approved|
                                                 -- published|withheld
withheld_reason         VARCHAR(60)  NULL        -- 'fee_balance'
report_document_id      BIGINT       NULL FK
published_at            TIMESTAMP    NULL
approved_by             BIGINT       NULL FK
  UNIQUE (school_id, term_id, student_id)
  INDEX  (school_id, term_id, class_id, status)

comment_banks
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
scope                   VARCHAR(20)  NOT NULL    -- subject|general|conduct
subject_id              BIGINT       NULL FK
grade_band              VARCHAR(20)  NULL        -- suggests by performance band
text                    VARCHAR(500) NOT NULL
usage_count             INT          NOT NULL DEFAULT 0
created_by, is_active

report_card_runs
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
scope_filter            JSON         NULL        -- section, level, class
template_id             BIGINT       FK → document_templates.id
template_version        SMALLINT     NOT NULL
total_count             INT          NOT NULL DEFAULT 0
generated_count         INT          NOT NULL DEFAULT 0
withheld_count          INT          NOT NULL DEFAULT 0
failed_count            INT          NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL    -- queued|running|completed|failed
merged_document_id      BIGINT       NULL FK
requested_by            BIGINT       FK → users.id
started_at, completed_at
```

### 3. ⭐ The aggregation pipeline

```
FOR EACH learner, FOR EACH enrolled subject, in a term:

 1  Collect every PUBLISHED assessment mark for that subject and term
 2  Group by assessment_type.category:
       coursework            → weighted mean of its assessments
       examination           → weighted mean
       continuous_assessment → 🇿🇼 SBP outcome from ACA-06

 3  Apply the subject's coursework_weight_percent from ACA-01:
       final = coursework × w  +  examination × (1 − w)
       where continuous assessment forms part of w per the framework

 4  Absent-with-reason marks are EXCLUDED from the mean, not counted as zero,
    unless the reason code is configured otherwise
    — a learner off sick for one test should not be recorded as scoring nothing

 5  Map final_percent to a grade via the subject's grading scale
       (falling back to the level default)

 6  Compute class position and level position within the subject
       tie-break: configured order (higher raw total, then alphabetical)

 7  Write term_subject_results

FOR EACH learner:

 8  subjects_taken     ← count of enrolled subjects with a result
 9  average_percent    ← mean of final_percent across subjects
10  total_points       ← Σ points where the scale carries points
11  aggregate          ← Σ unit grades where lower_is_better
12  class_position     ← rank within class by the configured basis
13  attendance_percent ← recomputed FROM SOURCE at close (ACA-04)
14  conduct_grade      ← from BRD-07 where enabled
15  promotion_recommendation ← rules on average, subjects passed, attendance
16  Write term_results (status = computed)
```

### 4. ⭐ Mark amendment after publication

Once a report card is in a parent's hands, a mark change is a serious act.

```
Mark is PUBLISHED
  │
  ├─ Teacher requests an amendment
  │     reason mandatory, minimum 15 characters
  ▼
CORE-07 approval  (HOD → Exams Officer → Deputy Head, configurable)
  │
  ├─ Rejected → original stands, request retained in history
  └─ Approved
        ├─ assessment_mark_versions: new version, was_published = 1
        ├─ assessment_marks: current value replaced, version incremented
        ├─ term_subject_results + term_results recomputed
        ├─ positions recomputed for the WHOLE CLASS (one mark moves several)
        ├─ report card marked SUPERSEDED, a new version generated
        └─ guardian notified that an amended report is available
```

**Positions must be recomputed for the whole class.** Changing one learner's mark can change five learners' positions. A system that updates only the amended learner produces a report card set where the positions do not agree with each other.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-05-001` | Grading scales are pure data. Adding a scale, changing a boundary, or defining a school's own scale requires no deployment. |
| `BR-ACA-05-002` | Grade band percentage ranges must be contiguous and non-overlapping across 0–100. Gaps and overlaps are refused at save. |
| `BR-ACA-05-003` | Scales with `lower_is_better = 1` invert ranking, aggregation, and pass logic throughout. |
| `BR-ACA-05-004` | Assessment weights within a subject and term must total 100%. A shortfall or excess blocks finalisation and is reported per subject. |
| `BR-ACA-05-005` | A teacher may enter marks only for assessments in subjects and groups they are allocated to (`academic.result.enter` scoped `assigned`), unless they hold the unscoped permission. |
| `BR-ACA-05-006` | Marks save as draft continuously and are only visible beyond the entering teacher once submitted. |
| `BR-ACA-05-007` | A mark cannot exceed `max_mark` and cannot be negative. |
| `BR-ACA-05-008` | Absence is recorded explicitly with a reason and excluded from the mean by default. It is never silently a zero. |
| `BR-ACA-05-009` ⭐ | Every mark change writes an append-only version row. The original entry is never overwritten in history. |
| `BR-ACA-05-010` | Amending a published mark requires approval, marks the version `was_published = 1`, and regenerates the report card as a new version. |
| `BR-ACA-05-011` | Position recomputation on any amendment covers the entire class and level, not just the amended learner. |
| `BR-ACA-05-012` | Positions are computed on the configured basis (average percent, total marks, or points) with a configured tie-break, and can be suppressed entirely on the report card by setting. |
| `BR-ACA-05-013` | Attendance percentage on a report card is recomputed from source records at close, never taken from the cache. |
| `BR-ACA-05-014` ⭐ | Where `finance.report_gate_enabled` is on, a learner whose balance exceeds the threshold has their report card status set to `withheld` with `withheld_reason = fee_balance`. Only components with `counts_toward_report_gate = 1` are counted. Per-learner override requires `finance.report_gate_override` and a recorded reason. |
| `BR-ACA-05-015` | A withheld report card is still **generated and stored**. It is not published to the guardian. Clearing the balance publishes it without regeneration. |
| `BR-ACA-05-016` | Report cards render with the template version in force at generation. A 2025 report regenerates identically in 2030. |
| `BR-ACA-05-017` | Publication is a deliberate act by an authorised user, per class or per level, after review. Marks becoming available does not publish anything. |
| `BR-ACA-05-018` | Publication notifies guardians and, where enabled, learners, on their preferred channel, with a portal link rather than an attachment. |
| `BR-ACA-05-019` | Results in a term whose academic period is `LOCKED` cannot be entered or amended. |
| `BR-ACA-05-020` | Finalising a term's results is a precondition for the academic period transitioning to `SOFT_CLOSED`. |
| `BR-ACA-05-021` | 🇿🇼 Where the active framework's `continuous_assessment_model = sbp`, the SBP outcome from `ACA-06` populates `continuous_percent` and appears on the report card. Where it is `cala`, the archived CALA record is used for historical terms. |
| `BR-ACA-05-022` | Transcripts aggregate `term_results` across years and are generated from source, so a transcript issued years later matches the reports issued at the time. |
| `BR-ACA-05-023` | Learners see their own results only after publication and only where `academic.publish_to_learner_portal` is on for their level band. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Grading scales | `Academic\Grading\Scales` | `academic.grading.manage` — band editor with contiguity validation |
| Assessment types | `Academic\Assessment\Types` | `academic.grading.manage` |
| Assessment planner | `Academic\Assessment\Planner` | `academic.assessment.manage` — per subject per term, **live weight total with a warning when ≠ 100%** |
| **Mark entry** | `Academic\Marks\Entry` | `academic.result.enter` — spreadsheet-style grid, keyboard navigation, autosave, live grade preview, absent toggle, paste from Excel |
| Mark submission | `Academic\Marks\Submit` | `academic.result.enter` — completeness check before submit |
| Moderation | `Academic\Marks\Moderate` | `academic.result.moderate` — distribution chart, outliers, HOD sign-off |
| Amendment request | `Academic\Marks\Amend` | `academic.result.amend` ⚠ — reason, impact preview showing whose positions change |
| Results computation | `Academic\Results\Compute` | `academic.result.compute` — per class or level, with exception report |
| Results review | `Academic\Results\Review` | `academic.result.review` — grid, distributions, promotion recommendations |
| Comment entry | `Academic\Results\Comments` | `academic.result.comment` — bank suggestions by grade band, free text |
| **Report card run** | `Academic\Reports\Run` | `academic.report_card.generate` — scope, template, preview one, generate batch, progress |
| Withheld reports | `Academic\Reports\Withheld` | `academic.report_card.view` — who, balance, override action |
| Publication | `Academic\Reports\Publish` | `academic.report_card.publish` ⚠ |
| Transcripts | `Academic\Results\Transcripts` | `academic.transcript.generate` |
| Performance analytics | `Academic\Results\Analytics` | `academic.result.view` — subject, teacher, class, trend |

**The mark entry grid is the screen teachers judge the product by.** It must accept paste from Excel, navigate entirely by keyboard, autosave without a save button, show the grade beside the mark as it is typed, and never lose work when a connection drops.

### 7. API endpoints

```
GET  /api/v1/academic/assessments             ?term=&subject=  teacher scope
GET  /api/v1/academic/assessments/{ulid}/marks
POST /api/v1/academic/assessments/{ulid}/marks    Idempotency-Key; draft save
POST /api/v1/academic/assessments/{ulid}/submit

GET  /api/v1/students/{ulid}/results          ?term=  guardian scope, published only
GET  /api/v1/students/{ulid}/report-cards
GET  /api/v1/students/{ulid}/report-cards/{ulid}/download
GET  /api/v1/students/{ulid}/performance-trend  ?subject=&from=&to=
GET  /api/v1/me/results                       learner scope, age-gated
```

A withheld report card returns `403` with code `REPORT_WITHHELD` and a message directing the guardian to the bursary — never a blank page or a generic error.

### 8. Permissions · Settings

```
academic.grading.view              academic.grading.manage
academic.assessment.view           academic.assessment.manage
academic.result.enter              academic.result.enter.assigned
academic.result.moderate           academic.result.amend ⚠
academic.result.amend_published ⚠⚠ academic.result.compute
academic.result.review             academic.result.comment
academic.report_card.generate      academic.report_card.publish ⚠
academic.report_card.view          academic.transcript.generate
finance.report_gate.override ⚠
```

| Setting | Type | Default |
|---|---|---|
| `academic.position_basis` | enum | `average_percent` |
| `academic.position_tiebreak` | enum | `total_marks` |
| `academic.show_positions_on_report` | bool | `true` |
| `academic.show_class_average_on_report` | bool | `true` |
| `academic.absent_counts_as_zero` | bool | `false` |
| `academic.require_moderation` | bool | `false` |
| `academic.mark_entry_deadline_days_after_term` | int | `7` |
| `academic.publish_to_learner_portal` | bool | `true` |
| `academic.promotion_min_average` | int | `40` |
| `academic.promotion_min_subjects_passed` | int | `5` |
| `academic.promotion_min_attendance` | int | `75` |

### 9. Events

**Published:** `AssessmentCreated` · `MarksSubmitted` · `MarksModerated` · `MarkAmended` ⚠ · `ResultsComputed` · `ReportCardGenerated` · `ReportCardWithheld` · `ReportCardPublished` · `PromotionRecommended`

**Consumed:** `SubjectEnrolmentAdded`/`Dropped` (adjust the result set) · `AttendanceSummaryRebuilt` · `SbpOutcomeRecorded` 🇿🇼 (`ACA-06`) · `ConductGradeAssigned` (`BRD-07`) · `PeriodClosing` (finalisation gate)

### 10. Acceptance criteria

```gherkin
AC-ACA-05-001
  Given assessment weights for a subject total 90%
  When results computation is attempted
  Then it is blocked
  And the subject is named with its shortfall

AC-ACA-05-002
  Given a learner was absent for one topic test with a valid reason
  Then that assessment is excluded from their mean
  And they are not recorded as scoring zero

AC-ACA-05-003
  Given a published mark is amended after approval
  Then a new version row exists with was_published = 1
  And the original value remains in history
  And positions are recomputed for the entire class
  And a new report card version is generated
  And the guardian is notified

AC-ACA-05-004
  Given report gating is on with a USD 100 threshold
  And a learner owes USD 340 on gate-counting components
  When report cards are generated
  Then their report is generated and stored
  And its status is withheld with reason fee_balance
  And the guardian API returns 403 REPORT_WITHHELD with a helpful message

AC-ACA-05-005
  Given a withheld report card and the balance is subsequently cleared
  When the report is published
  Then the stored document is released without regeneration

AC-ACA-05-006
  Given a report card generated in 2025 under template version 2
  When it is regenerated in 2030 under template version 7
  Then it renders identically to the original

AC-ACA-05-007
  Given a grading scale where lower is better
  Then ranking, aggregation, and pass logic all invert correctly

AC-ACA-05-008
  Given grade bands leave a gap between 49.5 and 50.0
  When the scale is saved
  Then it is refused naming the gap

AC-ACA-05-009
  Given the academic period for a term is LOCKED
  When a teacher attempts to enter or amend a mark
  Then it is refused with PERIOD_LOCKED

AC-ACA-05-010
  Given a learner's marks are complete but not published
  When they open the learner portal
  Then no results are visible

AC-ACA-05-011
  Given 1,400 report cards are generated
  Then the batch completes with progress reported
  And per-learner failures are listed without aborting the run

AC-ACA-05-012
  Given the active framework uses SBP for continuous assessment
  Then the SBP outcome appears on the report card
  And for a historical term under the CALA model, the CALA record is shown instead
```

---

## Part 3 — Domain C Core Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **C1** | `ACA-01` frameworks, subjects, groups, offerings | Multiple frameworks coexist; historical framework preserved |
| **C2** | `ACA-01` pathways, selection rules, rule tester | **No subject count hard-coded.** Unconfirmed-rule banner live. |
| **C3** | `ACA-02` class allocation, compulsory auto-enrol | Compulsory subjects enrol on allocation |
| **C4** | `ACA-02` subject enrolment with dated add/drop | Dated history complete; proration factors snapshotted |
| **C5** | `ACA-02` billing integration ⭐ | **`AC-ACA-02-001` to `-006` green. Part-time billing works end to end.** |
| **C6** | `ACA-02` teaching groups, selection workflow, fee preview | Preview equals the eventual invoice |
| **C7** | `ACA-04` sessions, marking, reason codes | Unmarked ≠ present, ever |
| **C8** | `ACA-04` offline sync, notification, summaries | Absence notice within 30 minutes; offline queue drains correctly |
| **C9** | `ACA-05` grading scales, assessment types, planner | Band contiguity enforced; weights validated |
| **C10** | `ACA-05` mark entry, versioning, moderation | Amendment history append-only |
| **C11** | `ACA-05` aggregation, positions, comments | Whole-class position recomputation on amendment |
| **C12** | `ACA-05` report cards, fee gate, publication | 1,400 cards generate; withheld logic correct |

**Sprint C5 completes the P1–P5 minimum sellable product.** At that point a school can enrol learners, bill both full-time and part-time correctly, take money, mark attendance, and — after C12 — issue report cards.

---

## Part 4 — Domain C Core Acceptance Gate

### The billing dependency

- [ ] `learner_subject_enrolments` is the only source of subject count; no denormalised column exists anywhere
- [ ] A part-time learner with three subjects bills at `Σ(group rates)` end to end
- [ ] Mid-term add produces a pro-rated charge; mid-term drop produces a pro-rated credit
- [ ] Proration factors are snapshotted and immune to later `teaching_days` corrections
- [ ] Tier bands re-apply on every count change
- [ ] The billing reconciliation screen shows zero mismatches across a 1,400-learner dataset
- [ ] A full-time learner adding a subject produces no charge but a complete audit record

### Curriculum

- [ ] Every subject-count limit is a `subject_selection_rules` row; a code search for hard-coded limits returns nothing
- [ ] Rules flagged `requires_confirmation` display the review banner
- [ ] Changing a limit takes effect without deployment and re-validates without auto-correcting
- [ ] A record created under an earlier framework renders under that framework's rules

### Attendance

- [ ] All `AC-ACA-04-*` pass
- [ ] Offline marking survives a full day disconnected and syncs without loss
- [ ] Concurrent marking resolves deterministically with the conflict surfaced
- [ ] An unmarked register is never treated as present
- [ ] Absence notification fires within 30 minutes, deduplicated per learner per day

### Assessment

- [ ] All `AC-ACA-05-*` pass
- [ ] Published mark amendment recomputes the whole class and regenerates the report
- [ ] Report card regeneration from an archived template version is byte-identical
- [ ] Fee gating withholds without deleting; clearing publishes without regenerating
- [ ] 1,400 report cards generate within 15 minutes with per-item failure reporting

### Cross-cutting

- [ ] Period guard rejects writes in locked terms across all four modules
- [ ] Tenancy isolation suite passes for every Domain C model
- [ ] Coverage ≥ 85% (`ACA-02` ≥ 90%, since it feeds billing)
- [ ] Mark entry grid usable entirely by keyboard, accepts Excel paste, survives a dropped connection

---

## Appendix A — The Subject Count Contract

The interface `FIN-02` depends on. Changing it is a breaking change to Book B.

```php
interface SubjectEnrolmentQuery
{
    public function billableCountOn(Student $s, Term $t, CarbonImmutable $on): int;
    public function billableSubjectsOn(Student $s, Term $t, CarbonImmutable $on): Collection;
    public function changesInTerm(Student $s, Term $t): Collection;
}
```

| Guarantee | Detail |
|---|---|
| Single source | `learner_subject_enrolments` only. No cached count, no denormalised column, ever. |
| Date-aware | Every query takes a date and honours `effective_from` / `effective_to`. |
| Group-aware | Every returned subject carries its `subject_group_code` so per-group rates apply. |
| Billable flag | `is_billable = 0` excludes a subject (audit-only, co-curricular) without deleting the enrolment. |
| Event-driven | `SubjectEnrolmentAdded` and `SubjectEnrolmentDropped` carry the learner, subject, group, effective date, and stored proration factor. |
| Snapshotted proration | The factor is computed once and stored, never recomputed from later data. |

---

## Appendix B — Seeded Grading Scales 🇿🇼

Shipped as data in the `grading` seed pack. Every value is editable per school.

| Scale | Type | Bands | Notes |
|---|---|---|---|
| `ZIMSEC_OLEVEL` | letter | A · B · C · D · E · U | Pass configured at C by default |
| `ZIMSEC_ALEVEL` | points | A · B · C · D · E · F/U | Points carried per band for aggregation |
| `GRADE7_UNITS` | unit | Numeric units, `lower_is_better = 1` | Aggregate across subjects |
| `PRIMARY_DMCP` | band | Distinction · Merit · Credit · Pass · Below | Lower primary |
| `CAMBRIDGE_IGCSE` | letter | A* · A · B · C · D · E · F · G · U | International streams |
| `PERCENTAGE` | percentage | Raw percentage, no banding | Internal tests |

**Boundary values are seeded conservatively and flagged for school confirmation**, on the same principle as the subject-count rules: published boundaries vary by subject and by examination series, and a school's own internal boundaries frequently differ from ZIMSEC's. The system supplies a starting point and expects the school to set its own.

---

## Appendix C — Next Book

**Book E — Academic Depth (`ACA-03` Timetable & Scheduling, `ACA-06` School-Based Projects, `ACA-07` Examinations Administration)** completes the academic domain. `ACA-06` implements the SBP interface this book defines and consumes; `ACA-03` generates the attendance sessions `ACA-04` marks against.

After Book E, the remaining books are: **F** Boarding & Welfare, **G** Operations & Estates, **H** Payroll, Procurement & Compliance, **I** Communication & Portals, **J** Intelligence & SaaS Control.

---

*End of Volume 2, Book D.*

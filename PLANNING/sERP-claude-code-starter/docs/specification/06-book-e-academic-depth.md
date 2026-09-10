# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book E — Domain C: Academic Depth (`ACA-03`, `ACA-06`, `ACA-07`)

| Field | Value |
|---|---|
| Document | Volume 2, Book E of 10 |
| Covers | Timetable & Scheduling · School-Based Projects & Legacy CALA · Examinations Administration |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A–D complete.** This book closes the two interfaces Book D defined but did not implement. |
| Next book | Book F — Boarding & Welfare (`BRD-01` → `BRD-05`) |

---

## Part 0 — The Two Open Interfaces

Book D shipped with two deliberate stubs. This book fills them.

**Stub 1 — attendance sessions.** `BR-ACA-04-002` states that sessions generate from the timetable, "or from a daily schedule where no timetable exists". `ACA-03` supplies the timetable and the session generator.

**Stub 2 — the SBP outcome.** `BR-ACA-05-021` states that where the active framework's `continuous_assessment_model = sbp`, the outcome from `ACA-06` populates `continuous_percent` on `term_subject_results`. `ACA-06` produces it.

### 0.1 Build order

```
ACA-03  Timetable & Scheduling     ← generates sessions; needed before ACA-07 seating
   ↓
ACA-06  School-Based Projects      ← feeds ACA-05 continuous assessment
   ↓
ACA-07  Examinations Administration
```

`ACA-06` and `ACA-07` have no dependency on each other and can run in parallel with two developers.

### 0.2 A boundary worth stating clearly

**`ACA-07` is internal examination administration.** Sessions, seating, invigilation, scripts, marking, moderation, special arrangements, malpractice.

**`CMP-01` is the ZIMSEC interface.** Candidate registration against the national Online Candidate Registration System, bio-data validation, subject entry export, entry-fee reconciliation, statements of entry, results import.

They are separate modules in separate books because a school runs internal mocks under `ACA-07` whether or not it ever touches ZIMSEC, and because the national systems change on their own schedule. `ACA-07` supplies `CMP-01` with the candidate set; `CMP-01` owns everything that crosses the school boundary.

---

# ACA-03 · Timetable & Scheduling Engine

> The module with the hardest algorithm and the most opinionated users. Every deputy head has built a timetable by hand on a wall chart and will judge yours against it.

### 1. Scope

**In scope.** Period structure and cycle configuration, venues, constraint definition, automated generation, clash detection at four levels, manual editing, substitution and cover, per-learner timetables, attendance session generation, the ZIMSEC examination slot planner.

**Out of scope.** Class and subject enrolment (`ACA-02`). Teacher allocation (`PPL-04`). Facility booking for non-teaching use (`OPS-05`).

### 2. Data model

```sql
period_structures                     -- the shape of a school day
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
section_id              BIGINT       NULL FK    -- primary and secondary differ
academic_year_id        BIGINT       FK
name                    VARCHAR(120) NOT NULL   -- 'Secondary Day 2026'
cycle_type              VARCHAR(20)  NOT NULL   -- weekly|two_week|six_day|ten_day
cycle_days              TINYINT      NOT NULL DEFAULT 5
day_labels              JSON         NOT NULL   -- ['Mon'..'Fri'] or ['Day 1'..'Day 6']
is_default              TINYINT(1)   NOT NULL DEFAULT 0
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, academic_year_id, section_id, name)

period_slots                          -- named time slots within a cycle day
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
structure_id            BIGINT       FK INDEX
cycle_day               TINYINT      NOT NULL   -- 1..cycle_days
period_number           SMALLINT     NOT NULL
label                   VARCHAR(40)  NOT NULL   -- 'Period 1','Break','Assembly','Prep'
slot_type               VARCHAR(20)  NOT NULL   -- teaching|break|assembly|registration|
                                                -- prep|games|chapel|activity
starts_at               TIME         NOT NULL
ends_at                 TIME         NOT NULL
duration_minutes        SMALLINT     NOT NULL
is_teachable            TINYINT(1)   NOT NULL DEFAULT 1
requires_attendance     TINYINT(1)   NOT NULL DEFAULT 1
sort_order              SMALLINT
  UNIQUE (structure_id, cycle_day, period_number)
  INDEX  (school_id, structure_id, cycle_day)

venues                                -- rooms, labs, workshops, fields
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL   -- 'LAB1','R12','WKSHP','HALL'
name                    VARCHAR(120) NOT NULL
venue_type              VARCHAR(30)  NOT NULL   -- classroom|laboratory|workshop|
                                                -- computer_lab|hall|field|library|
                                                -- music_room|art_room
building                VARCHAR(80)  NULL
floor                   VARCHAR(20)  NULL
capacity                SMALLINT     NOT NULL
exam_capacity           SMALLINT     NULL       -- spaced seating, lower than capacity
facilities              JSON         NULL       -- ['projector','gas','fume_hood','wifi']
is_bookable_externally  TINYINT(1)   NOT NULL DEFAULT 0   -- OPS-05
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

timetables                            -- a versioned published schedule
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
structure_id            BIGINT       FK
name                    VARCHAR(120) NOT NULL
version                 SMALLINT     NOT NULL DEFAULT 1
status                  VARCHAR(20)  NOT NULL   -- draft|generating|generated|
                                                -- review|published|superseded
effective_from          DATE         NULL
effective_to            DATE         NULL
generation_run_id       BIGINT       NULL FK
hard_violations         SMALLINT     NOT NULL DEFAULT 0
soft_violations         SMALLINT     NOT NULL DEFAULT 0
quality_score           DECIMAL(5,2) NULL
published_by            BIGINT       NULL FK
published_at            TIMESTAMP    NULL
created_by, created_at, updated_at
  INDEX (school_id, term_id, status)

timetable_slots                       -- one lesson in one slot
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
timetable_id            BIGINT       FK INDEX
term_id                 BIGINT       FK
period_slot_id          BIGINT       FK INDEX
cycle_day               TINYINT      NOT NULL   -- denormalised
period_number           SMALLINT     NOT NULL   -- denormalised
subject_id              BIGINT       FK INDEX
class_id                BIGINT       NULL FK    -- whole-class teaching
teaching_group_id       BIGINT       NULL FK    -- set teaching (ACA-02)
staff_id                BIGINT       FK INDEX
co_staff_id             BIGINT       NULL FK    -- practicals, team teaching
venue_id                BIGINT       NULL FK INDEX
is_double               TINYINT(1)   NOT NULL DEFAULT 0
double_partner_slot_id  BIGINT       NULL FK
is_locked               TINYINT(1)   NOT NULL DEFAULT 0   -- pinned; generator won't move
notes                   VARCHAR(255) NULL
  INDEX (school_id, timetable_id, cycle_day, period_number)
  INDEX (school_id, timetable_id, staff_id, cycle_day)
  INDEX (school_id, timetable_id, venue_id, cycle_day)

timetable_constraints                 -- ⭐ hard and soft rules for generation
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
constraint_type         VARCHAR(40)  NOT NULL
                        -- teacher_unavailable | venue_unavailable |
                        -- subject_requires_venue_type | subject_max_per_day |
                        -- subject_not_after_period | subject_prefers_morning |
                        -- subject_requires_double | teacher_max_consecutive |
                        -- teacher_max_per_day | teacher_no_first_period |
                        -- class_no_free_periods | games_afternoon |
                        -- subject_spread_across_week | consecutive_same_subject_forbidden
severity                VARCHAR(10)  NOT NULL   -- hard | soft
weight                  SMALLINT     NOT NULL DEFAULT 1   -- soft constraint cost
subject_id              BIGINT       NULL FK
staff_id                BIGINT       NULL FK
class_id                BIGINT       NULL FK
venue_id                BIGINT       NULL FK
grade_level_id          BIGINT       NULL FK
cycle_days              JSON         NULL       -- [1,3,5]
period_numbers          JSON         NULL       -- [7,8]
value                   SMALLINT     NULL       -- max count etc.
reason                  VARCHAR(255) NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, academic_year_id, constraint_type, is_active)

timetable_generation_runs
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
timetable_id            BIGINT       FK
algorithm               VARCHAR(30)  NOT NULL   -- greedy|annealing|tabu|hybrid
parameters              JSON         NULL
status                  VARCHAR(20)  NOT NULL   -- queued|running|completed|failed|cancelled
iterations              INT          NOT NULL DEFAULT 0
best_score              DECIMAL(10,2) NULL
hard_violations         SMALLINT     NULL
soft_violation_detail   JSON         NULL
unplaced_requirements   JSON         NULL       -- what could NOT be scheduled ⭐
duration_seconds        INT          NULL
started_at, completed_at
requested_by            BIGINT       FK → users.id

lesson_substitutions                  -- cover
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
timetable_slot_id       BIGINT       FK INDEX
substitution_date       DATE         NOT NULL
absent_staff_id         BIGINT       FK
cover_staff_id          BIGINT       NULL FK    -- null = uncovered
reason                  VARCHAR(60)  NOT NULL   -- leave|sick|duty|training|
                                                -- fixture|meeting
leave_request_id        BIGINT       NULL FK    -- PPL-04
venue_id                BIGINT       NULL FK    -- room change
status                  VARCHAR(20)  NOT NULL   -- pending|assigned|uncovered|
                                                -- cancelled|completed
work_set                TEXT         NULL       -- what the class should do
notified_at             TIMESTAMP    NULL
assigned_by             BIGINT       NULL FK
  UNIQUE (school_id, timetable_slot_id, substitution_date)
  INDEX  (school_id, substitution_date, status)
  INDEX  (school_id, cover_staff_id, substitution_date)

timetable_exceptions                  -- one-off changes to the pattern
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
exception_date          DATE         NOT NULL
exception_type          VARCHAR(30)  NOT NULL   -- no_lessons|special_timetable|
                                                -- exam_timetable|half_day|
                                                -- assembly_extended|sports_day
alternative_structure_id BIGINT      NULL FK
affected_scope          VARCHAR(20)  NOT NULL   -- whole_school|section|level|class
scope_id                BIGINT       NULL
reason                  VARCHAR(255) NOT NULL
suppresses_attendance   TINYINT(1)   NOT NULL DEFAULT 0
created_by, created_at
  UNIQUE (school_id, exception_date, affected_scope, scope_id)

exam_slot_plans                       -- 🇿🇼 ZIMSEC disruption planner
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
name                    VARCHAR(150) NOT NULL   -- 'ZIMSEC Nov 2026 O-Level'
exam_body               VARCHAR(40)  NOT NULL   -- zimsec|cambridge|internal
starts_on               DATE         NOT NULL
ends_on                 DATE         NOT NULL
affected_levels         JSON         NOT NULL   -- grade_level_ids sitting the exams
venues_reserved         JSON         NULL       -- venue_ids withdrawn from teaching
staff_reserved          JSON         NULL       -- invigilators withdrawn
disruption_report       JSON         NULL       -- which lessons are lost, per level ⭐
status                  VARCHAR(20)  NOT NULL   -- draft|approved|active|completed
created_by, created_at
```

### 3. ⭐ The four levels of clash detection

Three of these are obvious. The fourth is the one that breaks hand-built timetables.

| Level | Constraint | Why it matters |
|---|---|---|
| **Teacher** | One teacher, one slot | Obvious |
| **Venue** | One venue, one slot | Obvious |
| **Class** | One class, one slot | Obvious for whole-class teaching |
| **⭐ Learner** | One *learner*, one slot | **The hard one.** With A-Level option blocks and setted teaching, individual learners have unique timetables. Two teaching groups in different subjects can each be clash-free at class level while sharing seventeen learners. |

Learner-level detection is why the timetable module cannot be built before `ACA-02`. It resolves each slot to its actual learner set — the class roll, or the teaching group roll — and checks for intersections.

```php
public function detectLearnerClashes(Timetable $tt): Collection
{
    $clashes = collect();

    foreach ($tt->slots->groupBy(fn ($s) => "{$s->cycle_day}:{$s->period_number}") as $key => $slots) {
        if ($slots->count() < 2) continue;

        // Resolve every concurrent slot to its actual learner IDs
        $rolls = $slots->mapWithKeys(fn ($s) => [$s->id => $this->learnerIdsFor($s)]);

        foreach ($rolls as $slotId => $learners) {
            foreach ($rolls as $otherId => $otherLearners) {
                if ($slotId >= $otherId) continue;
                $shared = array_intersect($learners, $otherLearners);
                if ($shared !== []) {
                    $clashes->push(new LearnerClash($key, $slotId, $otherId, $shared));
                }
            }
        }
    }

    return $clashes;
}
```

This runs on every manual edit, synchronously, and blocks the save. A deputy head dragging a lesson must be told immediately that it would strand fourteen A-Level learners, not discover it in week two.

### 4. ⭐ The generation algorithm, honestly described

Timetabling is NP-hard. There is no algorithm that produces a provably optimal schedule for a real school in reasonable time. What works in practice is a two-phase approach that gets close and then improves.

**Phase 1 — Greedy construction with backtracking.**

```
1  Build the requirement list from ACA-02 + PPL-04:
     for each (teaching_group | class, subject) → periods_per_week, teacher, venue needs
2  Sort requirements by difficulty descending:
     - fewest feasible slots first (double periods, lab-bound subjects, part-time teachers)
     - most constrained resources first
3  For each requirement, place into the least-constraining feasible slot
     (minimise the reduction in remaining options for other requirements)
4  On a dead end, backtrack up to N levels; beyond N, record as UNPLACED and continue
5  Output: a schedule with zero hard violations, or an explicit unplaced list
```

**Phase 2 — Simulated annealing on soft constraints.**

```
6  Score the schedule: Σ(soft constraint weight × violation count)
7  Repeat until the time budget expires:
     propose a move — swap two slots, relocate one lesson, swap two teachers
     reject immediately if it creates a hard violation
     accept if it improves the score
     accept a worsening move with probability e^(−Δ/T); cool T
8  Keep the best-scoring schedule found
```

| # | Non-negotiable behaviours |
|---|---|
| Hard constraints are **never** violated in output. If something cannot be placed, it is reported as unplaced, not force-fitted. |
| `unplaced_requirements` names exactly what could not be scheduled and which constraint blocked it. **A generator that silently drops a class's Mathematics is worse than one that fails.** |
| Generation runs as a queued job with a configurable time budget (default 5 minutes), reports live progress, and is cancellable. |
| Locked slots (`is_locked = 1`) are treated as fixed. A deputy head who has hand-placed the Head's teaching period keeps it. |
| Every run is repeatable from its recorded seed and parameters. |

### 5. Attendance session generation ⭐

The interface `ACA-04` was written against.

```
On timetable publication, and nightly for the coming N days:

FOR EACH date in the generation window:
  1  Skip if the date is a holiday or falls outside a term (CORE-03)
  2  Resolve the cycle day for this date from the structure's cycle pattern
  3  Check timetable_exceptions:
       no_lessons          → generate nothing
       special_timetable   → use the alternative structure
       exam_timetable      → use the exam slot plan
  4  FOR EACH published timetable_slot on that cycle day:
       skip if period_slot.requires_attendance = 0
       create attendance_session (mode = period)
         class_id / teaching_group_id / subject_id / staff_id / timetable_slot_id
         expected_count ← resolved learner roll for that slot
  5  Generate the daily registration session where the section marks daily
  6  Idempotent: re-running never duplicates a session
```

| # | Rule |
|---|---|
| Sessions are generated `attendance.session_generation_days_ahead` (default 7) in advance, so a teacher's mobile app can cache a week of registers for offline marking. |
| Republishing a timetable regenerates future sessions only. **Past sessions with marks are never touched.** |
| A substitution updates the session's `staff_id` so the covering teacher sees the register in their app. |

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-03-001` | Period structures are per section and per academic year. Primary and secondary run different day shapes and must not share one. |
| `BR-ACA-03-002` | Cycle types support weekly (5), six-day, two-week, and ten-day rotations. The cycle day for a calendar date is derived from the term start and the holiday calendar, skipping non-teaching days. |
| `BR-ACA-03-003` | Slots with `is_teachable = 0` (break, assembly, chapel, games) cannot carry a lesson. |
| `BR-ACA-03-004` | Hard constraints are inviolable in generated output. Soft constraints carry weights and are minimised. |
| `BR-ACA-03-005` | Generation reports every unplaced requirement with the blocking constraint named. Silent omission is a defect. |
| `BR-ACA-03-006` | Clash detection covers teacher, venue, class, and **learner**. All four run on generation and on every manual edit. |
| `BR-ACA-03-007` | Manual edits creating a hard clash are refused with the specific conflict named — the teacher, the venue, or the affected learners listed by name. |
| `BR-ACA-03-008` | Manual edits creating a soft violation are permitted with a warning showing the score change. |
| `BR-ACA-03-009` | Double periods occupy consecutive teachable slots on the same day, are linked bidirectionally, and move together. |
| `BR-ACA-03-010` | Subjects requiring a venue type (laboratory, workshop, computer lab) are only placed in matching venues. This is a hard constraint. |
| `BR-ACA-03-011` | Venue capacity must meet or exceed the learner roll for the slot. Overflow is a hard violation. |
| `BR-ACA-03-012` | A teacher's slots must not exceed their `max_weekly_periods` from `PPL-04`, nor their configured maximum consecutive periods. |
| `BR-ACA-03-013` | Publishing supersedes the prior version. Both remain retrievable, and the effective dates determine which applies to a given date. |
| `BR-ACA-03-014` | Publication triggers session generation for future dates only and notifies affected teachers and learners of any change to their schedule. |
| `BR-ACA-03-015` | A timetable cannot be published while any hard violation exists. |
| `BR-ACA-03-016` | Approved leave in `PPL-04` automatically creates pending substitutions for every affected slot on the affected dates. |
| `BR-ACA-03-017` | Cover assignment prefers, in order: a teacher free that period who teaches the subject; a free teacher in the same department; any free teacher; the deputy head. Fairness balancing prevents the same person covering repeatedly. |
| `BR-ACA-03-018` | An uncovered lesson appears on the deputy head's daily cover report before the school day begins, not after. |
| `BR-ACA-03-019` | The covering teacher is notified with the class, venue, subject, and the work set, on their preferred channel. |
| `BR-ACA-03-020` | 🇿🇼 The exam slot planner reserves venues and staff for a public examination period and produces a **disruption report** listing, per level and subject, exactly how many teaching periods are lost. |
| `BR-ACA-03-021` | Timetable exceptions with `suppresses_attendance = 1` prevent session generation for that date and scope. |
| `BR-ACA-03-022` | Every learner, teacher, class, venue, and department has a printable and exportable timetable view. |

### 7. Screens

| Screen | Component | Permission |
|---|---|---|
| Period structures | `Academic\Timetable\Structures` | `timetable.manage` — visual day builder with times and slot types |
| Venues | `Academic\Timetable\Venues` | `timetable.manage` — capacity, exam capacity, facilities |
| Constraints | `Academic\Timetable\Constraints` | `timetable.manage` — grouped by type, hard/soft toggle, weight sliders |
| Requirements review | `Academic\Timetable\Requirements` | `timetable.manage` — what must be scheduled, from `ACA-02` + `PPL-04`, with feasibility warnings **before** generation |
| **Generation** | `Academic\Timetable\Generate` | `timetable.generate` — parameters, time budget, live progress, score curve, cancel |
| **Grid editor** | `Academic\Timetable\Editor` | `timetable.edit` ⭐ — drag and drop, live clash panel, four-level detection, undo, lock slots |
| Clash inspector | `Academic\Timetable\Clashes` | `timetable.view` — every violation with drill-through to the affected people |
| Views | `Academic\Timetable\Views` | `timetable.view` — by class, teacher, venue, learner, department; printable |
| Publish | `Academic\Timetable\Publish` | `timetable.publish` ⚠ — blocked while hard violations exist |
| **Daily cover** | `Academic\Timetable\Cover` | `timetable.cover.manage` — today's absences, suggested cover, one-click assign, uncovered highlighted |
| Exceptions | `Academic\Timetable\Exceptions` | `timetable.manage` |
| Exam slot planner | `Academic\Timetable\ExamPlanner` | `timetable.manage` — reserve, view disruption report |

**The grid editor's clash panel must update as the lesson is dragged, not on drop.** A deputy head needs to see "moving this here strands 14 learners" while the tile is still in their hand.

### 8. API endpoints

```
GET /api/v1/timetable/me                    ?date=&week=   teacher or learner
GET /api/v1/timetable/classes/{ulid}
GET /api/v1/timetable/today                 ?staff=        with substitutions applied
GET /api/v1/timetable/venues/{ulid}
GET /api/v1/timetable/cover/today           deputy head
POST /api/v1/timetable/substitutions/{ulid}/accept
GET /api/v1/timetable/exceptions            ?from=&to=
```

`GET /timetable/today` returns the schedule **with substitutions already applied**, so a covering teacher's app shows the class they are actually teaching, not the one they normally would.

### 9. Permissions · Settings · Events

```
timetable.view              timetable.manage
timetable.generate          timetable.edit
timetable.publish ⚠         timetable.cover.manage
timetable.export
```

| Setting | Type | Default |
|---|---|---|
| `timetable.default_cycle_type` | enum | `weekly` |
| `timetable.generation_time_budget_seconds` | int | `300` |
| `timetable.max_consecutive_periods` | int | `3` |
| `timetable.allow_soft_violations_on_publish` | bool | `true` |
| `timetable.session_generation_days_ahead` | int | `7` |
| `timetable.cover_fairness_balancing` | bool | `true` |
| `timetable.notify_on_schedule_change` | bool | `true` |
| `timetable.games_afternoon_days` | array | `[3]` |

Events: `TimetableGenerated` · `TimetablePublished` · `TimetableSuperseded` · `SubstitutionRequired` · `SubstitutionAssigned` · `LessonUncovered` ⚠ · `AttendanceSessionsGenerated` · `ExamSlotPlanActivated`

### 10. Acceptance criteria

```gherkin
AC-ACA-03-001
  Given two A-Level teaching groups in different subjects share 17 learners
  When both are placed in the same period
  Then a learner-level clash is reported naming all 17
  And the placement is refused

AC-ACA-03-002
  Given generation cannot place Form 2 Mathematics without violating a hard constraint
  Then the run completes
  And unplaced_requirements names Form 2 Mathematics and the blocking constraint
  And no lesson is silently dropped

AC-ACA-03-003
  Given a timetable has one hard violation
  When publication is attempted
  Then it is refused with the violation named

AC-ACA-03-004
  Given a lesson is pinned with is_locked = 1
  When generation runs
  Then that lesson remains in its slot

AC-ACA-03-005
  Given a timetable is published
  Then attendance sessions are generated for the next 7 days
  And re-publishing does not duplicate them
  And past sessions carrying marks are untouched

AC-ACA-03-006
  Given a teacher's leave is approved in PPL-04
  Then pending substitutions are created for every affected slot
  And uncovered lessons appear on the deputy head's cover report before school starts

AC-ACA-03-007
  Given a substitution is assigned
  Then the attendance session's staff_id updates
  And the covering teacher sees the register in their app
  And they are notified with class, venue, subject, and work set

AC-ACA-03-008
  Given a subject requires a laboratory
  When generation runs
  Then it is only placed in venues of type laboratory

AC-ACA-03-009
  Given a ZIMSEC exam period reserves the hall and four invigilators
  Then the disruption report states, per level and subject, how many periods are lost

AC-ACA-03-010
  Given a timetable exception of type no_lessons on a date
  Then no attendance sessions are generated for that date
```

---

# ACA-06 · School-Based Projects & Legacy CALA 🇿🇼

> Implements the continuous-assessment interface `ACA-05` consumes. Built to survive further Ministry revision, because this component of the Zimbabwean curriculum has already changed once and may change again.

### 1. Scope

**In scope.** Project brief creation and distribution, milestone tracking, learner evidence submission, rubric marking, moderation and internal verification, HOD sign-off, portfolio compilation, national submission export, the outcome feed to `ACA-05`, legacy CALA read-only preservation.

**Out of scope.** Assessment aggregation (`ACA-05`). Public examination coursework submitted to ZIMSEC (`CMP-01`).

### 2. 🇿🇼 The regulatory position

**School-Based Projects replaced Continuous Assessment Learning Activities from May 2024**, at all levels from ECD A upwards, at **one project per learning area per year**, with implementation guidance in **Circular No. 9 of 2024**. In the transition year, examination classes — Grade 7, Form 4, Form 6 — continued under the previous curriculum's assessment arrangements while non-examination classes moved to SBPs.

**Design consequence.** The module is built around an abstract *continuous assessment instrument* with SBP as the current concrete implementation and CALA preserved read-only. The active model is a property of the curriculum framework (`ACA-01.continuous_assessment_model`), so a school running a 2023 archive and a 2026 live term sees each rendered under its own rules, and a future Ministry revision is a new instrument type rather than a rewrite.

### 3. Data model

```sql
assessment_instruments                -- the abstraction ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
framework_id            BIGINT       FK
code                    VARCHAR(20)  NOT NULL   -- 'SBP','CALA'
name                    VARCHAR(120) NOT NULL
projects_per_subject_per_year TINYINT NOT NULL DEFAULT 1   -- 🇿🇼 SBP = 1
applies_to_exam_classes TINYINT(1)   NOT NULL DEFAULT 1
contributes_to_final_mark TINYINT(1) NOT NULL DEFAULT 1
default_weight_percent  DECIMAL(5,2) NULL
is_readonly             TINYINT(1)   NOT NULL DEFAULT 0   -- CALA = 1
reference_circular      VARCHAR(120) NULL       -- 'Circular No. 9 of 2024'
status                  VARCHAR(20)  NOT NULL   -- active|superseded|archived
  UNIQUE (school_id, framework_id, code)

project_briefs                        -- the task set to learners
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK INDEX
instrument_id           BIGINT       FK
subject_id              BIGINT       FK INDEX
grade_level_id          BIGINT       FK
title                   VARCHAR(200) NOT NULL
description             TEXT         NOT NULL
learning_objectives     JSON         NULL       -- syllabus objective references
heritage_link           TEXT         NULL       -- 🇿🇼 HBC heritage connection
deliverables            JSON         NOT NULL   -- what the learner must produce
resources               JSON         NULL
starts_on               DATE         NOT NULL
due_on                  DATE         NOT NULL
max_mark                DECIMAL(6,2) NOT NULL
rubric_id               BIGINT       FK
brief_document_id       BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- draft|approved|issued|closed|archived
approved_by             BIGINT       NULL FK    -- HOD approves before issue
issued_at               TIMESTAMP    NULL
created_by, created_at, updated_at
  UNIQUE (school_id, academic_year_id, subject_id, grade_level_id, instrument_id)
  INDEX  (school_id, academic_year_id, status)

project_rubrics
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
name                    VARCHAR(150) NOT NULL
subject_id              BIGINT       NULL FK    -- null = generic
total_mark              DECIMAL(6,2) NOT NULL
is_template             TINYINT(1)   NOT NULL DEFAULT 0
is_active               TINYINT(1)   NOT NULL DEFAULT 1

rubric_criteria
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
rubric_id               BIGINT       FK INDEX
criterion               VARCHAR(200) NOT NULL   -- 'Research and investigation'
description             TEXT         NULL
max_mark                DECIMAL(6,2) NOT NULL
weight_percent          DECIMAL(5,2) NOT NULL
performance_levels      JSON         NOT NULL
                        -- [{level:'Excellent', min:80, max:100, descriptor:'...'}]
sort_order              SMALLINT

project_milestones                    -- staged submission
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
brief_id                BIGINT       FK INDEX
sequence                TINYINT      NOT NULL
title                   VARCHAR(150) NOT NULL   -- 'Proposal','Data collection','Report'
description             TEXT         NULL
due_on                  DATE         NOT NULL
weight_percent          DECIMAL(5,2) NOT NULL DEFAULT 0
requires_evidence       TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (brief_id, sequence)

learner_projects                      -- one per learner per brief
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK INDEX
brief_id                BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
subject_id              BIGINT       FK          -- denormalised for ACA-05 lookup
project_title           VARCHAR(255) NULL        -- learner's own topic
status                  VARCHAR(20)  NOT NULL    -- not_started|in_progress|submitted|
                                                 -- marked|moderated|verified|
                                                 -- returned|incomplete|exempt
raw_mark                DECIMAL(6,2) NULL
percent                 DECIMAL(5,2) NULL
grade                   VARCHAR(10)  NULL
outcome                 VARCHAR(30)  NULL        -- ⭐ fed to ACA-05
criterion_marks         JSON         NULL        -- {criterion_id: mark}
marker_staff_id         BIGINT       NULL FK
marked_at               TIMESTAMP    NULL
marker_comment          TEXT         NULL
moderator_staff_id      BIGINT       NULL FK
moderated_at            TIMESTAMP    NULL
moderated_mark          DECIMAL(6,2) NULL
moderation_note         TEXT         NULL
verified_by             BIGINT       NULL FK     -- HOD sign-off
verified_at             TIMESTAMP    NULL
exemption_reason        VARCHAR(255) NULL
version                 SMALLINT     NOT NULL DEFAULT 1
submitted_at            TIMESTAMP    NULL
created_at, updated_at
  UNIQUE (school_id, brief_id, student_id)
  INDEX  (school_id, academic_year_id, student_id, subject_id)
  INDEX  (school_id, brief_id, status)

learner_project_milestones
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
learner_project_id      BIGINT       FK INDEX
milestone_id            BIGINT       FK
status                  VARCHAR(20)  NOT NULL    -- pending|submitted|late|
                                                 -- accepted|returned|missed
submitted_at            TIMESTAMP    NULL
mark                    DECIMAL(6,2) NULL
feedback                TEXT         NULL
reviewed_by             BIGINT       NULL FK
  UNIQUE (learner_project_id, milestone_id)

project_evidence
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
learner_project_id      BIGINT       FK INDEX
milestone_id            BIGINT       NULL FK
evidence_type           VARCHAR(30)  NOT NULL    -- document|photograph|video|audio|
                                                 -- artefact_photo|spreadsheet|link
file_id                 BIGINT       NULL FK
external_url            VARCHAR(500) NULL
caption                 VARCHAR(255) NULL
uploaded_by             BIGINT       FK → users.id
uploaded_at             TIMESTAMP    NOT NULL
is_final_submission     TINYINT(1)   NOT NULL DEFAULT 0
  INDEX (school_id, learner_project_id)

project_mark_versions                 -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
learner_project_id      BIGINT       FK INDEX
version                 SMALLINT     NOT NULL
raw_mark                DECIMAL(6,2) NULL
criterion_marks         JSON         NULL
stage                   VARCHAR(20)  NOT NULL    -- marked|moderated|verified|amended
change_reason           VARCHAR(255) NULL
changed_by              BIGINT       FK → users.id
changed_at              TIMESTAMP    NOT NULL
  UNIQUE (learner_project_id, version)

legacy_cala_records                   -- READ-ONLY archive
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
student_id              BIGINT       FK INDEX
subject_id              BIGINT       FK
cala_number             TINYINT      NOT NULL    -- 1..5 under the old model
title                   VARCHAR(200) NULL
raw_mark                DECIMAL(6,2) NULL
max_mark                DECIMAL(6,2) NULL
percent                 DECIMAL(5,2) NULL
recorded_at             TIMESTAMP    NULL
source                  VARCHAR(30)  NOT NULL    -- imported|native
  UNIQUE (school_id, academic_year_id, student_id, subject_id, cala_number)
  -- DB grants: SELECT only after migration. No INSERT, UPDATE, or DELETE.
```

### 4. ⭐ The continuous assessment interface

What `ACA-05` calls, and the only sanctioned route to a continuous assessment figure.

```php
interface ContinuousAssessmentProvider
{
    /** The percentage contribution for a learner, subject and academic year. */
    public function outcomeFor(
        Student $student,
        Subject $subject,
        AcademicYear $year,
    ): ?ContinuousAssessmentOutcome;

    /** Which instrument applies — drives report card labelling. */
    public function activeInstrument(AcademicYear $year): AssessmentInstrument;

    /** Learners with no verified outcome, for the finalisation gate. */
    public function outstandingFor(Term $term): Collection;
}

final readonly class ContinuousAssessmentOutcome
{
    public function __construct(
        public string  $instrumentCode,   // 'SBP' | 'CALA'
        public ?float  $percent,
        public ?string $grade,
        public string  $status,           // verified | marked | incomplete | exempt
        public ?string $projectTitle,
        public bool    $isVerified,
    ) {}
}
```

**Resolution order.** The provider reads `ACA-01.framework.continuous_assessment_model` for the year in question. `sbp` reads `learner_projects`; `cala` reads `legacy_cala_records`; `none` returns null and `ACA-05` renders the subject without a continuous assessment column. A 2023 report card and a 2026 report card therefore each carry the correct instrument and the correct label, with no branching in the report template.

### 5. The project lifecycle

```
  HOD / SUBJECT TEACHER                    LEARNER                    MODERATION
  ─────────────────────                    ───────                    ──────────
  Create brief (draft)
  Attach rubric + milestones
        │
        ▼
  HOD approves ──────► Issue to level ────► learner_projects
                                            created for every
                                            enrolled learner
                                                  │
                                                  ▼
                                            Milestone 1 evidence
                                            (portal or mobile)
                                                  │
                                            Teacher feedback
                                                  │
                                            ... milestones ...
                                                  │
                                            Final submission
                                                  │
                                                  ▼
                                       Teacher marks against rubric
                                       (criterion by criterion)
                                                  │
                                                  ▼ ───────────────► Moderator samples
                                                                     per policy
                                                                          │
                                                                     Adjust or confirm
                                                                          │
                                                                          ▼
                                                                     HOD verifies
                                                                          │
                                                                          ▼
                                                          outcome available to ACA-05
                                                          and to the national export
```

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-06-001` | The active instrument comes from the curriculum framework for the year in question. A school running an archive year and a live year sees each under its own instrument. |
| `BR-ACA-06-002` | 🇿🇼 Under SBP, `projects_per_subject_per_year` defaults to 1 and a second brief for the same subject, level and year is refused unless the setting is changed with a recorded reason. |
| `BR-ACA-06-003` | Only subjects with `requires_sbp = 1` in `ACA-01` generate project requirements. |
| `BR-ACA-06-004` | Issuing a brief creates a `learner_project` for every learner with an active enrolment in that subject and level, at that moment. |
| `BR-ACA-06-005` | A learner enrolling in the subject after issue has their project created automatically, with a pro-rated deadline where configured. |
| `BR-ACA-06-006` | A learner dropping the subject has their project set to `exempt` with the reason recorded. It is never deleted. |
| `BR-ACA-06-007` | Rubric criterion weights must total 100%. Criterion marks must not exceed their criterion maximum. |
| `BR-ACA-06-008` | A brief requires HOD approval before it can be issued. A teacher cannot set a project unilaterally. |
| `BR-ACA-06-009` | Evidence submission is open until the milestone or final deadline. Late submission is permitted or blocked per setting, and lateness is always recorded. |
| `BR-ACA-06-010` | Evidence files go through `CORE-10` with virus scanning. Learners submit from the mobile app; photographs of physical artefacts are a first-class evidence type. |
| `BR-ACA-06-011` | Every mark change writes an append-only `project_mark_versions` row with its stage. |
| `BR-ACA-06-012` | Moderation samples per the school's policy — a percentage, a fixed count, or all boundary cases. A moderated mark supersedes the marker's mark and both remain visible. |
| `BR-ACA-06-013` | A project reaching `verified` is final. Amending it afterwards requires approval through `CORE-07` and regenerates any affected report card. |
| `BR-ACA-06-014` | Only `verified` outcomes contribute to the final subject mark. A `marked` but unverified project reports as outstanding. |
| `BR-ACA-06-015` | Term academic close is blocked while any project for that year is `submitted` or `marked` but not `verified`, unless explicitly waived with a reason. |
| `BR-ACA-06-016` | An `incomplete` project contributes zero to the continuous component unless the subject's configuration excludes it, in which case the examination component is reweighted. |
| `BR-ACA-06-017` | Portfolio compilation produces a per-learner, per-subject document containing the brief, every milestone, all evidence, the rubric breakdown, and the marker and moderator comments. |
| `BR-ACA-06-018` | The national submission export produces the format required by the Ministry for the active instrument, with a validation report run before export. The format is a configurable template, not code. |
| `BR-ACA-06-019` | `legacy_cala_records` is read-only after migration, enforced by database grants. CALA cannot be created, edited, or deleted through the application. |
| `BR-ACA-06-020` | Guardians see their child's project status, milestone progress, and — after publication — the outcome. They never see other learners' marks or the moderation trail. |

### 7. Screens

| Screen | Component | Permission |
|---|---|---|
| Instruments | `Academic\Projects\Instruments` | `curriculum.manage` — active model per framework |
| Brief library | `Academic\Projects\Briefs` | `projects.view` |
| Brief editor | `Academic\Projects\BriefEditor` | `projects.manage` — objectives, heritage link, deliverables, milestones |
| Rubric builder | `Academic\Projects\Rubrics` | `projects.manage` — criteria, weights with live total, performance level descriptors |
| Brief approval | `Academic\Projects\Approve` | `projects.approve` (HOD) |
| **Progress tracker** | `Academic\Projects\Tracker` | `projects.view` — brief × learner grid, milestone status at a glance, chase list |
| **Marking** | `Academic\Projects\Mark` | `projects.mark` — evidence viewer beside the rubric, criterion-by-criterion entry, running total |
| Moderation | `Academic\Projects\Moderate` | `projects.moderate` — sample set, marker vs moderator comparison, distribution chart |
| Verification | `Academic\Projects\Verify` | `projects.verify` (HOD) |
| Portfolio | `Academic\Projects\Portfolio` | `projects.view` — compile and export per learner |
| National export | `Academic\Projects\Export` | `projects.export` ⚠ — validation report first |
| CALA archive | `Academic\Projects\CalaArchive` | `projects.view` — read-only, clearly labelled as archived |

### 8. API endpoints

```
GET  /api/v1/projects/mine                    learner: my projects and milestones
GET  /api/v1/projects/{ulid}
POST /api/v1/projects/{ulid}/evidence         multipart or presigned
POST /api/v1/projects/{ulid}/milestones/{id}/submit
POST /api/v1/projects/{ulid}/submit           final submission

GET  /api/v1/projects/to-mark                 teacher: marking queue
POST /api/v1/projects/{ulid}/mark             { criterion_marks, comment }

GET  /api/v1/students/{ulid}/projects         guardian: status and outcome
```

Learner evidence submission from the mobile app is the primary interaction. A learner photographing a physical artefact in a rural school with intermittent connectivity must be able to queue the upload and have it arrive.

### 9. Permissions · Settings · Events

```
projects.view          projects.manage       projects.approve
projects.mark          projects.moderate     projects.verify
projects.amend_verified ⚠                    projects.export ⚠
```

| Setting | Type | Default |
|---|---|---|
| `projects.projects_per_subject_per_year` | int | `1` |
| `projects.allow_late_submission` | bool | `true` |
| `projects.late_penalty_percent` | int | `0` |
| `projects.moderation_sample_percent` | int | `20` |
| `projects.moderation_includes_boundaries` | bool | `true` |
| `projects.require_hod_verification` | bool | `true` |
| `projects.block_close_on_unverified` | bool | `true` |
| `projects.incomplete_counts_as_zero` | bool | `true` |
| `projects.evidence_max_files` | int | `20` |

Events: `BriefApproved` · `BriefIssued` · `MilestoneSubmitted` · `ProjectSubmitted` · `ProjectMarked` · `ProjectModerated` · `SbpOutcomeRecorded` ⭐ (→ `ACA-05`) · `ProjectOutstanding` · `PortfolioCompiled`

### 10. Acceptance criteria

```gherkin
AC-ACA-06-001
  Given the active framework uses SBP
  And a brief is issued for Form 2 Geography
  Then a learner_project exists for every learner enrolled in that subject and level

AC-ACA-06-002
  Given a brief already exists for Form 2 Geography this year
  When a second is created
  Then it is refused unless projects_per_subject_per_year is changed with a reason

AC-ACA-06-003
  Given a learner enrols in the subject after the brief was issued
  Then their project is created automatically

AC-ACA-06-004
  Given a learner drops the subject
  Then their project is set to exempt with the reason recorded
  And it is not deleted

AC-ACA-06-005
  Given a project is marked but not verified
  When ACA-05 requests the continuous assessment outcome
  Then the status is 'marked' and it does not contribute to the final mark
  And the learner appears on the outstanding list

AC-ACA-06-006
  Given unverified projects exist for the year
  When the academic period close is attempted
  Then it is blocked with the outstanding count
  Unless explicitly waived with a reason

AC-ACA-06-007
  Given a moderator adjusts a marker's mark
  Then both marks remain visible
  And an append-only version row records the moderation stage

AC-ACA-06-008
  Given a 2023 term under the CALA framework
  When its report card is rendered
  Then the CALA record is shown, labelled as CALA
  And a 2026 report card shows the SBP outcome labelled as SBP

AC-ACA-06-009
  Given the application database user
  When it attempts INSERT, UPDATE, or DELETE on legacy_cala_records
  Then the database refuses the statement

AC-ACA-06-010
  Given a learner submits evidence from the mobile app while offline
  When connectivity returns
  Then the evidence uploads and is attached to the correct milestone
```

---

# ACA-07 · Examinations Administration

### 1. Scope

**In scope.** Examination sessions, candidate entry, seating and index numbers, invigilation rostering, secure question paper repository with timed release, script tracking, marking and double marking, moderation, mark capture and aggregation, special arrangements, malpractice register, results processing and release control.

**Out of scope.** ZIMSEC candidate registration and results import (`CMP-01`). Term aggregation (`ACA-05`). Duty roster mechanics (`PPL-04`, reused here).

### 2. Data model

```sql
examination_sessions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
name                    VARCHAR(150) NOT NULL   -- 'End of Term 3 2026','Form 4 Mocks'
exam_type               VARCHAR(30)  NOT NULL   -- end_of_term|mid_term|mock|
                                                -- entrance|public|resit
exam_body               VARCHAR(30)  NOT NULL   -- internal|zimsec|cambridge
affected_levels         JSON         NOT NULL   -- grade_level_ids
starts_on               DATE         NOT NULL
ends_on                 DATE         NOT NULL
index_number_pattern    VARCHAR(80)  NULL       -- '{CENTRE}/{LEVEL}/{SEQ:4}'
exam_slot_plan_id       BIGINT       NULL FK    -- ACA-03 venue/staff reservation
status                  VARCHAR(20)  NOT NULL   -- planning|entries_open|entries_closed|
                                                -- scheduled|in_progress|marking|
                                                -- moderation|results_ready|
                                                -- published|archived
created_by, created_at, updated_at
  INDEX (school_id, term_id, status)

examination_papers
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
session_id              BIGINT       FK INDEX
subject_id              BIGINT       FK INDEX
grade_level_id          BIGINT       FK
paper_number            VARCHAR(10)  NOT NULL   -- '1','2','3','Practical'
paper_name              VARCHAR(120) NOT NULL
component_type          VARCHAR(20)  NOT NULL   -- theory|practical|oral|coursework
max_mark                DECIMAL(6,2) NOT NULL
weight_percent          DECIMAL(5,2) NOT NULL   -- within the subject result
duration_minutes        SMALLINT     NOT NULL
scheduled_date          DATE         NULL
scheduled_start         TIME         NULL
requires_special_venue  VARCHAR(30)  NULL       -- laboratory|computer_lab
paper_file_id           BIGINT       NULL FK    -- ENCRYPTED at rest
marking_scheme_file_id  BIGINT       NULL FK    -- ENCRYPTED
release_at              TIMESTAMP    NULL       -- ⭐ time-locked
released_at             TIMESTAMP    NULL
released_by             BIGINT       NULL FK
setter_staff_id         BIGINT       NULL FK
vetted_by               BIGINT       NULL FK
vetted_at               TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL   -- draft|set|vetted|sealed|released|sat
  UNIQUE (school_id, session_id, subject_id, grade_level_id, paper_number)
  INDEX  (school_id, session_id, scheduled_date)

examination_candidates
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
session_id              BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
index_number            VARCHAR(40)  NOT NULL
entry_status            VARCHAR(20)  NOT NULL   -- provisional|confirmed|withdrawn
entered_subjects        JSON         NOT NULL   -- subject_ids
entry_fee_minor         BIGINT       NULL       -- billed via FIN-02 ad hoc
entry_fee_currency      CHAR(3)      NULL
entry_invoiced          TINYINT(1)   NOT NULL DEFAULT 0
statement_of_entry_doc_id BIGINT     NULL FK
confirmed_by            BIGINT       NULL FK
confirmed_at            TIMESTAMP    NULL
  UNIQUE (school_id, session_id, student_id)
  UNIQUE (school_id, session_id, index_number)
  INDEX  (school_id, session_id, entry_status)

examination_seatings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
paper_id                BIGINT       FK INDEX
candidate_id            BIGINT       FK INDEX
venue_id                BIGINT       FK
row_number              SMALLINT     NULL
seat_number             VARCHAR(20)  NOT NULL
attended                TINYINT(1)   NULL
arrival_time            TIME         NULL
notes                   VARCHAR(255) NULL
  UNIQUE (paper_id, candidate_id)
  UNIQUE (paper_id, venue_id, seat_number)
  INDEX  (school_id, paper_id, venue_id)

invigilation_assignments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
paper_id                BIGINT       FK INDEX
venue_id                BIGINT       FK
staff_id                BIGINT       FK INDEX
role                    VARCHAR(20)  NOT NULL   -- chief|assistant|relief|runner
duty_assignment_id      BIGINT       NULL FK    -- PPL-04 roster linkage
confirmed               TINYINT(1)   NOT NULL DEFAULT 0
attended                TINYINT(1)   NULL
report_submitted        TINYINT(1)   NOT NULL DEFAULT 0
report_notes            TEXT         NULL
  UNIQUE (paper_id, venue_id, staff_id)

script_batches                        -- chain of custody ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
paper_id                BIGINT       FK INDEX
batch_reference         VARCHAR(60)  NOT NULL
venue_id                BIGINT       NULL FK
script_count            SMALLINT     NOT NULL
expected_count          SMALLINT     NOT NULL
status                  VARCHAR(20)  NOT NULL   -- collected|in_transit|with_marker|
                                                -- marked|with_moderator|moderated|
                                                -- returned|archived|discrepancy
current_holder_staff_id BIGINT       NULL FK
  UNIQUE (school_id, batch_reference)

script_custody_log                    -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
batch_id                BIGINT       FK INDEX
from_staff_id           BIGINT       NULL FK
to_staff_id             BIGINT       NULL FK
action                  VARCHAR(30)  NOT NULL   -- collected|handed_over|received|
                                                -- returned|archived
script_count            SMALLINT     NOT NULL
discrepancy_note        TEXT         NULL
occurred_at             TIMESTAMP    NOT NULL
recorded_by             BIGINT       FK → users.id

examination_marks
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
paper_id                BIGINT       FK INDEX
candidate_id            BIGINT       FK INDEX
student_id              BIGINT       FK          -- denormalised
raw_mark                DECIMAL(6,2) NULL
percent                 DECIMAL(5,2) NULL
is_absent               TINYINT(1)   NOT NULL DEFAULT 0
first_marker_id         BIGINT       NULL FK
first_mark              DECIMAL(6,2) NULL
second_marker_id        BIGINT       NULL FK
second_mark             DECIMAL(6,2) NULL
mark_variance           DECIMAL(6,2) NULL       -- triggers third marking
final_marker_id         BIGINT       NULL FK
moderated_mark          DECIMAL(6,2) NULL
moderator_id            BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- pending|first_marked|
                                                -- second_marked|variance_review|
                                                -- moderated|final
version                 SMALLINT     NOT NULL DEFAULT 1
  UNIQUE (paper_id, candidate_id)
  INDEX  (school_id, paper_id, status)

special_arrangements
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
session_id              BIGINT       FK
student_id              BIGINT       FK INDEX
arrangement_type        VARCHAR(40)  NOT NULL   -- extra_time|separate_room|reader|
                                                -- scribe|large_print|rest_breaks|
                                                -- prompter|assistive_technology
extra_time_percent      SMALLINT     NULL       -- 25, 50
justification           TEXT         NOT NULL
supporting_document_id  BIGINT       NULL FK    -- medical or educational psychologist
approved_by             BIGINT       NULL FK
approved_at             TIMESTAMP    NULL
applies_to_papers       JSON         NULL       -- null = all
status                  VARCHAR(20)  NOT NULL   -- requested|approved|rejected|expired
  INDEX (school_id, session_id, student_id)

malpractice_incidents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
session_id              BIGINT       FK
paper_id                BIGINT       NULL FK
candidate_id            BIGINT       NULL FK
incident_type           VARCHAR(40)  NOT NULL   -- unauthorised_material|copying|
                                                -- impersonation|disruption|
                                                -- mobile_phone|leaving_early|
                                                -- paper_leak
description             TEXT         NOT NULL
evidence_file_ids       JSON         NULL
reported_by             BIGINT       FK → users.id
occurred_at             TIMESTAMP    NOT NULL
investigation_notes     TEXT         NULL
outcome                 VARCHAR(40)  NULL       -- no_case|warning|paper_annulled|
                                                -- session_annulled|referred_to_board
outcome_by              BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- reported|investigating|decided|closed
is_confidential         TINYINT(1)   NOT NULL DEFAULT 1
```

### 3. ⭐ Question paper security

A leaked paper destroys an examination series and a school's reputation. The controls are deliberately heavy.

| Control | Mechanism |
|---|---|
| **Encrypted at rest** | Paper and marking scheme files are encrypted with a per-session key, separate from general file storage encryption. |
| **Time-locked release** | `release_at` is enforced server-side. Before that moment, the download endpoint returns 403 for **everyone**, including the Head and the Super Admin. There is no override path. |
| **Setter separation** | The paper setter cannot be the vetter. Enforced. |
| **Vetting required** | A paper cannot reach `sealed` without a recorded vetting by a different staff member. |
| **Access logging** | Every view and download writes to `data_access_log` with the user, IP, device, and timestamp. |
| **Watermarking** | Downloaded papers carry a visible watermark naming the downloading user, so a leaked copy identifies its source. |
| **Download limits** | A configurable maximum downloads per user per paper; exceeding it raises a security event. |
| **Sealed status is one-way** | A sealed paper cannot return to draft. Corrections require a new version with an audit note. |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-07-001` | Index numbers are allocated per session per level from the configured pattern, gapless, and immutable once assigned. |
| `BR-ACA-07-002` | Candidate entry is derived from `ACA-02` subject enrolments, then confirmed by the exams officer. It is never typed from scratch. |
| `BR-ACA-07-003` | Entry fees raise an ad hoc charge through `FIN-02` per candidate per subject, and reconcile against the amount ultimately remitted. |
| `BR-ACA-07-004` | Paper component weights within a subject must total 100%. |
| `BR-ACA-07-005` | A paper cannot be scheduled without a venue whose `exam_capacity` meets the candidate count for that paper. |
| `BR-ACA-07-006` | Seating allocation separates candidates taking the same paper by the configured spacing and, where possible, avoids seating classmates adjacently. |
| `BR-ACA-07-007` | A candidate with an approved `separate_room` arrangement is seated in their own venue, and that venue is reserved and invigilated. |
| `BR-ACA-07-008` | Extra time is applied to the candidate's finish time on the attendance sheet and the invigilator's brief, computed from the paper duration. |
| `BR-ACA-07-009` | Special arrangements require documented justification and approval. They carry forward between sessions until they expire or are withdrawn. |
| `BR-ACA-07-010` | Invigilation assignment excludes any teacher of the subject being examined for that paper's venue, where staffing permits, and warns where it does not. |
| `BR-ACA-07-011` | Invigilation draws from and writes back to the `PPL-04` duty roster, so exam duty counts toward fairness balancing. |
| `BR-ACA-07-012` | Every script batch handover writes an append-only custody entry with both parties and the count. A count discrepancy sets the batch to `discrepancy` and alerts the exams officer immediately. |
| `BR-ACA-07-013` | Where double marking is configured, a variance exceeding `exams.double_marking_variance_threshold` routes to third marking. Neither marker sees the other's mark until both have submitted. |
| `BR-ACA-07-014` | Moderation samples per policy. A moderated mark supersedes; both remain visible. |
| `BR-ACA-07-015` | Absence is recorded explicitly. An absent candidate is not a zero unless configured, and the reason is captured. |
| `BR-ACA-07-016` | Paper marks aggregate to a subject result by component weight and flow into `ACA-05` as an `examination` category assessment. Exam marks are not a separate parallel result system. |
| `BR-ACA-07-017` | Results release is staged: `results_ready` (internal review) → `published` (visible to learners and guardians). Marking completion does not publish anything. |
| `BR-ACA-07-018` | A malpractice incident with outcome `paper_annulled` or `session_annulled` voids the affected marks, which remain visible with the annulment reason. |
| `BR-ACA-07-019` | Malpractice records are confidential, visible to the head, deputy, and exams officer only, with every access logged. |
| `BR-ACA-07-020` | Paper files are permanently retained per `CMP-03`, and the marking scheme is released to markers only after the paper has been sat. |
| `BR-ACA-07-021` | 🇿🇼 For sessions with `exam_body = zimsec`, the confirmed candidate set is exposed to `CMP-01` for national registration. `ACA-07` never talks to ZIMSEC directly. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Session setup | `Academic\Exams\Sessions` | `exams.manage` |
| Papers | `Academic\Exams\Papers` | `exams.manage` — components, weights with live total, duration |
| **Secure paper vault** | `Academic\Exams\PaperVault` | `exams.paper.manage` ⚠⚠ — upload, vet, seal, set release time; access log visible |
| Candidate entries | `Academic\Exams\Candidates` | `exams.manage` — derived from enrolments, confirm, index numbers, entry fees |
| **Seating plan** | `Academic\Exams\Seating` | `exams.manage` — auto-allocate with spacing, visual venue layout, printable |
| Invigilation | `Academic\Exams\Invigilation` | `exams.manage` — roster, subject-teacher exclusion warnings, confirmations |
| Attendance sheets | `Academic\Exams\AttendanceSheets` | `exams.manage` — printable per venue with special arrangements shown |
| **Script tracking** | `Academic\Exams\Scripts` | `exams.script.manage` — batches, scan handover, discrepancy alerts |
| Mark capture | `Academic\Exams\MarkEntry` | `exams.mark` — by paper, blind for double marking |
| Variance review | `Academic\Exams\Variance` | `exams.moderate` — first vs second, third-mark queue |
| Moderation | `Academic\Exams\Moderate` | `exams.moderate` — sample, distribution, adjustment |
| Special arrangements | `Academic\Exams\Arrangements` | `exams.arrangements.manage` |
| Malpractice | `Academic\Exams\Malpractice` | `exams.malpractice.manage` ⚠ |
| Results processing | `Academic\Exams\Results` | `exams.results.process` — aggregate, analyse, release |
| Analysis | `Academic\Exams\Analysis` | `exams.results.view` — distributions, subject comparison, year-on-year |

### 6. API endpoints

```
GET  /api/v1/exams/sessions                    ?term=
GET  /api/v1/exams/my-timetable                candidate: papers, dates, venues, seats
GET  /api/v1/exams/my-invigilation             staff: duties with venue and paper
POST /api/v1/exams/papers/{ulid}/download      ⭐ time-locked, watermarked, logged
POST /api/v1/exams/scripts/{ulid}/handover     scan-based custody transfer
GET  /api/v1/exams/marking-queue               marker's papers
POST /api/v1/exams/papers/{ulid}/marks         Idempotency-Key
GET  /api/v1/students/{ulid}/exam-results      ?session=   published only
```

### 7. Permissions · Settings · Events

```
exams.view                    exams.manage
exams.paper.manage ⚠⚠         exams.paper.download ⚠
exams.script.manage           exams.mark
exams.moderate                exams.arrangements.manage
exams.malpractice.view ⚠      exams.malpractice.manage ⚠
exams.results.process         exams.results.publish ⚠
exams.results.view
```

| Setting | Type | Default |
|---|---|---|
| `exams.index_number_pattern` | string | `{CENTRE}/{LEVEL}/{SEQ:4}` |
| `exams.seating_spacing_seats` | int | `1` |
| `exams.double_marking_enabled` | bool | `false` |
| `exams.double_marking_variance_threshold` | int | `5` |
| `exams.moderation_sample_percent` | int | `15` |
| `exams.absent_counts_as_zero` | bool | `false` |
| `exams.paper_max_downloads_per_user` | int | `3` |
| `exams.marking_scheme_release` | enum | `after_paper_sat` |
| `exams.exclude_subject_teacher_from_invigilation` | bool | `true` |

Events: `ExamSessionCreated` · `CandidatesConfirmed` · `PaperSealed` · `PaperReleased` ⚠ · `SeatingAllocated` · `ScriptBatchHandedOver` · `ScriptDiscrepancyDetected` ⚠ · `MarkVarianceDetected` · `MalpracticeReported` ⚠ · `ExamResultsPublished`

### 8. Acceptance criteria

```gherkin
AC-ACA-07-001
  Given a paper's release_at is tomorrow at 08:00
  When any user — including the Super Admin — attempts to download it today
  Then the request returns 403
  And there is no override path

AC-ACA-07-002
  Given a paper is downloaded after release
  Then the file carries a visible watermark naming the downloading user
  And a data_access_log entry records user, IP, device, and time

AC-ACA-07-003
  Given a paper's setter attempts to vet their own paper
  Then it is refused

AC-ACA-07-004
  Given a script batch of 42 is handed over and 41 are received
  Then the batch status becomes 'discrepancy'
  And the exams officer is alerted immediately
  And the custody log records both counts

AC-ACA-07-005
  Given double marking is enabled with a variance threshold of 5
  And a candidate scores 62 from the first marker and 71 from the second
  Then the mark routes to third marking
  And neither marker saw the other's mark before submitting

AC-ACA-07-006
  Given a candidate has an approved 25% extra time arrangement
  Then their finish time on the attendance sheet reflects it
  And the invigilator brief shows the arrangement

AC-ACA-07-007
  Given a candidate has an approved separate_room arrangement
  Then they are seated in their own venue
  And that venue is invigilated

AC-ACA-07-008
  Given marking is complete
  When a learner opens the portal
  Then no results are visible until the session is explicitly published

AC-ACA-07-009
  Given a malpractice outcome of paper_annulled
  Then the affected marks are voided
  And they remain visible with the annulment reason
  And the result excludes them from aggregation

AC-ACA-07-010
  Given a ZIMSEC session's candidates are confirmed
  Then the candidate set is available to CMP-01
  And ACA-07 makes no direct call to any ZIMSEC system

AC-ACA-07-011
  Given exam paper component weights total 90%
  When results processing is attempted
  Then it is blocked naming the subject and shortfall
```

---

## Part 3 — Domain C Depth Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **E1** | `ACA-03` structures, slots, venues, constraints | Requirements review shows feasibility warnings before generation |
| **E2** | `ACA-03` clash detection, four levels | **Learner-level clash detection proven against A-Level option blocks** |
| **E3** | `ACA-03` generation engine | Zero hard violations in output; unplaced requirements always reported |
| **E4** | `ACA-03` grid editor, views, publication | Clash panel updates during drag, not on drop |
| **E5** | `ACA-03` session generation ⭐ | **`ACA-04` interface closed. `AC-ACA-03-005` green.** |
| **E6** | `ACA-03` substitutions and cover | Approved leave creates cover before the school day starts |
| **E7** | `ACA-06` instruments, briefs, rubrics, milestones | One-project-per-subject-per-year enforced |
| **E8** | `ACA-06` submission, marking, moderation, verification | Append-only mark versioning |
| **E9** | `ACA-06` outcome provider ⭐ | **`ACA-05` interface closed. `AC-ACA-06-005` and `-008` green.** |
| **E10** | `ACA-06` portfolio, national export, CALA archive read-only | DB grants verified on `legacy_cala_records` |
| **E11** | `ACA-07` sessions, papers, candidates, index numbers | Entry derived from enrolments, never typed |
| **E12** | `ACA-07` paper vault, time-lock, watermarking | **No override path exists. `AC-ACA-07-001` green.** |
| **E13** | `ACA-07` seating, invigilation, special arrangements | Separate-room arrangements seated and invigilated |
| **E14** | `ACA-07` script custody, marking, variance, moderation | Discrepancy alerts immediate |
| **E15** | `ACA-07` results processing, malpractice, publication | Marks flow into `ACA-05` as examination assessments |

`ACA-06` (E7–E10) and `ACA-07` (E11–E15) are independent and should run in parallel.

---

## Part 4 — Domain C Depth Acceptance Gate

### The two closed interfaces

- [ ] `ACA-03` generates attendance sessions; `ACA-04` marks against them; republishing never touches past marked sessions
- [ ] `ACA-06` supplies `ContinuousAssessmentOutcome`; `ACA-05` renders it with the correct instrument label per framework year

### Timetable

- [ ] All `AC-ACA-03-*` pass
- [ ] Learner-level clash detection catches shared learners across concurrent teaching groups
- [ ] Generation over a 1,400-learner secondary school with 60 teachers completes within the time budget with zero hard violations
- [ ] Every unplaced requirement is named with its blocking constraint
- [ ] Locked slots survive regeneration
- [ ] Publication blocked while any hard violation exists
- [ ] Approved leave produces cover assignments before the school day begins

### Projects

- [ ] All `AC-ACA-06-*` pass
- [ ] SBP and CALA both render correctly on report cards from their respective framework years
- [ ] `legacy_cala_records` confirmed `SELECT`-only at database grant level
- [ ] Unverified projects block academic period close
- [ ] Offline evidence submission from the mobile app arrives intact

### Examinations

- [ ] All `AC-ACA-07-*` pass
- [ ] **Time-locked paper release cannot be bypassed by any role, including Super Admin**
- [ ] Watermarking identifies the downloading user on every retrieved paper
- [ ] Script custody discrepancies alert immediately and cannot be silently closed
- [ ] Double marking keeps markers blind to each other until both submit
- [ ] Special arrangements appear on attendance sheets and invigilator briefs
- [ ] Exam marks aggregate into `ACA-05`, not into a parallel result system

### Cross-cutting

- [ ] Period guard rejects writes in locked terms across all three modules
- [ ] Tenancy isolation suite passes for every model in this book
- [ ] Coverage ≥ 85%; `ACA-03` clash detection ≥ 95%
- [ ] Paper vault, malpractice, and script custody accesses all write to `data_access_log`

---

## Appendix A — Interfaces Closed by This Book

| Interface | Defined in | Implemented here | Verification |
|---|---|---|---|
| `AttendanceSessionGenerator` | `BR-ACA-04-002` | `ACA-03` §5 | `AC-ACA-03-005` |
| `ContinuousAssessmentProvider` | `BR-ACA-05-021` | `ACA-06` §4 | `AC-ACA-06-005`, `AC-ACA-06-008` |
| `ExaminationCandidateSet` | opened here | consumed by `CMP-01`, Book H | `AC-ACA-07-010` |

The third row is a new interface this book opens deliberately. `ACA-07` produces a confirmed candidate set with bio-data and subject entries; `CMP-01` validates it against ZIMSEC's requirements and exports it to the national Online Candidate Registration System. Keeping that boundary sharp means a change in the national system touches one module.

---

## Appendix B — Remaining Books

| Book | Domain | Modules |
|---|---|---|
| **F** | Boarding & Welfare | `BRD-01` → `BRD-05` (hostels, roll call, exeats, catering, laundry) |
| **G** | Welfare & Pastoral | `BRD-06` → `BRD-08` (health, discipline, safeguarding) |
| **H** | Operations, Payroll & Compliance | `OPS-*`, `PPL-05`, `FIN-08` → `FIN-14`, `CMP-*` |
| **I** | Communication & Portals | `COM-01` → `COM-08` |
| **J** | Intelligence & SaaS Control | `INT-*`, `SAA-*` |

**Book F is the commercial priority.** Boarding is what makes the product sellable to the schools this system was designed for, and no competing product in this market models it properly.

---

*End of Volume 2, Book E.*

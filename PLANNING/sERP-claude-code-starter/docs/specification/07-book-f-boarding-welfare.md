# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book F — Domain E: Boarding & Welfare (`BRD-01` → `BRD-05`)

| Field | Value |
|---|---|
| Document | Volume 2, Book F of 10 |
| Covers | Hostel & Bed Allocation · Roll Call & Movement · Exeat, Leave & Visitors · Catering & Kitchen · Laundry & Linen |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A–D complete.** `BRD-04` has a partial dependency on `FIN-09` (Book H) — see §0.3. |
| Next book | Book G — Welfare & Pastoral (`BRD-06` → `BRD-08`) |

---

## Part 0 — Read This First

### 0.1 Why this book matters commercially

Boarding is the reason a Peterhouse-class school switches systems. Generic international school platforms handle marks and fees competently and handle boarding badly or not at all. A Zimbabwean boarding school running 900 boarders across six hostels, feeding them three times a day from a store partly supplied by its own farm, managing weekend exeats for parents scattered across three provinces and the diaspora, is running an operation no off-the-shelf product addresses.

This book is the moat.

### 0.2 ⭐ Why this book matters more than commercially

**`BRD-02` and `BRD-03` are child-protection systems.**

Knowing where every boarder is at all times is a duty of care, not a convenience. Refusing to release a child to an unauthorised adult is a safeguarding control, not a workflow step. Two rules in this book are therefore written harder than anything else in the specification:

| Rule | Consequence |
|---|---|
| `BR-BRD-02-009` | A learner unaccounted for at roll call escalates on a timed ladder that cannot be silenced by acknowledgement alone. Someone must record what they found. |
| `BR-BRD-03-012` | The gate refuses release to any adult without an active `may_collect_learner` right, and **every refused attempt is logged permanently**, because a pattern of attempts is itself the signal. |

Neither has an override that a single person can exercise quietly. Build them as specified.

### 0.3 An honest dependency

`BRD-04` (Catering) costs food issued from the kitchen store, and store mechanics live in `FIN-09`, which is specified in Book H. Two options:

**Recommended.** Build a **minimal `FIN-09` kitchen-store subset alongside `BRD-04`** — item master, goods receipt, FIFO issue, stock take — scoped to store type `kitchen`. Book H then extends the same tables to general, boarding, uniform, laboratory and farm stores without rework.

**Fallback.** Build `BRD-04` in **planning-only mode**: menus, recipes, per-capita quantities and dietary management all work; costing and stock depletion are stubbed behind the `StoreIssuanceProvider` interface defined in §BRD-04.4 and light up when `FIN-09` ships.

The interface is specified either way, so the choice is a scheduling decision, not an architectural one.

### 0.4 Build order

```
BRD-01  Hostel, Room & Bed Allocation   ← the physical model everything else references
   ↓
BRD-02  Roll Call & Movement            ← ⭐ child safety; supplies live occupancy
   ↓
BRD-03  Exeat, Leave & Visitors         ← ⭐ child safety; consumes PPL-03 rights
   ↓
BRD-04  Catering & Kitchen              ← scales to BRD-02's live counts
   ↓
BRD-05  Laundry & Linen
```

---

# BRD-01 · Hostel, Room & Bed Allocation

### 1. Scope

**In scope.** The physical hierarchy, staff assignment, the allocation engine and its constraints, occupancy tracking, waiting lists, mid-term movement, room inspections, damage recording and charging.

**Out of scope.** Roll call (`BRD-02`). Linen issue (`BRD-05`). Building maintenance (`OPS-02`, Book H).

### 2. Data model

```sql
hostels
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL   -- 'NEH','LOB'
name                    VARCHAR(120) NOT NULL   -- 'Nehanda House'
gender                  VARCHAR(10)  NOT NULL   -- male|female  ⭐ HARD constraint
section_id              BIGINT       NULL FK    -- junior vs senior hostel
house_id                BIGINT       NULL FK    -- where hostel = sports house
housemaster_staff_id    BIGINT       NULL FK → staff.id
matron_staff_id         BIGINT       NULL FK
deputy_staff_id         BIGINT       NULL FK
capacity                SMALLINT     NOT NULL DEFAULT 0   -- derived from beds
building                VARCHAR(80)  NULL
latitude                DECIMAL(10,7) NULL
longitude               DECIMAL(10,7) NULL
has_sick_bay            TINYINT(1)   NOT NULL DEFAULT 0
has_prep_room           TINYINT(1)   NOT NULL DEFAULT 0
notes                   TEXT         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, code)
  INDEX  (school_id, gender, is_active)

hostel_wings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
hostel_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL   -- 'A','GROUND','UPPER'
name                    VARCHAR(80)  NOT NULL
floor                   VARCHAR(20)  NULL
supervisor_staff_id     BIGINT       NULL FK
prefect_student_id      BIGINT       NULL FK → students.id
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, hostel_id, code)

hostel_rooms
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
hostel_id               BIGINT       FK INDEX
wing_id                 BIGINT       NULL FK
room_number             VARCHAR(20)  NOT NULL
room_type               VARCHAR(20)  NOT NULL   -- dormitory|cubicle|single|
                                                -- prefect|isolation|staff
bed_count               SMALLINT     NOT NULL
condition_grade         VARCHAR(20)  NOT NULL DEFAULT 'good'  -- good|fair|poor|
                                                              -- out_of_service
is_ground_floor         TINYINT(1)   NOT NULL DEFAULT 0   -- mobility / medical
proximity_to_exit       VARCHAR(20)  NULL       -- near|mid|far  ⭐ asthma, epilepsy
proximity_to_ablution   VARCHAR(20)  NULL
has_power_outlet        TINYINT(1)   NOT NULL DEFAULT 1
notes                   VARCHAR(255) NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, hostel_id, room_number)
  INDEX  (school_id, hostel_id, condition_grade)

hostel_beds
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
room_id                 BIGINT       FK INDEX
bed_number              VARCHAR(20)  NOT NULL
bed_type                VARCHAR(20)  NOT NULL   -- single|bunk_upper|bunk_lower
asset_tag               VARCHAR(40)  NULL       -- FIN-10 fixed asset linkage
mattress_asset_tag      VARCHAR(40)  NULL
condition_grade         VARCHAR(20)  NOT NULL DEFAULT 'good'
is_available            TINYINT(1)   NOT NULL DEFAULT 1
out_of_service_reason   VARCHAR(255) NULL
  UNIQUE (school_id, room_id, bed_number)
  INDEX  (school_id, is_available)

bed_allocations                       -- dated occupancy history
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
bed_id                  BIGINT       FK INDEX
hostel_id               BIGINT       FK          -- denormalised for fast rolls
room_id                 BIGINT       FK          -- denormalised
allocation_type         VARCHAR(20)  NOT NULL    -- initial|reallocated|temporary|
                                                 -- isolation|promoted
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
status                  VARCHAR(20)  NOT NULL    -- draft|confirmed|ended|superseded
reason                  VARCHAR(255) NULL
allocated_by            BIGINT       FK → users.id
confirmed_by            BIGINT       NULL FK
  UNIQUE (school_id, bed_id, effective_from)
  UNIQUE (school_id, student_id, effective_from)
  INDEX  (school_id, term_id, hostel_id, status)
  INDEX  (school_id, student_id, effective_from)

allocation_constraints                -- ⭐ the rule set
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
constraint_type         VARCHAR(40)  NOT NULL
                        -- gender_match | level_band | house_affinity |
                        -- sibling_together | sibling_apart | medical_proximity |
                        -- mobility_ground_floor | max_level_spread |
                        -- prefect_room_only | learner_incompatibility
severity                VARCHAR(10)  NOT NULL    -- hard | soft
weight                  SMALLINT     NOT NULL DEFAULT 1
hostel_id               BIGINT       NULL FK
grade_level_ids         JSON         NULL
value                   SMALLINT     NULL
reason                  VARCHAR(255) NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1

learner_incompatibilities             -- keep these two apart
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_a_id            BIGINT       FK
student_b_id            BIGINT       FK
scope                   VARCHAR(20)  NOT NULL    -- room|wing|hostel
reason_category         VARCHAR(40)  NOT NULL    -- bullying|conflict|safeguarding|
                                                 -- family_request|medical
reason                  TEXT         NULL        -- restricted visibility
is_confidential         TINYINT(1)   NOT NULL DEFAULT 1
raised_by               BIGINT       FK → users.id
expires_on              DATE         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, student_a_id, student_b_id)

hostel_waiting_list
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
student_id              BIGINT       FK INDEX
preferred_hostel_id     BIGINT       NULL FK
priority_score          DECIMAL(6,2) NULL
position                SMALLINT     NULL
reason                  VARCHAR(255) NULL
status                  VARCHAR(20)  NOT NULL    -- waiting|offered|allocated|
                                                 -- declined|expired
added_at                TIMESTAMP    NOT NULL
  UNIQUE (school_id, term_id, student_id)

room_inspections
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
room_id                 BIGINT       FK INDEX
inspection_date         DATE         NOT NULL
inspection_type         VARCHAR(20)  NOT NULL    -- routine|spot|end_of_term|
                                                 -- start_of_term|complaint
criteria_scores         JSON         NOT NULL    -- {tidiness:8, cleanliness:7, ...}
total_score             DECIMAL(5,2) NULL
max_score               DECIMAL(5,2) NOT NULL
grade                   VARCHAR(20)  NULL
findings                TEXT         NULL
photo_file_ids          JSON         NULL
inspector_staff_id      BIGINT       FK
follow_up_required      TINYINT(1)   NOT NULL DEFAULT 0
follow_up_by            DATE         NULL
  INDEX (school_id, room_id, inspection_date)

hostel_damages
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
hostel_id               BIGINT       FK
room_id                 BIGINT       NULL FK
bed_id                  BIGINT       NULL FK
inspection_id           BIGINT       NULL FK
damage_type             VARCHAR(40)  NOT NULL    -- window|door|furniture|bedding|
                                                 -- plumbing|electrical|wall|locker
description             TEXT         NOT NULL
photo_file_ids          JSON         NULL
estimated_cost_minor    BIGINT       NULL
actual_cost_minor       BIGINT       NULL
currency                CHAR(3)      NOT NULL
liability               VARCHAR(20)  NOT NULL    -- individual|shared_room|
                                                 -- shared_wing|school|unknown
liable_student_ids      JSON         NULL        -- one or many
charge_status           VARCHAR(20)  NOT NULL    -- pending|approved|charged|
                                                 -- waived|disputed
approval_request_id     BIGINT       NULL FK     -- CORE-07
ad_hoc_charge_ids       JSON         NULL        -- FIN-02 linkage ⭐
work_order_id           BIGINT       NULL FK     -- OPS-02
reported_by             BIGINT       FK → users.id
reported_at             TIMESTAMP    NOT NULL
  INDEX (school_id, term_id, charge_status)
```

### 3. ⭐ The allocation engine

Bed allocation looks like a seating puzzle and is actually a duty-of-care exercise. The constraint order matters.

```
HARD constraints — never violated, allocation fails rather than breach them
────────────────────────────────────────────────────────────────────────────
 1  gender_match            learner.gender === hostel.gender          ⭐ absolute
 2  bed availability        bed.is_available AND no overlapping allocation
 3  room in service         room.condition_grade != 'out_of_service'
 4  learner_incompatibility no two incompatible learners within the stated scope
 5  medical_proximity       an approved medical requirement is honoured
 6  mobility_ground_floor   a mobility requirement means ground floor

SOFT constraints — weighted, minimised
────────────────────────────────────────────────────────────────────────────
 7  level_band              keep a room within N form levels
 8  house_affinity          prefer the learner's sports house hostel
 9  sibling_together        per school policy; may be inverted to sibling_apart
10  continuity              prefer last term's bed for a returning learner
11  room balance            even fill across rooms rather than clustering
```

**Gender is a hard constraint with no override in the interface.** There is no permission that permits allocating a boy to a girls' hostel. If a school has a legitimate edge case — a staff child, a sanatorium isolation room — it is modelled as a room of type `isolation` or `staff` outside the normal allocation pool, not as an exception to the rule.

**Medical proximity is honoured but never explained.** The allocation engine receives a boolean requirement from `BRD-06` — "this learner requires proximity to an exit" — and never the diagnosis. The housemaster sees the placement constraint; only the nurse sees why. This is the same flag-versus-detail split `PPL-01` uses.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-01-001` ⭐ | Gender segregation is a hard constraint with no override path. Allocation to a hostel whose gender does not match the learner's is refused at the Action layer, not merely hidden in the UI. |
| `BR-BRD-01-002` | Hostel capacity is derived from active, available beds. It is never entered manually. |
| `BR-BRD-01-003` | A bed holds at most one allocation on any date. Overlapping allocations are structurally prevented. |
| `BR-BRD-01-004` | A learner holds at most one active bed allocation, except during a documented temporary move (isolation, sick bay), which is `allocation_type = temporary` and carries an expected return date. |
| `BR-BRD-01-005` | Only learners with `residency` of `BOARDER` or `WEEKLY_BOARDER` may hold an allocation. A residency change to `DAY` ends the allocation on the effective date and releases the bed. |
| `BR-BRD-01-006` | Allocations are dated. Mid-term movement supersedes rather than overwrites; the full history is queryable, including who moved whom and why. |
| `BR-BRD-01-007` | Bulk allocation produces **draft** allocations. Nothing is confirmed without human review, and the draft screen shows every soft violation before confirmation. |
| `BR-BRD-01-008` | Learner incompatibility reasons are confidential where flagged. The allocation engine honours the constraint; the housemaster sees only that a constraint exists. |
| `BR-BRD-01-009` | An out-of-service room's occupants must be reallocated before the room can be marked out of service. |
| `BR-BRD-01-010` | The waiting list orders by priority score, recomputes when a bed is released, and offers with an expiry. |
| `BR-BRD-01-011` | Room inspection scores are recorded with photographs where the finding warrants it, and feed the inter-house competition in `OPS-07` where that is enabled. |
| `BR-BRD-01-012` ⭐ | Damage with `liability = individual` or `shared_room` raises an ad hoc charge through `FIN-02` against the liable learners, split evenly for shared liability, **after approval**. It is never charged silently. |
| `BR-BRD-01-013` | A disputed damage charge halts billing until resolved. The dispute, its resolution, and who decided it are recorded. |
| `BR-BRD-01-014` | Damage requiring repair raises a work order in `OPS-02` where that module is enabled; the two records link. |
| `BR-BRD-01-015` | Term roll-over produces draft allocations for the new term honouring continuity and level progression, and reports every learner who could not be placed. |
| `BR-BRD-01-016` | Withdrawal, transfer, or graduation ends the allocation on the exit date and releases the bed to the waiting list. |
| `BR-BRD-01-017` | Occupancy figures are derived from allocations, never stored, and are the input to `BRD-04` catering planning. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Hostel structure | `Boarding\Hostels\Structure` | `boarding.hostel.manage` — tree: hostel → wing → room → bed, with condition indicators |
| Hostel profile | `Boarding\Hostels\Show` | `boarding.hostel.view` — staff, occupancy, inspection history, damages |
| **Occupancy board** | `Boarding\Allocation\Board` | `boarding.allocation.view` ⭐ — visual room grid, occupied and free beds, drag to move, live constraint checking |
| Allocation run | `Boarding\Allocation\Run` | `boarding.allocation.manage` — auto-allocate, review drafts with violations listed, confirm |
| Learner allocation | `Boarding\Allocation\Learner` | `boarding.allocation.view` — current bed, full dated history |
| Waiting list | `Boarding\Allocation\Waitlist` | `boarding.allocation.manage` |
| Constraints | `Boarding\Allocation\Constraints` | `boarding.hostel.manage` |
| Incompatibilities | `Boarding\Allocation\Incompatibilities` | `boarding.incompatibility.manage` ⚠ — reason restricted |
| Inspections | `Boarding\Inspections\Index` | `boarding.inspection.manage` — mobile-first, photo capture, scoring rubric |
| Damages | `Boarding\Damages\Index` | `boarding.damage.manage` — report, assess liability, approve charge |
| Bed availability | `Boarding\Reports\Availability` | `boarding.hostel.view` |

### 6. API endpoints

```
GET  /api/v1/boarding/my-allocation           learner: hostel, room, bed
GET  /api/v1/students/{ulid}/boarding         guardian: hostel, housemaster, matron contact
GET  /api/v1/boarding/hostels/{ulid}/rolls    staff: current occupancy roll
POST /api/v1/boarding/inspections             mobile inspection with photos
GET  /api/v1/boarding/damages                 staff
POST /api/v1/boarding/damages                 report from mobile with photos
```

### 7. Permissions · Settings · Events

```
boarding.hostel.view              boarding.hostel.manage
boarding.allocation.view          boarding.allocation.manage
boarding.allocation.confirm       boarding.incompatibility.manage ⚠
boarding.inspection.view          boarding.inspection.manage
boarding.damage.view              boarding.damage.manage
boarding.damage.approve_charge ⚠
```

| Setting | Type | Default |
|---|---|---|
| `boarding.max_level_spread_per_room` | int | `2` |
| `boarding.prefer_house_hostel` | bool | `true` |
| `boarding.siblings_same_hostel` | bool | `true` |
| `boarding.prefer_bed_continuity` | bool | `true` |
| `boarding.damage_requires_approval` | bool | `true` |
| `boarding.damage_shared_liability_default` | enum | `shared_room` |
| `boarding.inspection_frequency_days` | int | `7` |
| `boarding.waitlist_offer_expiry_hours` | int | `48` |

Events: `BedAllocated` · `BedReleased` · `LearnerMovedRoom` · `HostelAtCapacity` · `RoomOutOfService` · `InspectionRecorded` · `DamageReported` · `DamageChargeApproved` (→ `FIN-02`) · `AllocationDraftReady`

### 8. Acceptance criteria

```gherkin
AC-BRD-01-001
  Given a male learner and a female hostel
  When allocation is attempted by any user with any permission
  Then it is refused at the Action layer
  And no override path exists

AC-BRD-01-002
  Given two learners with an active incompatibility scoped to 'room'
  When the allocation engine runs
  Then they are never placed in the same room
  And the housemaster sees that a constraint applied, not the reason

AC-BRD-01-003
  Given a learner's residency changes from BOARDER to DAY effective 2026-10-12
  Then their bed allocation ends on that date
  And the bed becomes available
  And the waiting list is re-evaluated

AC-BRD-01-004
  Given bulk allocation runs for 900 boarders
  Then all allocations are created as drafts
  And every soft violation is listed before confirmation
  And no learner is confirmed without review

AC-BRD-01-005
  Given a broken window with liability 'shared_room' and 6 occupants
  When the charge is approved
  Then 6 ad hoc charges are raised through FIN-02, one per learner
  And their sum equals the assessed cost exactly

AC-BRD-01-006
  Given a damage charge is disputed
  Then billing halts
  And the dispute and its resolution are recorded

AC-BRD-01-007
  Given a room is marked out of service while occupied
  Then the change is refused until its occupants are reallocated

AC-BRD-01-008
  Given a learner requires proximity to an exit for medical reasons
  Then they are allocated to a room with proximity_to_exit = 'near'
  And the housemaster's view shows the constraint without the diagnosis
```

---

# BRD-02 · Roll Call & Movement ⭐

> **A child-protection system.** Knowing where every boarder is, at all times, is a duty of care. Build the escalation ladder exactly as specified — it is the part that matters when something has actually gone wrong.

### 1. Scope

**In scope.** Roll call point configuration, marking across web, mobile, offline and hardware, automatic status pre-population, the missing-learner escalation ladder, campus movement logging, live occupancy feeding catering, historical audit.

**Out of scope.** Class attendance (`ACA-04`, whose engine this module extends with different roll points). Exeats (`BRD-03`).

### 2. Data model

```sql
roll_call_points                      -- when a roll is taken
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
hostel_id               BIGINT       NULL FK     -- null = all hostels
code                    VARCHAR(30)  NOT NULL    -- 'MORNING','PREP','LIGHTS_OUT'
name                    VARCHAR(80)  NOT NULL
scheduled_time          TIME         NOT NULL
applies_on_days         JSON         NOT NULL    -- ['mon'..'sun']
applies_in_term_only    TINYINT(1)   NOT NULL DEFAULT 1
grace_minutes           SMALLINT     NOT NULL DEFAULT 10
is_mandatory            TINYINT(1)   NOT NULL DEFAULT 1
escalation_profile_id   BIGINT       FK
sort_order              SMALLINT
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, hostel_id, code)

roll_calls                            -- one occurrence
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK INDEX
roll_call_point_id      BIGINT       FK
hostel_id               BIGINT       FK INDEX
roll_date               DATE         NOT NULL
scheduled_at            TIMESTAMP    NOT NULL
expected_count          SMALLINT     NOT NULL DEFAULT 0
present_count           SMALLINT     NOT NULL DEFAULT 0
accounted_count         SMALLINT     NOT NULL DEFAULT 0   -- exeat, sick bay, fixture
missing_count           SMALLINT     NOT NULL DEFAULT 0   -- ⭐ the number that matters
status                  VARCHAR(20)  NOT NULL   -- pending|in_progress|completed|
                                                -- escalated|resolved|missed
started_at              TIMESTAMP    NULL
completed_at            TIMESTAMP    NULL
conducted_by            BIGINT       NULL FK → users.id
device_source           VARCHAR(20)  NULL       -- mobile|web|rfid|biometric
  UNIQUE (school_id, roll_call_point_id, hostel_id, roll_date)
  INDEX  (school_id, roll_date, status)
  INDEX  (school_id, status, missing_count)

roll_call_records
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
roll_call_id            BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
roll_date               DATE         NOT NULL    -- denormalised
status                  VARCHAR(20)  NOT NULL    -- present|missing|exeat|sick_bay|
                                                 -- hospital|fixture|detention|
                                                 -- suspended|withdrawn|late
is_auto_populated       TINYINT(1)   NOT NULL DEFAULT 0
source_reference        VARCHAR(120) NULL        -- exeat ulid, sick bay admission
marked_by               BIGINT       NULL FK
marked_at               TIMESTAMP    NOT NULL
device_source           VARCHAR(20)  NULL
note                    VARCHAR(255) NULL
resolved_at             TIMESTAMP    NULL        -- for a missing learner subsequently found
resolved_by             BIGINT       NULL FK
resolution_note         TEXT         NULL
  UNIQUE (roll_call_id, student_id)
  INDEX  (school_id, student_id, roll_date)
  INDEX  (school_id, status, roll_date)

escalation_profiles                   -- ⭐ the ladder
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
name                    VARCHAR(120) NOT NULL
description             VARCHAR(255) NULL
is_default              TINYINT(1)   NOT NULL DEFAULT 0

escalation_steps
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
profile_id              BIGINT       FK INDEX
step_number             TINYINT      NOT NULL
delay_minutes           SMALLINT     NOT NULL    -- from the previous step
notify_role_id          BIGINT       NULL FK
notify_staff_id         BIGINT       NULL FK
notify_guardians        TINYINT(1)   NOT NULL DEFAULT 0
channels                JSON         NOT NULL    -- ['push','whatsapp','sms','call_list']
requires_acknowledgement TINYINT(1)  NOT NULL DEFAULT 1
requires_action_record  TINYINT(1)   NOT NULL DEFAULT 0  -- ⭐ must say what you did
message_template_key    VARCHAR(80)  NOT NULL
  UNIQUE (profile_id, step_number)

missing_learner_incidents             -- APPEND-ONLY once opened ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
student_id              BIGINT       FK INDEX
roll_call_id            BIGINT       FK
first_missed_at         TIMESTAMP    NOT NULL
last_seen_at            TIMESTAMP    NULL
last_seen_location      VARCHAR(150) NULL
last_seen_source        VARCHAR(60)  NULL        -- gate log, class register, checkpoint
current_step            TINYINT      NOT NULL DEFAULT 1
status                  VARCHAR(20)  NOT NULL    -- open|escalating|located|
                                                 -- resolved|false_alarm
located_at              TIMESTAMP    NULL
located_by              BIGINT       NULL FK
location_found          VARCHAR(255) NULL
outcome                 VARCHAR(40)  NULL        -- safe|absconded|medical|
                                                 -- unauthorised_absence|admin_error
outcome_note            TEXT         NULL
guardians_notified_at   TIMESTAMP    NULL
authorities_notified_at TIMESTAMP    NULL
closed_by               BIGINT       NULL FK
closed_at               TIMESTAMP    NULL
  INDEX (school_id, status, first_missed_at)
  -- No UPDATE except the resolution columns; no DELETE, ever

escalation_actions                    -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
incident_id             BIGINT       FK INDEX
step_number             TINYINT      NOT NULL
action_type             VARCHAR(30)  NOT NULL    -- notified|acknowledged|
                                                 -- action_recorded|escalated|resolved
actor_id                BIGINT       NULL FK
action_taken            TEXT         NULL        -- ⭐ "checked sick bay, not there"
occurred_at             TIMESTAMP    NOT NULL

movement_checkpoints
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL    -- 'MAIN_GATE','DINING','LIBRARY'
name                    VARCHAR(120) NOT NULL
checkpoint_type         VARCHAR(30)  NOT NULL    -- gate|building|zone|dining|
                                                 -- sanatorium|transport
hardware_device_id      VARCHAR(80)  NULL
is_boundary             TINYINT(1)   NOT NULL DEFAULT 0   -- crossing = leaving campus
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

movement_log                          -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
checkpoint_id           BIGINT       FK
direction               VARCHAR(10)  NOT NULL    -- in | out
occurred_at             TIMESTAMP(3) NOT NULL
method                  VARCHAR(20)  NOT NULL    -- rfid|qr|biometric|manual
recorded_by             BIGINT       NULL FK
exeat_id                BIGINT       NULL FK     -- BRD-03 authorisation
is_authorised           TINYINT(1)   NOT NULL DEFAULT 1
note                    VARCHAR(255) NULL
  INDEX (school_id, student_id, occurred_at)
  INDEX (school_id, checkpoint_id, occurred_at)
```

### 3. ⭐ The escalation ladder

This is the part that matters when a child is genuinely missing. The design principle: **acknowledgement is not resolution.** Someone must record what they actually did.

```
ROLL CALL COMPLETED, missing_count > 0
  │
  ▼
Incident opened per missing learner (append-only)
  │
  ├─ STEP 1  T+0 min      Housemaster + Matron
  │                        push + in-app
  │                        requires_acknowledgement
  │                        requires_action_record ⭐
  │                        "I checked the ablution block and the prep room."
  │
  ├─ STEP 2  T+10 min     Boarding Master + Deputy Head
  │                        push + WhatsApp + SMS
  │                        requires_action_record
  │                        System auto-attaches: last gate scan, last class
  │                        register, open exeat records, sick bay admissions
  │
  ├─ STEP 3  T+20 min     Headmaster + Security
  │                        all channels + call list
  │                        Campus-wide search protocol displayed
  │
  ├─ STEP 4  T+30 min     GUARDIANS notified
  │                        Configurable, default ON
  │
  └─ STEP 5  T+45 min     Safeguarding Lead; authority contact prompted
                           System does NOT contact police automatically —
                           it prompts and records the decision and the time
  │
  ▼
LOCATED → outcome recorded → incident closed by a named person
```

| # | Rule |
|---|---|
| The ladder advances **automatically on time**. Acknowledging a step stops the notification repeat for that step; it does not stop the clock. |
| A step with `requires_action_record = 1` cannot advance to resolved without free text describing what was checked. "Acknowledged" is not an action. |
| A learner located at any step immediately halts escalation and prompts for the outcome. |
| An incident marked `false_alarm` (an administrative error — an unrecorded exeat, a stale allocation) still remains permanently. **The pattern of false alarms is itself information** about how well the school's records are kept. |
| Incidents cannot be deleted by anyone, at any permission level. |

### 4. Automatic status pre-population

The single largest source of false alarms is a learner who is legitimately elsewhere and whose absence nobody wired up. So the roll pre-populates from every module that knows better.

| Source | Status set | Module |
|---|---|---|
| Approved exeat active on this date | `exeat` | `BRD-03` |
| Sick bay admission open | `sick_bay` | `BRD-06` |
| Hospital referral active | `hospital` | `BRD-06` |
| Sports fixture away, on the squad | `fixture` | `OPS-07` |
| Detention scheduled at this time | `detention` | `BRD-07` |
| Suspension active | `suspended` | `BRD-07` |
| Status not `active` | `withdrawn` | `PPL-01` |

A pre-populated status carries `is_auto_populated = 1` and a `source_reference`, so the housemaster can see *why* a learner is marked accounted for, and can override with a note if the source is wrong.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-02-001` | Roll call points are configurable per hostel. A junior hostel and a sixth-form hostel legitimately run different schedules. |
| `BR-BRD-02-002` | The expected roll is derived from active bed allocations on the roll date, never from a stored list. |
| `BR-BRD-02-003` | Statuses pre-populate from every source in §4 before the housemaster opens the roll. |
| `BR-BRD-02-004` | A pre-populated status is overridable with a mandatory note, and the override is recorded against the housemaster. |
| `BR-BRD-02-005` | Marking works fully offline. Dormitories frequently have no signal, and the roll must not depend on one. |
| `BR-BRD-02-006` | Offline marks queue with an idempotency key per roll call and drain in order on reconnect. Conflicts surface to the marker; nothing is silently discarded. |
| `BR-BRD-02-007` | The **server** determines `roll_date` from the roll call record, never from the device clock. |
| `BR-BRD-02-008` | A roll call not started within `grace_minutes` of its scheduled time raises a `RollCallMissed` alert to the boarding master. **An unconducted roll is a failure, not a silence.** |
| `BR-BRD-02-009` ⭐ | A learner marked `missing` opens an incident immediately and starts the escalation ladder. The ladder advances on time. Acknowledgement suppresses notification repeats only; it never stops the clock. |
| `BR-BRD-02-010` ⭐ | Steps flagged `requires_action_record` cannot be satisfied by acknowledgement alone. Free text describing what was checked is mandatory. |
| `BR-BRD-02-011` | On opening an incident, the system automatically attaches the learner's last gate scan, last class register mark, any open exeat, and any sick bay record, so the searcher starts with information rather than a name. |
| `BR-BRD-02-012` | Locating a learner halts escalation and requires an outcome. |
| `BR-BRD-02-013` | Incidents and escalation actions are append-only. No deletion path exists at any permission level. |
| `BR-BRD-02-014` | The system never contacts external authorities automatically. It prompts at the configured step and records the decision, the decider, and the time. |
| `BR-BRD-02-015` | Guardian notification at the configured step is on by default and can be disabled only by the head, with the change logged. |
| `BR-BRD-02-016` | Hardware sources (RFID, biometric) create records with `device_source` set. **A hardware failure never blocks manual marking** — the fallback is always available. |
| `BR-BRD-02-017` | The movement log is append-only. Crossing a checkpoint with `is_boundary = 1` without an active exeat flags `is_authorised = 0` and alerts security. |
| `BR-BRD-02-018` | Live present-and-accounted counts per hostel are exposed to `BRD-04` for catering, and to the emergency muster roll in `OPS-06`. |
| `BR-BRD-02-019` | Roll call history is retained for the full `CMP-03` retention period. It is evidence. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| **Take roll call** | `Boarding\RollCall\Take` | `boarding.rollcall.conduct` ⭐ — mobile-first, photo grid, one tap, pre-populated statuses shown with source, all-present action, offline indicator with queue depth |
| Roll call board | `Boarding\RollCall\Board` | `boarding.rollcall.view` — every hostel today: conducted, pending, missing counts |
| **Incident console** | `Boarding\RollCall\Incidents` | `boarding.incident.view` ⭐ — open incidents, current step, countdown to next, attached context, action recording |
| Incident detail | `Boarding\RollCall\Incident` | `boarding.incident.view` — full timeline, every notification, every action, outcome |
| Movement log | `Boarding\Movement\Log` | `boarding.movement.view` — per learner or per checkpoint, unauthorised crossings highlighted |
| Live occupancy | `Boarding\Occupancy\Live` | `boarding.rollcall.view` — present, exeat, sick bay, off-campus, per hostel |
| Escalation profiles | `Boarding\RollCall\Escalation` | `boarding.rollcall.manage` ⚠ |
| Roll call history | `Boarding\RollCall\History` | `boarding.rollcall.view` — conducted, missed, by hostel and staff member |
| Checkpoints | `Boarding\Movement\Checkpoints` | `boarding.movement.manage` |

### 7. API endpoints

```
GET  /api/v1/boarding/roll-calls/due            housemaster: what to take now
GET  /api/v1/boarding/roll-calls/{ulid}         roll with pre-populated statuses
POST /api/v1/boarding/roll-calls/{ulid}/mark    Idempotency-Key REQUIRED
POST /api/v1/boarding/roll-calls/sync           offline queue drain
GET  /api/v1/boarding/incidents/open            escalation console feed
POST /api/v1/boarding/incidents/{ulid}/acknowledge
POST /api/v1/boarding/incidents/{ulid}/action   { action_taken }  ⭐ mandatory text
POST /api/v1/boarding/incidents/{ulid}/locate   { location, outcome, note }
POST /api/v1/boarding/movement                  checkpoint scan
GET  /api/v1/boarding/occupancy/live            catering + muster
```

### 8. Permissions · Settings · Events

```
boarding.rollcall.view            boarding.rollcall.conduct
boarding.rollcall.manage ⚠        boarding.incident.view
boarding.incident.action          boarding.incident.close ⚠
boarding.movement.view            boarding.movement.manage
```

| Setting | Type | Default |
|---|---|---|
| `boarding.rollcall_grace_minutes` | int | `10` |
| `boarding.escalation_notify_guardians_step` | int | `4` |
| `boarding.escalation_guardian_notification_enabled` | bool | `true` |
| `boarding.require_action_record_from_step` | int | `1` |
| `boarding.rollcall_offline_cache_hours` | int | `24` |
| `boarding.unauthorised_boundary_alert` | bool | `true` |
| `boarding.rollcall_photo_grid` | bool | `true` |

Events: `RollCallStarted` · `RollCallCompleted` · `RollCallMissed` ⚠ · `LearnerMissing` ⚠⚠ · `EscalationStepTriggered` ⚠ · `LearnerLocated` · `IncidentClosed` · `UnauthorisedBoundaryCrossing` ⚠ · `LiveOccupancyUpdated`

### 9. Acceptance criteria

```gherkin
AC-BRD-02-001
  Given a learner has an approved exeat active tonight
  When the lights-out roll is opened
  Then their status is pre-populated as 'exeat'
  And the source reference names the exeat
  And they are not counted as missing

AC-BRD-02-002
  Given a learner is marked missing at 21:05
  Then an incident opens immediately
  And step 1 notifies the housemaster and matron
  And the incident attaches the last gate scan, last register mark,
      any open exeat, and any sick bay record

AC-BRD-02-003
  Given the housemaster acknowledges step 1 at 21:07
  When 21:15 arrives
  Then step 2 fires regardless
  Because acknowledgement does not stop the clock

AC-BRD-02-004
  Given step 1 requires an action record
  When the housemaster submits acknowledgement with no text
  Then it is rejected
  And the step remains unsatisfied

AC-BRD-02-005
  Given the learner is found in the library at 21:22
  Then escalation halts immediately
  And an outcome is required before the incident can close

AC-BRD-02-006
  Given an incident was a false alarm caused by an unrecorded exeat
  Then the incident remains permanently with outcome 'admin_error'
  And no user at any permission level can delete it

AC-BRD-02-007
  Given a housemaster takes a roll call with no network in the dormitory
  Then marking completes locally
  And the queue depth is visible in the app
  And marks arrive intact on reconnect

AC-BRD-02-008
  Given the RFID reader at the hostel fails
  Then manual marking remains fully available
  And the roll can be completed

AC-BRD-02-009
  Given the morning roll is not started within 10 minutes of its scheduled time
  Then a RollCallMissed alert reaches the boarding master

AC-BRD-02-010
  Given a learner scans out at the main gate with no active exeat
  Then the movement record is flagged is_authorised = 0
  And security is alerted

AC-BRD-02-011
  Given 640 boarders are present and 12 are on exeat
  When BRD-04 requests live occupancy
  Then it receives 640, not 652
```

---

# BRD-03 · Exeat, Leave & Visitor Management ⭐

> The chain of custody of a child. Every design decision here answers one question: *can the school prove who took this learner, when, and on whose authority?*

### 1. Scope

**In scope.** Parent-initiated exeat requests, configurable multi-stage approval, exeat passes with QR verification, gate exit and re-entry, overdue return handling, collection authorisation against `PPL-03` rights, visitor register, visiting-day slot booking, blacklist and watchlist.

**Out of scope.** Roll call (`BRD-02`, which consumes exeat status). Guardian rights themselves (`PPL-03`, which owns them). General campus security patrols (`OPS-06`, Book H).

### 2. Data model

```sql
exeat_types
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL    -- 'WEEKEND','HALF_TERM','MEDICAL',
                                                 -- 'COMPASSIONATE','FIXTURE','DAY_PASS'
name                    VARCHAR(120) NOT NULL
max_duration_hours      SMALLINT     NULL
requires_guardian_request TINYINT(1) NOT NULL DEFAULT 1
requires_document       TINYINT(1)   NOT NULL DEFAULT 0   -- medical letter
approval_chain_id       BIGINT       FK           -- CORE-07
min_notice_hours        SMALLINT     NOT NULL DEFAULT 24
allowed_per_term        SMALLINT     NULL         -- quota
counts_toward_quota     TINYINT(1)   NOT NULL DEFAULT 1
blocks_on_fee_arrears   TINYINT(1)   NOT NULL DEFAULT 0
blocks_on_suspension    TINYINT(1)   NOT NULL DEFAULT 1
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

exeats
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
exeat_number            VARCHAR(40)  NOT NULL     -- gapless, CORE-06
student_id              BIGINT       FK INDEX
exeat_type_id           BIGINT       FK
-- request
requested_by_guardian_id BIGINT      NULL FK      -- ⭐ parent-initiated
requested_by_user_id    BIGINT       NULL FK      -- staff-initiated (medical, fixture)
request_source          VARCHAR(20)  NOT NULL     -- guardian_portal|mobile_app|
                                                  -- staff|phone_recorded
reason                  TEXT         NOT NULL
supporting_document_id  BIGINT       NULL FK
-- timing
departs_at              TIMESTAMP    NOT NULL
returns_by              TIMESTAMP    NOT NULL
-- destination
destination_address     VARCHAR(255) NOT NULL
destination_city        VARCHAR(100) NULL
destination_province    VARCHAR(60)  NULL         -- drives escalation
destination_country     CHAR(2)      NOT NULL DEFAULT 'ZW'
contact_phone           VARCHAR(30)  NOT NULL     -- reachable while away
-- ⭐ collection authority
collecting_guardian_id  BIGINT       NULL FK → guardians.id
collecting_person_name  VARCHAR(150) NULL         -- one-off authorised person
collecting_person_id_no VARCHAR(30)  NULL         -- ENCRYPTED
collecting_person_phone VARCHAR(30)  NULL
collection_method       VARCHAR(30)  NOT NULL     -- guardian_collect|authorised_person|
                                                  -- school_transport|public_transport|
                                                  -- self_travel
one_off_authorisation_by BIGINT      NULL FK      -- who authorised a non-listed person
-- status
status                  VARCHAR(20)  NOT NULL     -- draft|pending|approved|rejected|
                                                  -- cancelled|departed|returned|
                                                  -- overdue|expired
approval_request_id     BIGINT       NULL FK      -- CORE-07
rejection_reason        VARCHAR(255) NULL
pass_document_id        BIGINT       NULL FK
verification_code       VARCHAR(40)  NULL UNIQUE  -- QR target
-- actual movement
actual_departure_at     TIMESTAMP    NULL
departure_recorded_by   BIGINT       NULL FK
departure_verified_by   VARCHAR(150) NULL         -- who actually collected
actual_return_at        TIMESTAMP    NULL
return_recorded_by      BIGINT       NULL FK
overdue_notified_at     TIMESTAMP    NULL
late_return_minutes     SMALLINT     NULL
created_at, updated_at
  UNIQUE (school_id, exeat_number)
  INDEX  (school_id, student_id, term_id)
  INDEX  (school_id, status, departs_at)
  INDEX  (school_id, status, returns_by)

exeat_quotas                          -- per learner per term
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
student_id              BIGINT       FK INDEX
term_id                 BIGINT       FK
exeat_type_id           BIGINT       FK
allowed                 SMALLINT     NOT NULL
used                    SMALLINT     NOT NULL DEFAULT 0
pending                 SMALLINT     NOT NULL DEFAULT 0
  UNIQUE (school_id, student_id, term_id, exeat_type_id)

collection_attempts                   -- APPEND-ONLY ⭐ including refusals
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
exeat_id                BIGINT       NULL FK
attempted_by_guardian_id BIGINT      NULL FK
attempted_by_name       VARCHAR(150) NOT NULL
attempted_by_id_no      VARCHAR(30)  NULL         -- ENCRYPTED
claimed_relationship    VARCHAR(60)  NULL
outcome                 VARCHAR(20)  NOT NULL     -- released|refused|escalated
refusal_reason          VARCHAR(60)  NULL         -- no_right|court_restriction|
                                                  -- no_exeat|identity_unverified|
                                                  -- blacklisted|learner_not_available
verified_by_photo       TINYINT(1)   NOT NULL DEFAULT 0
gate_staff_id           BIGINT       FK → users.id
escalated_to_staff_id   BIGINT       NULL FK
occurred_at             TIMESTAMP    NOT NULL
notes                   TEXT         NULL
  INDEX (school_id, student_id, occurred_at)
  INDEX (school_id, outcome, occurred_at)
  -- No UPDATE. No DELETE. Ever.

visitors
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
full_name               VARCHAR(150) NOT NULL
id_type                 VARCHAR(30)  NULL         -- national_id|passport|drivers_licence
id_number               VARCHAR(40)  NULL         -- ENCRYPTED
phone                   VARCHAR(30)  NULL
photo_file_id           BIGINT       NULL FK      -- captured at first visit
organisation            VARCHAR(150) NULL
is_blacklisted          TINYINT(1)   NOT NULL DEFAULT 0
blacklist_reason        TEXT         NULL
blacklisted_by          BIGINT       NULL FK
is_watchlisted          TINYINT(1)   NOT NULL DEFAULT 0
watchlist_note          TEXT         NULL
linked_guardian_id      BIGINT       NULL FK
created_at, updated_at
  INDEX (school_id, full_name)
  INDEX (school_id, is_blacklisted)

visitor_logs                          -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
visitor_id              BIGINT       FK INDEX
visit_purpose           VARCHAR(40)  NOT NULL     -- parent_visit|meeting|delivery|
                                                  -- contractor|inspection|
                                                  -- prospective_parent|medical
host_staff_id           BIGINT       NULL FK
student_id              BIGINT       NULL FK      -- who they came to see
vehicle_registration    VARCHAR(30)  NULL
badge_number            VARCHAR(30)  NULL
signed_in_at            TIMESTAMP    NOT NULL
signed_out_at           TIMESTAMP    NULL
expected_duration_mins  SMALLINT     NULL
gate_staff_in           BIGINT       FK → users.id
gate_staff_out          BIGINT       NULL FK
items_declared          VARCHAR(255) NULL
induction_completed     TINYINT(1)   NOT NULL DEFAULT 0   -- contractors
notes                   TEXT         NULL
  INDEX (school_id, signed_in_at)
  INDEX (school_id, student_id, signed_in_at)
  INDEX (school_id, signed_out_at)      -- overnight-visitor detection

visiting_days
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
visit_date              DATE         NOT NULL
name                    VARCHAR(120) NOT NULL
starts_at               TIME         NOT NULL
ends_at                 TIME         NOT NULL
slot_duration_minutes   SMALLINT     NULL         -- null = open, no booking
max_per_slot            SMALLINT     NULL
applies_to_hostels      JSON         NULL
booking_opens_at        TIMESTAMP    NULL
booking_closes_at       TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL     -- planned|open|closed|completed
  UNIQUE (school_id, visit_date)

visiting_day_bookings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
visiting_day_id         BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
guardian_id             BIGINT       FK
slot_starts_at          TIME         NOT NULL
party_size              TINYINT      NOT NULL DEFAULT 1
status                  VARCHAR(20)  NOT NULL     -- booked|attended|no_show|cancelled
booked_at               TIMESTAMP    NOT NULL
  UNIQUE (visiting_day_id, student_id, slot_starts_at)
```

### 3. ⭐ The collection authorisation check

This runs at the gate, every time, before any child leaves with any adult. It is the single most important twenty lines in this book.

```php
public function checkCollectionAuthority(
    Student $learner,
    CollectionClaim $claim,      // who is standing at the gate
    ?Exeat $exeat,
): CollectionDecision {

    // 1. Is there an approved, currently-valid exeat?
    if ($exeat === null || !$exeat->isApprovedAndCurrent()) {
        return $this->refuse('no_exeat', escalate: true);
    }

    // 2. Blacklist — absolute
    if ($claim->matchesBlacklistedVisitor()) {
        return $this->refuse('blacklisted', escalate: true, alertSecurity: true);
    }

    // 3. Named on the exeat?
    $authorised = $exeat->collecting_guardian_id
        ? $claim->matchesGuardian($exeat->collecting_guardian_id)
        : $claim->matchesOneOffPerson($exeat);

    if (!$authorised) {
        return $this->refuse('no_right', escalate: true);
    }

    // 4. ⭐ Court restriction OVERRIDES every other right (PPL-03)
    if ($claim->guardian?->relationshipTo($learner)?->has_court_restriction) {
        return $this->refuse('court_restriction', escalate: true, alertHead: true);
    }

    // 5. Standing collection right on the relationship
    if ($claim->guardian && !$claim->guardian->relationshipTo($learner)?->may_collect_learner) {
        return $this->refuse('no_right', escalate: true);
    }

    // 6. Identity verification
    if ($this->requiresPhotoId() && !$claim->identityVerified()) {
        return $this->refuse('identity_unverified', escalate: false);
    }

    return $this->release();
}
```

**Every path through this function — release or refusal — writes a `collection_attempts` row.** Refusals are not errors to be discarded; a pattern of a restricted parent repeatedly presenting at the gate is exactly the signal a safeguarding lead needs, and it only exists if refusals are recorded.

### 4. The exeat lifecycle

```
GUARDIAN (portal or app)
  │  selects learner, type, dates, destination, contact, who is collecting
  │  system pre-checks: quota, notice period, fee arrears, suspension,
  │                     collector's standing rights
  ▼
PENDING ──────────────────────────────────────────────────────────────┐
  │                                                                    │
  ├─ Housemaster verifies      (learner's conduct, no clash, plausible)│
  ├─ Boarding Master approves                                          │
  ├─ Deputy Head signs off     (only if crossing province, or > N days)│  rejected
  │                                                                    │  at any
  ▼                                                                    │  stage
APPROVED
  │  pass generated with QR + verification code
  │  guardian notified; housemaster's roll pre-populates
  ▼
GATE — DEPARTURE
  │  scan pass QR → collection authority check (§3) → record who collected
  ▼
DEPARTED
  │  roll call shows 'exeat'; movement log records boundary crossing
  ▼
returns_by passes without return
  │
  ├─► OVERDUE  → guardian + housemaster notified immediately
  │              → escalates on the configured ladder
  ▼
GATE — RETURN
  │  scan → record actual_return_at, compute late minutes
  ▼
RETURNED
```

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-03-001` | Exeats requiring guardian initiation cannot be created by staff on a guardian's behalf, except via `request_source = phone_recorded`, which requires the recording staff member's identity and is flagged on the approval screen. |
| `BR-BRD-03-002` | Approval chains are per exeat type and configured through `CORE-07`. A day pass and a half-term exeat legitimately need different scrutiny. |
| `BR-BRD-03-003` | Destination province different from the school's province, or duration exceeding the configured threshold, adds a deputy head or head step automatically. |
| `BR-BRD-03-004` | Requests inside `min_notice_hours` are flagged as short notice and require an additional approval step. Compassionate exeats bypass notice requirements by type configuration. |
| `BR-BRD-03-005` | Quota checking is per learner per term per type. Exceeding it requires override with a reason. |
| `BR-BRD-03-006` | Types with `blocks_on_fee_arrears = 1` check the balance and block above the threshold. This is off by default — a school choosing to use it is making a policy decision, and the block message must be routed to the bursary, not presented as a system error. |
| `BR-BRD-03-007` | A suspended learner cannot take exeats of types with `blocks_on_suspension = 1`. |
| `BR-BRD-03-008` | An approved exeat generates a pass carrying a QR code and a unique verification code, issued through `CORE-06`. |
| `BR-BRD-03-009` | Approval automatically pre-populates the learner's roll call status for every affected roll (`BRD-02` §4). |
| `BR-BRD-03-010` | Departure requires a gate scan or manual verification. `departure_verified_by` records the name of the person who actually collected — not the person named on the request, but the person who appeared. |
| `BR-BRD-03-011` | The collection authority check (§3) runs on **every** departure. There is no fast path and no trusted-parent bypass. |
| `BR-BRD-03-012` ⭐ | Every collection attempt writes an append-only `collection_attempts` row, **including every refusal**, with the claimed identity, the reason, and the gate staff member. No deletion path exists. |
| `BR-BRD-03-013` ⭐ | A court restriction on the guardian's relationship refuses collection regardless of every other flag, alerts the head immediately, and escalates to the safeguarding lead. |
| `BR-BRD-03-014` | A one-off authorised person requires explicit authorisation by a guardian holding `may_authorise_exeat`, captured on the request, with the collector's identity document recorded at the gate. |
| `BR-BRD-03-015` | An overdue return notifies the guardian and housemaster at `returns_by`, then escalates on the configured ladder. An exeat overdue beyond the final step opens a `BRD-02` missing-learner incident. |
| `BR-BRD-03-016` | Return is recorded at the gate with the actual timestamp. Late return minutes are computed and reported; repeated lateness feeds the pastoral record. |
| `BR-BRD-03-017` | Visitors are registered with an identity document and a photograph captured on first visit. The photograph is reused for subsequent recognition. |
| `BR-BRD-03-018` | A blacklisted visitor is refused at sign-in, security is alerted, and the attempt is logged. |
| `BR-BRD-03-019` | A watchlisted visitor is admitted but the host and the designated staff member are notified on sign-in. |
| `BR-BRD-03-020` | Visitors not signed out by the configured hour raise an alert. **Nobody stays on a boarding campus overnight unaccounted for.** |
| `BR-BRD-03-021` | Contractors require induction completion before campus access; the induction record is checked at the gate. |
| `BR-BRD-03-022` | Visiting-day slot booking prevents overcrowding and produces a per-slot expected list for the gate. |
| `BR-BRD-03-023` | Visitor logs and collection attempts are retained for the full `CMP-03` retention period. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Exeat requests | `Boarding\Exeats\Index` | `boarding.exeat.view` |
| Exeat detail | `Boarding\Exeats\Show` | `boarding.exeat.view` — request, approvals, pass, movement, return |
| Approval queue | `Boarding\Exeats\Approvals` | `boarding.exeat.approve` — batched by stage, mobile-friendly |
| **Gate terminal** | `Boarding\Gate\Terminal` | `boarding.gate.operate` ⭐ — scan QR, learner photo, collector photo, **authority check result in large, unambiguous type**, release or refuse with reason |
| Collection attempts | `Boarding\Gate\Attempts` | `boarding.gate.view` — refusals highlighted, per learner and per person |
| Overdue returns | `Boarding\Exeats\Overdue` | `boarding.exeat.view` — live, with escalation status |
| Exeat quotas | `Boarding\Exeats\Quotas` | `boarding.exeat.view` |
| Exeat types | `Boarding\Exeats\Types` | `boarding.exeat.manage` |
| **Visitor terminal** | `Boarding\Visitors\Terminal` | `boarding.visitor.manage` — sign in, ID capture, photo, badge print, blacklist check |
| Visitor log | `Boarding\Visitors\Log` | `boarding.visitor.view` — on-site now, overdue sign-outs |
| Blacklist | `Boarding\Visitors\Blacklist` | `boarding.visitor.blacklist` ⚠ |
| Visiting days | `Boarding\Visitors\VisitingDays` | `boarding.visitor.manage` — slots, bookings, expected lists |

**The gate terminal's authority result must be unmistakable.** Large type, colour-coded, one word: `RELEASE` or `DO NOT RELEASE`. A gatekeeper reading a dense screen at dusk with a queue of cars behind is the failure mode this design guards against.

### 7. API endpoints

```
POST /api/v1/exeats                          guardian: request
GET  /api/v1/exeats/mine                     guardian: my children's exeats
GET  /api/v1/exeats/{ulid}
POST /api/v1/exeats/{ulid}/cancel
GET  /api/v1/exeats/{ulid}/pass              QR pass for the phone
GET  /api/v1/exeats/pending-approval         staff approval queue
POST /api/v1/exeats/{ulid}/approve
POST /api/v1/exeats/{ulid}/reject

POST /api/v1/gate/verify                     { verification_code } → learner + authority
POST /api/v1/gate/departure                  { exeat, collector_claim }  ⭐ authority check
POST /api/v1/gate/return
POST /api/v1/gate/visitors/sign-in
POST /api/v1/gate/visitors/sign-out

GET  /api/v1/visiting-days/available         guardian
POST /api/v1/visiting-days/{ulid}/book
```

### 8. Permissions · Settings · Events

```
boarding.exeat.view            boarding.exeat.request
boarding.exeat.approve         boarding.exeat.override_quota ⚠
boarding.exeat.manage
boarding.gate.operate          boarding.gate.view
boarding.gate.override ⚠⚠      -- head only; every use alerts the safeguarding lead
boarding.visitor.view          boarding.visitor.manage
boarding.visitor.blacklist ⚠
```

| Setting | Type | Default |
|---|---|---|
| `boarding.exeat_min_notice_hours` | int | `24` |
| `boarding.exeat_escalate_if_out_of_province` | bool | `true` |
| `boarding.exeat_escalate_if_days_over` | int | `2` |
| `boarding.exeat_block_on_fee_arrears` | bool | `false` |
| `boarding.exeat_arrears_threshold_minor` | int | `0` |
| `boarding.collection_requires_photo_id` | bool | `true` |
| `boarding.overdue_escalation_minutes` | array | `[0,30,60,120]` |
| `boarding.overdue_opens_missing_incident_after_minutes` | int | `180` |
| `boarding.visitor_signout_alert_hour` | time | `19:00` |
| `boarding.visitor_photo_required` | bool | `true` |

Events: `ExeatRequested` · `ExeatApproved` · `ExeatRejected` · `LearnerDeparted` · `LearnerReturned` · `ExeatOverdue` ⚠ · `CollectionRefused` ⚠⚠ · `CourtRestrictionAttempt` ⚠⚠ · `BlacklistedVisitorAttempt` ⚠ · `VisitorNotSignedOut` ⚠

### 9. Acceptance criteria

```gherkin
AC-BRD-03-001
  Given a guardian requests a weekend exeat to a different province
  Then the approval chain automatically includes the deputy head step

AC-BRD-03-002
  Given an approved exeat
  When the roll call for that night is opened
  Then the learner's status is pre-populated as 'exeat'

AC-BRD-03-003
  Given an adult presents at the gate who is not named on the exeat
  Then release is refused with reason 'no_right'
  And a collection_attempts row is written
  And the housemaster is alerted

AC-BRD-03-004
  Given a guardian has has_court_restriction = 1 and may_collect_learner = 1
  When they present at the gate with a valid exeat naming them
  Then release is refused with reason 'court_restriction'
  And the head is alerted immediately
  And the safeguarding lead is notified

AC-BRD-03-005
  Given a collection is refused
  When any user at any permission level attempts to delete the record
  Then it is refused
  And the record persists permanently

AC-BRD-03-006
  Given an exeat's returns_by has passed with no return recorded
  Then the guardian and housemaster are notified immediately
  And escalation follows the configured ladder
  And after 180 minutes a missing-learner incident opens in BRD-02

AC-BRD-03-007
  Given a learner departs on exeat
  Then departure_verified_by records the name of the person who actually
      collected them, captured at the gate

AC-BRD-03-008
  Given a blacklisted visitor attempts to sign in
  Then sign-in is refused
  And security is alerted
  And the attempt is logged

AC-BRD-03-009
  Given a visitor has not signed out by 19:00
  Then an alert is raised
  And they appear on the on-site-now list

AC-BRD-03-010
  Given a guardian requests a fourth weekend exeat when the quota is three
  Then the request is blocked
  Unless a user with override permission approves it with a reason

AC-BRD-03-011
  Given the gate terminal completes an authority check
  Then the result renders as a single unambiguous instruction
  Legible at arm's length in poor light
```

---

# BRD-04 · Catering, Menus & Kitchen

> Feeding 900 boarders three times a day at a known cost per head. The distinguishing feature: rations scale to **who is actually present**, not to the nominal roll.

### 1. Scope

**In scope.** Cyclical menu planning, recipes and portions, per-capita scaling from live occupancy, store requisition and issue, wastage, the special dietary register, meal attendance, cost-per-boarder analytics, farm-to-kitchen transfer.

**Out of scope.** General inventory mechanics (`FIN-09`, Book H — see §0.3). Farm production (`OPS-03`, Book H). Medical dietary diagnosis (`BRD-06`).

### 2. Data model

```sql
menu_cycles
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
name                    VARCHAR(120) NOT NULL     -- 'Term 3 2026 Standard'
cycle_length_days       TINYINT      NOT NULL DEFAULT 7
starts_on               DATE         NULL
ends_on                 DATE         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1

menu_days
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
cycle_id                BIGINT       FK INDEX
cycle_day               TINYINT      NOT NULL
meal                    VARCHAR(20)  NOT NULL     -- breakfast|lunch|supper|
                                                  -- tea|snack
recipe_ids              JSON         NOT NULL
notes                   VARCHAR(255) NULL
  UNIQUE (cycle_id, cycle_day, meal)

recipes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL
name                    VARCHAR(150) NOT NULL     -- 'Sadza ne Nyama'
category                VARCHAR(30)  NOT NULL     -- staple|protein|vegetable|
                                                  -- beverage|dessert|sauce
base_servings           SMALLINT     NOT NULL     -- quantities are per this many
preparation_notes       TEXT         NULL
allergen_flags          JSON         NULL         -- ['gluten','dairy','nuts','egg']
is_vegetarian           TINYINT(1)   NOT NULL DEFAULT 0
is_halal_suitable       TINYINT(1)   NOT NULL DEFAULT 1
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

recipe_ingredients
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
recipe_id               BIGINT       FK INDEX
inventory_item_id       BIGINT       FK           -- FIN-09
quantity                DECIMAL(12,4) NOT NULL    -- per base_servings
unit                    VARCHAR(20)  NOT NULL     -- kg|g|l|ml|each
is_substitutable        TINYINT(1)   NOT NULL DEFAULT 0
substitute_item_ids     JSON         NULL
wastage_allowance_pct   DECIMAL(5,2) NOT NULL DEFAULT 0

meal_services                         -- one meal, one day, actually served
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
service_date            DATE         NOT NULL
meal                    VARCHAR(20)  NOT NULL
menu_day_id             BIGINT       NULL FK
-- ⭐ the numbers that drive everything
nominal_boarders        SMALLINT     NOT NULL DEFAULT 0   -- allocated beds
present_boarders        SMALLINT     NOT NULL DEFAULT 0   -- ⭐ BRD-02 live count
on_exeat                SMALLINT     NOT NULL DEFAULT 0
in_sick_bay             SMALLINT     NOT NULL DEFAULT 0
staff_meals             SMALLINT     NOT NULL DEFAULT 0
guest_meals             SMALLINT     NOT NULL DEFAULT 0
planned_servings        SMALLINT     NOT NULL DEFAULT 0
actual_served           SMALLINT     NULL
-- costing
issued_cost_minor       BIGINT       NULL
currency                CHAR(3)      NOT NULL
cost_per_serving_minor  BIGINT       NULL
wastage_note            VARCHAR(255) NULL
status                  VARCHAR(20)  NOT NULL     -- planned|requisitioned|issued|
                                                  -- served|closed
requisition_id          BIGINT       NULL FK      -- FIN-09
prepared_by_staff_id    BIGINT       NULL FK
  UNIQUE (school_id, service_date, meal)
  INDEX  (school_id, term_id, service_date)

meal_requisition_lines                -- what the kitchen draws
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
meal_service_id         BIGINT       FK INDEX
inventory_item_id       BIGINT       FK
required_quantity       DECIMAL(12,4) NOT NULL    -- computed from recipes × servings
issued_quantity         DECIMAL(12,4) NULL
returned_quantity       DECIMAL(12,4) NULL
wasted_quantity         DECIMAL(12,4) NULL
unit                    VARCHAR(20)  NOT NULL
unit_cost_minor         BIGINT       NULL         -- FIFO from FIN-09
line_cost_minor         BIGINT       NULL
substitution_note       VARCHAR(255) NULL

dietary_requirements                  -- ⭐ safeguarding, not preference
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
requirement_type        VARCHAR(30)  NOT NULL     -- allergy|intolerance|medical|
                                                  -- religious|ethical
severity                VARCHAR(20)  NOT NULL     -- life_threatening|severe|
                                                  -- moderate|preference
allergens               JSON         NULL         -- ['nuts','shellfish']
excluded_items          JSON         NULL
description             VARCHAR(255) NOT NULL
alternative_provision   VARCHAR(255) NULL
medical_source_id       BIGINT       NULL FK      -- BRD-06 clinical record
requires_epipen         TINYINT(1)   NOT NULL DEFAULT 0
verified_by_nurse       TINYINT(1)   NOT NULL DEFAULT 0
verified_at             TIMESTAMP    NULL
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, is_active, severity)

meal_attendance
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
meal_service_id         BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
attended                TINYINT(1)   NOT NULL DEFAULT 1
special_meal_served     TINYINT(1)   NOT NULL DEFAULT 0
recorded_at             TIMESTAMP    NOT NULL
method                  VARCHAR(20)  NOT NULL     -- rfid|qr|manual|assumed
  UNIQUE (meal_service_id, student_id)
```

### 3. ⭐ Per-capita scaling from live occupancy

The kitchen draws for who is actually eating. This is the single feature that pays for the module.

```php
public function planService(MealService $service): ServicePlan
{
    // ⭐ Live, not nominal. From BRD-02.
    $occupancy = $this->rollCall->liveOccupancy($service->service_date, $service->meal);

    $service->nominal_boarders  = $occupancy->allocated;
    $service->present_boarders  = $occupancy->present;      // ← the number used
    $service->on_exeat          = $occupancy->onExeat;
    $service->in_sick_bay       = $occupancy->inSickBay;

    $servings = $occupancy->present
              + $service->staff_meals
              + $service->guest_meals
              + $this->contingency($occupancy->present);    // configurable %

    $lines = collect();
    foreach ($this->recipesFor($service) as $recipe) {
        $factor = $servings / $recipe->base_servings;
        foreach ($recipe->ingredients as $ing) {
            $qty = $ing->quantity * $factor * (1 + $ing->wastage_allowance_pct / 100);
            $lines->push(new RequisitionLine($ing->inventory_item_id, $qty, $ing->unit));
        }
    }

    return new ServicePlan($servings, $lines->groupBy('item')->map->sum());
}
```

**The saving is real and immediate.** A school with 900 nominal boarders and 60 away on a fixture weekend that draws for 900 wastes 180 meals across three services. Over a term that is a meaningful line on the income statement, and it is the number a bursar will quote back to you when deciding whether to renew.

### 4. The store interface

The dependency described in §0.3, stated precisely.

```php
interface StoreIssuanceProvider
{
    public function checkAvailability(Collection $lines, Store $store): AvailabilityReport;
    public function createRequisition(MealService $s, Collection $lines): Requisition;
    public function issue(Requisition $r, Collection $actualQuantities): IssueResult;  // FIFO
    public function recordReturn(Requisition $r, Collection $returned): void;
    public function currentCost(int $itemId, Store $store): Money;
}
```

In planning-only mode this is backed by a null implementation that computes quantities and reports availability as unknown. Menus, dietary management and attendance all work; costing shows as unavailable rather than as zero. **Never display an uncosted meal as costing nothing.**

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-04-001` ⭐ | Servings are computed from **live present occupancy** (`BRD-02`), never from allocated beds. |
| `BR-BRD-04-002` | Learners on exeat, in the sick bay, or in hospital are excluded from the count. Sick bay meals are planned separately as a special provision. |
| `BR-BRD-04-003` | A configurable contingency percentage is added to the computed servings and is reported separately, so the school can see what it costs. |
| `BR-BRD-04-004` | Recipe quantities scale linearly from `base_servings` with the item's wastage allowance applied. |
| `BR-BRD-04-005` | Requisition quantities aggregate across all recipes in a service, per inventory item. |
| `BR-BRD-04-006` | Issue depletes stock at FIFO cost through `FIN-09` and posts a `STOCK_ISSUE` journal to the catering cost centre. |
| `BR-BRD-04-007` | Unused stock returned to the store is recorded and reverses proportionally. Wastage is recorded separately and reported, never buried in consumption. |
| `BR-BRD-04-008` | Insufficient stock warns at planning time with the shortfall named, and offers configured substitutes. A substitution is recorded. |
| `BR-BRD-04-009` ⭐ | Dietary requirements of severity `life_threatening` or `severe` are displayed on the serving terminal for every affected learner at every meal, with the learner's photograph. **This is a safeguarding control, not a convenience.** |
| `BR-BRD-04-010` | Dietary requirements sourced from a medical record require nurse verification before they are treated as clinical. Religious and ethical requirements do not. |
| `BR-BRD-04-011` | A learner with `requires_epipen = 1` is flagged to the serving terminal and to the duty staff member with the location of their medication. |
| `BR-BRD-04-012` | Cost per serving is computed from actual issued cost divided by actual servings, and trended by meal, by day, and by term. |
| `BR-BRD-04-013` | Farm produce transferred to the kitchen (`OPS-03`) enters at internal cost and posts a real journal, so both the farm's contribution and the kitchen's true cost are visible. |
| `BR-BRD-04-014` | Reorder levels on kitchen items trigger purchase requisitions through `FIN-08`. |
| `BR-BRD-04-015` | Meal attendance capture is optional per school. Where disabled, `actual_served` is entered by the kitchen manager. |
| `BR-BRD-04-016` | Published menus are visible to learners and guardians in the portal, which materially reduces the volume of enquiries a matron fields. |
| `BR-BRD-04-017` | A meal service cannot close without `actual_served` recorded, so cost per serving is always computable. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Menu cycles | `Boarding\Catering\MenuCycles` | `catering.menu.manage` |
| Menu planner | `Boarding\Catering\Planner` | `catering.menu.manage` — cycle grid, drag recipes, allergen indicators |
| Recipes | `Boarding\Catering\Recipes` | `catering.recipe.manage` — ingredients, base servings, allergens |
| **Daily service plan** | `Boarding\Catering\ServicePlan` | `catering.service.manage` ⭐ — live occupancy, computed servings, requisition preview, stock availability |
| Requisition & issue | `Boarding\Catering\Issue` | `catering.service.manage` — required vs issued vs returned vs wasted |
| **Serving terminal** | `Boarding\Catering\ServingTerminal` | `catering.serve` ⭐ — scan learner, **dietary alerts in large type with photograph**, special meal confirmation |
| Dietary register | `Boarding\Catering\Dietary` | `catering.dietary.manage` — by severity, with nurse verification status |
| Cost analytics | `Boarding\Catering\Costs` | `catering.report.view` — per boarder per day, trend, variance to budget |
| Wastage report | `Boarding\Catering\Wastage` | `catering.report.view` |
| Published menu | `Boarding\Catering\PublicMenu` | — portal view |

### 7. API endpoints

```
GET /api/v1/catering/menu                    ?from=&to=   learners and guardians
GET /api/v1/catering/my-dietary              learner: my recorded requirements
GET /api/v1/catering/service-plan            ?date=&meal= kitchen staff
POST /api/v1/catering/serving/scan           { student }  → dietary alerts ⭐
POST /api/v1/catering/services/{ulid}/issue
POST /api/v1/catering/services/{ulid}/close  { actual_served, wastage_note }
```

### 8. Permissions · Settings · Events

```
catering.menu.view           catering.menu.manage
catering.recipe.manage       catering.service.view
catering.service.manage      catering.serve
catering.dietary.view        catering.dietary.manage
catering.report.view
```

| Setting | Type | Default |
|---|---|---|
| `catering.contingency_percent` | int | `5` |
| `catering.use_live_occupancy` | bool | `true` (**cannot be disabled**) |
| `catering.meal_attendance_capture` | bool | `false` |
| `catering.publish_menu_to_portal` | bool | `true` |
| `catering.dietary_alert_min_severity` | enum | `moderate` |
| `catering.costing_enabled` | bool | depends on `FIN-09` |

Events: `ServicePlanned` · `RequisitionRaised` · `StockIssued` · `ServiceClosed` · `StockShortfall` ⚠ · `DietaryRequirementAdded` · `LifeThreateningAllergyFlagged` ⚠ · `CostPerServingExceededBudget`

### 9. Acceptance criteria

```gherkin
AC-BRD-04-001
  Given 900 allocated boarders, 60 on exeat and 8 in the sick bay
  When the lunch service plan is computed
  Then servings are based on 832 present, plus staff, guests and contingency
  And 900 is shown separately as the nominal figure

AC-BRD-04-002
  Given a recipe with base_servings 100 requiring 12kg mealie-meal
  When planning for 850 servings
  Then the requisition line is 102kg plus the wastage allowance

AC-BRD-04-003
  Given a learner has a life-threatening nut allergy
  When they are scanned at the serving terminal
  Then the alert displays in large type with their photograph
  And it appears at every meal, not only the first

AC-BRD-04-004
  Given stock is insufficient for a planned service
  Then the shortfall is named at planning time
  And configured substitutes are offered
  And any substitution used is recorded

AC-BRD-04-005
  Given costing is unavailable because FIN-09 is not yet installed
  Then the cost column shows 'unavailable'
  And never shows zero

AC-BRD-04-006
  Given 45kg of vegetables are transferred from the farm to the kitchen
  Then a journal posts at internal cost
  And both the farm's contribution and the kitchen's cost reflect it

AC-BRD-04-007
  Given a meal service is closed with actual_served recorded
  Then cost per serving is computed and trended
  And a service cannot close without that figure
```

---

# BRD-05 · Laundry & Linen

### 1. Scope

School-issued item register per learner, condition grading at issue and return, laundry cycle scheduling, collection and return reconciliation, loss and damage charging, end-of-term clearance gating.

### 2. Data model

```sql
issuable_items                        -- what the school issues
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL     -- 'MATTRESS','BLANKET','TOWEL'
name                    VARCHAR(120) NOT NULL
category                VARCHAR(30)  NOT NULL     -- bedding|linen|uniform|equipment
inventory_item_id       BIGINT       NULL FK      -- FIN-09
is_returnable           TINYINT(1)   NOT NULL DEFAULT 1
is_launderable          TINYINT(1)   NOT NULL DEFAULT 1
replacement_cost_minor  BIGINT       NULL
currency                CHAR(3)      NOT NULL
expected_lifespan_terms SMALLINT     NULL
requires_tagging        TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

learner_issued_items
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
student_id              BIGINT       FK INDEX
issuable_item_id        BIGINT       FK
tag_reference           VARCHAR(40)  NULL         -- laundry mark or barcode
quantity                SMALLINT     NOT NULL DEFAULT 1
condition_at_issue      VARCHAR(20)  NOT NULL     -- new|good|fair|poor
issued_on               DATE         NOT NULL
issued_by               BIGINT       FK → users.id
returned_on             DATE         NULL
condition_at_return     VARCHAR(20)  NULL
received_by             BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL     -- issued|returned|lost|damaged|
                                                  -- replaced|written_off
charge_minor            BIGINT       NULL
ad_hoc_charge_id        BIGINT       NULL FK      -- FIN-02
notes                   VARCHAR(255) NULL
  INDEX (school_id, student_id, status)
  INDEX (school_id, term_id, status)

laundry_cycles
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
hostel_id               BIGINT       FK
cycle_date              DATE         NOT NULL
collected_at            TIMESTAMP    NULL
returned_at             TIMESTAMP    NULL
items_collected         SMALLINT     NOT NULL DEFAULT 0
items_returned          SMALLINT     NOT NULL DEFAULT 0
items_missing           SMALLINT     NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL     -- scheduled|collected|
                                                  -- in_progress|returned|reconciled
cost_minor              BIGINT       NULL
supervised_by           BIGINT       NULL FK
  UNIQUE (school_id, hostel_id, cycle_date)

laundry_items                         -- per learner per cycle
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
cycle_id                BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
items_out               SMALLINT     NOT NULL
items_back              SMALLINT     NULL
missing_description     VARCHAR(255) NULL
resolved                TINYINT(1)   NOT NULL DEFAULT 0
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-05-001` | Issue records condition and, where the item requires tagging, a tag reference. |
| `BR-BRD-05-002` | Returnable items form part of end-of-term clearance. Outstanding items block clearance and therefore block transfer-out (`PPL-01`). |
| `BR-BRD-05-003` | Condition degrading beyond fair wear raises a damage charge through `FIN-02`, requiring approval. Normal wear over the expected lifespan is not charged. |
| `BR-BRD-05-004` | A lost item is charged at replacement cost, after approval, with the learner and guardian notified. |
| `BR-BRD-05-005` | Laundry cycles reconcile items out against items back per learner. Discrepancies are flagged and must be resolved or charged. |
| `BR-BRD-05-006` | Repeated missing items for the same learner escalate to the housemaster. |
| `BR-BRD-05-007` | Laundry cost is tracked per hostel and per term for the departmental cost centre. |
| `BR-BRD-05-008` | Items issued to a learner who withdraws mid-term must be returned or charged before final clearance. |

### 4. Screens & endpoints

| Screen | Component | Permission |
|---|---|---|
| Item catalogue | `Boarding\Linen\Items` | `linen.manage` |
| Issue and return | `Boarding\Linen\Issue` | `linen.manage` — bulk by hostel at term start and end |
| Learner items | `Boarding\Linen\LearnerItems` | `linen.view` |
| Laundry cycles | `Boarding\Laundry\Cycles` | `linen.manage` — collect, return, reconcile |
| Missing items | `Boarding\Laundry\Missing` | `linen.manage` |
| Clearance | `Boarding\Linen\Clearance` | `linen.manage` — per learner, blocks transfer |

```
GET /api/v1/boarding/my-issued-items      learner
GET /api/v1/students/{ulid}/issued-items  guardian
```

### 5. Acceptance criteria

```gherkin
AC-BRD-05-001
  Given a learner was issued a mattress, two blankets and a towel
  When end-of-term clearance runs and the towel is not returned
  Then clearance is blocked
  And a replacement charge is raised for approval

AC-BRD-05-002
  Given a blanket is returned in poor condition within its expected lifespan
  Then a damage charge is raised for approval
  And normal wear beyond that lifespan is not charged

AC-BRD-05-003
  Given a laundry cycle sent 14 items for a learner and 13 returned
  Then the discrepancy is flagged
  And must be resolved or charged before the cycle reconciles

AC-BRD-05-004
  Given a learner withdraws with outstanding issued items
  Then final clearance is blocked until they are returned or charged
```

---

## Part 3 — Domain E Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **F1** | `BRD-01` hierarchy, beds, staff assignment | Capacity derived, never entered |
| **F2** | `BRD-01` allocation engine, constraints | **Gender segregation has no override path** |
| **F3** | `BRD-01` occupancy board, waitlist, movement history | Drag-to-move with live constraint checking |
| **F4** | `BRD-01` inspections, damages, charging | Shared liability splits sum exactly |
| **F5** | `BRD-02` roll points, marking, pre-population | Every §4 source wired |
| **F6** | `BRD-02` offline sync, hardware hooks | Full day offline, no loss; hardware failure never blocks |
| **F7** | `BRD-02` escalation ladder ⭐ | **Acknowledgement does not stop the clock. Action records mandatory.** |
| **F8** | `BRD-02` movement log, live occupancy | Boundary crossings without exeat alert |
| **F9** | `BRD-03` exeat types, request, approval chains | Province and duration escalation automatic |
| **F10** | `BRD-03` gate terminal, authority check ⭐ | **`AC-BRD-03-003` and `-004` green. Refusals permanent.** |
| **F11** | `BRD-03` overdue handling, visitors, visiting days | Overdue opens a missing incident |
| **F12** | `BRD-04` menus, recipes, service planning | **Live occupancy drives servings** |
| **F13** | `BRD-04` requisition, issue, costing, dietary terminal | Life-threatening alerts at every meal |
| **F14** | `BRD-05` linen, laundry, clearance | Outstanding items block transfer |

---

## Part 4 — Domain E Acceptance Gate

### Child safety — build these to the letter

- [ ] Gender segregation cannot be overridden by any user at any permission level
- [ ] The escalation ladder advances on time; acknowledgement suppresses repeats only
- [ ] Steps requiring an action record reject bare acknowledgement
- [ ] Missing-learner incidents cannot be deleted by anyone, including Super Admin
- [ ] Collection authority is checked on every departure with no fast path
- [ ] Court restrictions override every other collection right and alert the head
- [ ] Every collection refusal is recorded permanently with claimed identity and reason
- [ ] An overdue exeat opens a missing-learner incident after the configured interval
- [ ] Life-threatening dietary alerts display at every meal with a photograph
- [ ] Blacklisted visitors are refused and the attempt logged
- [ ] Visitors not signed out by the configured hour raise an alert

### Functional

- [ ] All `AC-BRD-01-*` through `AC-BRD-05-*` pass
- [ ] Bulk allocation of 900 boarders produces drafts with every soft violation listed
- [ ] Roll call pre-populates from exeat, sick bay, hospital, fixture, detention and suspension
- [ ] Catering servings derive from live present occupancy, never allocated beds
- [ ] Damage and lost-item charges reach `FIN-02` only after approval and sum exactly
- [ ] Outstanding linen blocks clearance and therefore transfer-out

### Resilience

- [ ] Roll call completes fully offline for a whole day and syncs without loss
- [ ] Hardware failure at any reader never blocks manual marking or manual gate operation
- [ ] The gate terminal operates on a poor connection and queues movement records
- [ ] Escalation notifications reach recipients across push, WhatsApp and SMS with fallback

### Quality

- [ ] Coverage ≥ 85%; **`BRD-02` escalation and `BRD-03` authority check ≥ 95%**
- [ ] Tenancy isolation suite passes for every Domain E model
- [ ] Append-only enforcement verified at database grant level on `missing_learner_incidents`, `escalation_actions`, `collection_attempts`, `movement_log`, `visitor_logs`

---

## Appendix A — Interfaces

| Interface | Owner | Consumer | Status |
|---|---|---|---|
| `liveOccupancy(date, meal)` | `BRD-02` | `BRD-04`, `OPS-06` muster | Implemented here |
| `checkCollectionAuthority()` | `BRD-03` | Gate terminal | Implemented here |
| Roll status pre-population | `BRD-03`, `BRD-06`, `BRD-07`, `OPS-07`, `PPL-01` | `BRD-02` | `BRD-06`/`BRD-07` stubs until Book G |
| `StoreIssuanceProvider` | `FIN-09` (Book H) | `BRD-04` | Stubbed; see §0.3 |
| Farm-to-kitchen transfer | `OPS-03` (Book H) | `BRD-04` | Stubbed |
| Damage → ad hoc charge | `FIN-02` | `BRD-01`, `BRD-05` | Live |

---

## Appendix B — Remaining Books

| Book | Domain | Modules |
|---|---|---|
| **G** | Welfare & Pastoral | `BRD-06` health · `BRD-07` discipline · `BRD-08` safeguarding |
| **H** | Operations, Payroll & Compliance | `OPS-*`, `PPL-05`, `FIN-08`–`FIN-14`, `CMP-*` |
| **I** | Communication & Portals | `COM-01` → `COM-08` |
| **J** | Intelligence & SaaS Control | `INT-*`, `SAA-*` |

**Book G is the natural next step.** It completes the two roll-status stubs left open here, and `BRD-08` safeguarding carries the inverted access model flagged in Volume 1 — the one place in the system where Super Admin does not get automatic access.

---

*End of Volume 2, Book F.*

# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book C — Domain B: People & Organisation (`PPL-01` → `PPL-04`)

| Field | Value |
|---|---|
| Document | Volume 2, Book C of 10 |
| Covers | Student Information System · Guardian & Family · Admissions & Enrolment CRM · Staff & Human Resources |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Book A complete.** **Book B `FIN-01` through `FIN-04` complete** — admissions collects deposits and the SIS emits events the billing engine consumes. |
| Next book | Book D — Domain C Academic Core (`ACA-01`, `ACA-02`, `ACA-04`, `ACA-05`) |

---

## Part 0 — What This Book Is For

### 0.1 The two contracts this book must honour

Books A and B were built on assumptions about people. This book is where those assumptions become real, and it must satisfy two contracts exactly.

**Contract 1 — the billing attribute contract (`FIN-02`).**

The fee rule engine matches learners on `section`, `grade_level`, `class`, `enrolment_type`, `residency`, `pathway`, `house`, `nationality`, `gender`, `entry_cohort`, and any custom field. Every one of those must be a real, non-nullable, validated, event-emitting attribute on the learner record. A nullable `enrolment_type` would silently drop learners out of billing.

**Contract 2 — the liability contract (`FIN-03`).**

Split invoicing resolves against `fee_liabilities`, which references guardians, sponsors, employers, and organisations. `PPL-03` must produce a relationship model rich enough that `Σ(split invoices) ≡ Σ(learner charges)` always holds, in every family arrangement a Zimbabwean school actually encounters.

### 0.2 Build order

```
PPL-01  Student Information System    ← the master record everything hangs off
   ↓
PPL-03  Guardian, Family & Liability  ← without this, invoices have nobody to bill
   ↓
PPL-02  Admissions & Enrolment CRM    ← creates learners and guardians; needs both
   ↓
PPL-04  Staff & Human Resources       ← independent; can run in parallel from D-week 2
```

`PPL-05` Payroll is **not** in this book. It is specified in Book H alongside statutory compliance, because it depends on `FIN-08` and the ZIMRA tax table engine.

### 0.3 A note on data sensitivity

This book handles children's personal data. Every table here falls under the Cyber and Data Protection Act [Chapter 12:07], and `CMP-03` will later attach retention schedules and consent tracking to it. Build with that in mind now:

- National registration numbers, birth certificate numbers, and medical flags are encrypted at rest from day one, not retrofitted.
- Every bulk read of learner data writes to `data_access_log` (`CORE-08`).
- The learner portal exposes a deliberately narrower field set than the admin panel, gated by age band.

---

# PPL-01 · Student Information System ⭐

### 1. Scope

**In scope.** The learner master record, identity documents, enrolment and residency classification, class and house placement, status lifecycle, prior-school history, sibling linkage, the learner timeline, ID cards, bulk operations.

**Out of scope.** Guardian relationships (`PPL-03`). Application pipeline (`PPL-02`). Subject enrolment (`ACA-02`). Medical detail (`BRD-06` — this module holds only the actionable flags).

### 2. Data model

```sql
students
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
user_id                 BIGINT       NULL FK → users.id   -- portal login, age-gated
admission_number        VARCHAR(40)  NOT NULL   -- from CORE-06, gapless
former_admission_number VARCHAR(40)  NULL       -- carried on transfer-in

-- identity
first_name              VARCHAR(80)  NOT NULL
middle_names            VARCHAR(150) NULL
last_name               VARCHAR(80)  NOT NULL
preferred_name          VARCHAR(80)  NULL
date_of_birth           DATE         NOT NULL
gender                  VARCHAR(10)  NOT NULL   -- male | female
nationality             CHAR(2)      NOT NULL DEFAULT 'ZW'
home_language           VARCHAR(40)  NULL       -- Shona|Ndebele|English|Tonga|...
religion                VARCHAR(60)  NULL
national_registration_no VARCHAR(30) NULL       -- ENCRYPTED  🇿🇼
birth_certificate_no    VARCHAR(40)  NULL       -- ENCRYPTED
passport_no             VARCHAR(30)  NULL       -- ENCRYPTED, non-nationals
photo_file_id           BIGINT       NULL FK → files.id

-- ⭐ BILLING ATTRIBUTE CONTRACT — all NOT NULL, all event-emitting
enrolment_type          VARCHAR(20)  NOT NULL   -- FULL_TIME | PART_TIME
residency               VARCHAR(20)  NOT NULL   -- DAY | BOARDER | WEEKLY_BOARDER
section_id              BIGINT       NOT NULL FK → school_sections.id
grade_level_id          BIGINT       NOT NULL FK → grade_levels.id
class_id                BIGINT       NULL FK → school_classes.id
house_id                BIGINT       NULL FK → houses.id
pathway                 VARCHAR(20)  NULL       -- ACADEMIC | VOCATIONAL  🇿🇼 HBC
entry_cohort_year       SMALLINT     NOT NULL   -- year first enrolled here

-- status
status                  VARCHAR(20)  NOT NULL   -- applicant|enrolled|active|suspended|
                                                -- transferred|graduated|withdrawn|
                                                -- deceased|archived
status_reason_code      VARCHAR(40)  NULL
status_changed_at       TIMESTAMP    NULL
status_changed_by       BIGINT       NULL FK
enrolled_on             DATE         NULL
exited_on               DATE         NULL

-- residence
address_line_1          VARCHAR(200) NULL
address_line_2          VARCHAR(200) NULL
suburb                  VARCHAR(100) NULL
city                    VARCHAR(100) NULL
province                VARCHAR(60)  NULL
latitude                DECIMAL(10,7) NULL      -- transport zoning (OPS-01)
longitude               DECIMAL(10,7) NULL
transport_zone_id       BIGINT       NULL FK

-- welfare flags (detail lives in BRD-06; these are the actionable markers)
has_medical_alert       TINYINT(1)   NOT NULL DEFAULT 0
has_allergy_alert       TINYINT(1)   NOT NULL DEFAULT 0
has_dietary_requirement TINYINT(1)   NOT NULL DEFAULT 0
has_sen_record          TINYINT(1)   NOT NULL DEFAULT 0   -- special educational needs
has_safeguarding_flag   TINYINT(1)   NOT NULL DEFAULT 0   -- existence only; BRD-08 holds detail
is_vulnerable           TINYINT(1)   NOT NULL DEFAULT 0   -- orphan, child-headed household
blood_group             VARCHAR(5)   NULL

-- operational
rfid_tag                VARCHAR(60)  NULL UNIQUE_PER_SCHOOL
biometric_reference     VARCHAR(120) NULL
id_card_issued_at       TIMESTAMP    NULL
notes                   TEXT         NULL

created_by, updated_by, deleted_by, created_at, updated_at, deleted_at
  UNIQUE (school_id, admission_number)
  UNIQUE (school_id, rfid_tag)              -- where NOT NULL
  INDEX  (school_id, status, grade_level_id)
  INDEX  (school_id, class_id, status)
  INDEX  (school_id, enrolment_type, residency)     -- billing rule matching
  INDEX  (school_id, last_name, first_name)
  INDEX  (school_id, house_id)
  FULLTEXT (first_name, middle_names, last_name)    -- fast counter search

student_enrolments                    -- the term-by-term history ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
section_id              BIGINT       FK
grade_level_id          BIGINT       FK
class_id                BIGINT       NULL FK
house_id                BIGINT       NULL FK
enrolment_type          VARCHAR(20)  NOT NULL
residency               VARCHAR(20)  NOT NULL
pathway                 VARCHAR(20)  NULL
status                  VARCHAR(20)  NOT NULL  -- active|repeating|promoted|
                                               -- withdrawn|transferred|completed
started_on              DATE         NOT NULL
ended_on                DATE         NULL
is_repeat               TINYINT(1)   NOT NULL DEFAULT 0
attendance_percent      DECIMAL(5,2) NULL      -- denormalised at term close
created_by, created_at, updated_at
  UNIQUE (school_id, student_id, term_id)
  INDEX  (school_id, term_id, grade_level_id, status)

student_attribute_changes             -- APPEND-ONLY; billing depends on this ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
attribute               VARCHAR(40)  NOT NULL  -- enrolment_type|residency|grade_level|
                                               -- class|house|pathway|section|status
old_value               VARCHAR(80)  NULL
new_value               VARCHAR(80)  NOT NULL
effective_from          DATE         NOT NULL  -- ⭐ drives proration, NOT created_at
reason_code             VARCHAR(40)  NULL
reason                  TEXT         NULL
triggers_rebilling      TINYINT(1)   NOT NULL DEFAULT 0
rebilling_status        VARCHAR(20)  NULL      -- pending|applied|not_required|failed
changed_by              BIGINT       FK → users.id
changed_at              TIMESTAMP    NOT NULL
  INDEX (school_id, student_id, effective_from)
  INDEX (school_id, rebilling_status)

student_documents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
document_type           VARCHAR(50)  NOT NULL  -- birth_certificate|national_registration|
                                               -- passport|study_permit|transfer_letter|
                                               -- previous_report|immunisation|
                                               -- guardianship_order|court_order
file_id                 BIGINT       FK → files.id
reference_number        VARCHAR(80)  NULL      -- ENCRYPTED where identifying
issued_on               DATE         NULL
expires_on              DATE         NULL      -- 🇿🇼 permits expire
is_verified             TINYINT(1)   NOT NULL DEFAULT 0
verified_by             BIGINT       NULL FK
verified_at             TIMESTAMP    NULL
is_original_sighted     TINYINT(1)   NOT NULL DEFAULT 0
notes                   VARCHAR(255) NULL
uploaded_by, created_at
  INDEX (school_id, student_id, document_type)
  INDEX (school_id, expires_on)

student_prior_schools
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
school_name             VARCHAR(200) NOT NULL
school_type             VARCHAR(30)  NULL      -- government|council|mission|private|
                                               -- home_school|foreign
country                 CHAR(2)      NOT NULL DEFAULT 'ZW'
province                VARCHAR(60)  NULL
attended_from           DATE         NULL
attended_to             DATE         NULL
last_grade_completed    VARCHAR(30)  NULL
reason_for_leaving      VARCHAR(255) NULL
transfer_letter_file_id BIGINT       NULL FK
had_outstanding_fees    TINYINT(1)   NOT NULL DEFAULT 0
notes                   TEXT         NULL

student_prior_results                 -- carried achievement
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
prior_school_id         BIGINT       NULL FK
examination             VARCHAR(60)  NOT NULL  -- 'ZIMSEC Grade 7','ZIMSEC O-Level',
                                               -- 'Cambridge IGCSE'
exam_year               SMALLINT     NOT NULL
candidate_number        VARCHAR(40)  NULL
subject                 VARCHAR(100) NOT NULL
grade                   VARCHAR(10)  NOT NULL
points                  DECIMAL(5,2) NULL      -- A-Level points
is_verified             TINYINT(1)   NOT NULL DEFAULT 0
  INDEX (school_id, student_id, exam_year)

student_siblings                      -- symmetric linkage
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
sibling_student_id      BIGINT       FK INDEX
relationship            VARCHAR(20)  NOT NULL  -- full|half|step|adopted|cousin_treated_as
birth_order             TINYINT      NULL
linked_by               BIGINT       FK → users.id
created_at
  UNIQUE (school_id, student_id, sibling_student_id)

student_timeline                      -- denormalised aggregation for the profile view
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
academic_year_id        BIGINT       NULL
term_id                 BIGINT       NULL
event_category          VARCHAR(30)  NOT NULL  -- academic|financial|disciplinary|
                                               -- health|boarding|attendance|
                                               -- administrative|achievement
event_type              VARCHAR(60)  NOT NULL
title                   VARCHAR(200) NOT NULL
summary                 VARCHAR(500) NULL
severity                VARCHAR(20)  NULL      -- info|positive|warning|serious
source_type             VARCHAR(255) NULL
source_id               BIGINT       NULL
is_visible_to_guardian  TINYINT(1)   NOT NULL DEFAULT 0
occurred_at             TIMESTAMP    NOT NULL
recorded_by             BIGINT       NULL FK
  INDEX (school_id, student_id, occurred_at)
  INDEX (school_id, student_id, event_category)
```

### 3. ⭐ Why `student_attribute_changes` exists

The `students` table holds the learner's *current* state. Billing needs the learner's state *on a given date*.

A boarder who becomes a day scholar in week 6 of a 13-week term must be billed boarding for 5 weeks and not for 8. If the only record is `residency = 'DAY'` on the current row, that information is gone the moment it is overwritten, and the pro-rating cannot be computed or defended.

So every change to a billing-relevant attribute writes an append-only row carrying `effective_from`. `FIN-02` reads that history, not the current value, whenever it prorates.

```
students.residency               = 'DAY'          ← current state, for lists and filters
student_attribute_changes        = BOARDER → DAY, effective 2026-10-12, reason: parent request
                                                 ← the billing truth
```

**`effective_from` is not `changed_at`.** A parent tells the school on 20 October that the change took effect on 12 October. The system records both. Proration uses `effective_from`; audit uses `changed_at`.

### 4. 🇿🇼 National registration number handling

Zimbabwean national registration numbers follow a district-sequence-checkletter-district pattern. Formats vary in the wild — different digit counts, spacing, and hyphenation are all encountered on real documents, and older records differ from newer ones.

**Design decision.** The validation pattern is a **setting**, not a constant.

```
identity.national_registration_pattern   (regex, school-scoped)
identity.national_registration_required  (bool, default false)
identity.normalise_national_registration (bool, default true)
```

| # | Rule |
|---|---|
| The field is stored encrypted, and a deterministic hash column is maintained separately for duplicate detection without decryption. |
| Input is normalised (whitespace and hyphens stripped, uppercased) before hashing, so `63-123456A12` and `63 123456 A 12` are recognised as the same person. |
| Validation failure produces a **warning**, not a hard block, unless the school has set `national_registration_required`. A rural school enrolling a learner whose documents are still being processed must not be prevented from doing so. |
| Duplicate detection across the school raises a **possible duplicate learner** flag for human review. It never auto-merges. |

The same approach applies to birth certificate numbers.

### 5. Domain actions

| Action | Input | Output | Notes |
|---|---|---|---|
| `ACT-CreateStudent` | `CreateStudentData` | `Student` | Allocates admission number; creates first enrolment; emits `LearnerEnrolled` |
| `ACT-UpdateStudentProfile` | `UpdateStudentData` | `Student` | Non-billing attributes only |
| `ACT-ChangeBillingAttribute` | `ChangeAttributeData` | `AttributeChangeResult` | ⭐ The single path for `enrolment_type`, `residency`, `grade_level`, `class`, `pathway`, `section` |
| `ACT-ChangeStudentStatus` | `ChangeStatusData` | `Student` | State machine guarded |
| `ACT-AllocateToClass` | `ClassAllocationData` | `Student` | Capacity and gender-balance aware |
| `ACT-AllocateToHouse` | `HouseAllocationData` | `Student` | Balancing algorithm, sibling affinity |
| `ACT-BulkAllocateClasses` | `BulkClassAllocationData` | `BulkResult` | Draft → review → commit |
| `ACT-LinkSiblings` | `LinkSiblingsData` | `void` | Symmetric; triggers sibling discount re-evaluation |
| `ACT-AttachDocument` | `AttachDocumentData` | `StudentDocument` | |
| `ACT-VerifyDocument` | `VerifyDocumentData` | `StudentDocument` | |
| `ACT-RecordPriorSchool` | `PriorSchoolData` | `StudentPriorSchool` | |
| `ACT-ImportPriorResults` | `PriorResultsData` | `ImportResult` | |
| `ACT-TransferOut` | `TransferOutData` | `TransferResult` | Clearance check → letter → status change |
| `ACT-WithdrawStudent` | `WithdrawStudentData` | `WithdrawResult` | Emits `LearnerWithdrawn` for pro-rata credit |
| `ACT-ReadmitStudent` | `ReadmitStudentData` | `Student` | Preserves original admission number and history |
| `ACT-PromoteStudents` | `PromotionData` | `PromotionResult` | Called by the `CORE-03` roll-over handler |
| `ACT-GenerateIdCard` | `IdCardData` | `Document` | |
| `ACT-DetectPossibleDuplicates` | `DuplicateScanData` | `DuplicateReport` | |
| `ACT-MergeDuplicateStudents` | `MergeStudentsData` | `MergeResult` | ⚠⚠ Heavily restricted |

### 6. The status state machine

```
                    ┌───────────┐
                    │ APPLICANT │  (owned by PPL-02)
                    └─────┬─────┘
                          │ accept + deposit
                          ▼
                    ┌──────────┐   first day    ┌────────┐
                    │ ENROLLED │───────────────►│ ACTIVE │◄──────┐
                    └──────────┘                └───┬────┘       │
                                                    │            │ reinstate
                          ┌─────────────────────────┼────────────┤
                          │             │           │            │
                    suspend│      transfer│   withdraw│    ┌──────────┐
                          ▼             ▼           ▼     │SUSPENDED │
                   ┌───────────┐ ┌────────────┐ ┌─────────┴──┐      │
                   │ SUSPENDED │ │TRANSFERRED │ │ WITHDRAWN  │      │
                   └───────────┘ └────────────┘ └────────────┘      │
                                                                    │
                    ACTIVE ──── completes final level ────► GRADUATED
                    any    ──── (rare, guarded) ──────────► DECEASED
                    terminal states ── after retention ───► ARCHIVED
```

| Transition | Guard |
|---|---|
| → `ENROLLED` | Acceptance recorded; deposit received or explicitly waived |
| → `ACTIVE` | Term has started; class allocated |
| → `SUSPENDED` | Disciplinary decision (`BRD-07`) with approval; **billing continues** unless explicitly waived |
| → `TRANSFERRED` | Clearance passed; transfer letter issued |
| → `WITHDRAWN` | Withdrawal recorded; pro-rata credit raised |
| → `GRADUATED` | Exit-level enrolment completed |
| `SUSPENDED` → `ACTIVE` | Reinstatement approved |
| `WITHDRAWN` → `ACTIVE` | Readmission; original admission number retained |
| → `ARCHIVED` | Retention period elapsed (`CMP-03`) |

### 7. Business rules

| ID | Rule |
|---|---|
| `BR-PPL-01-001` | `admission_number` is allocated by `CORE-06`, gapless per school, immutable once assigned. |
| `BR-PPL-01-002` | A learner readmitted after withdrawal retains their original admission number. Issuing a second number to the same person destroys their financial and academic history. |
| `BR-PPL-01-003` | `enrolment_type` and `residency` are `NOT NULL` on every learner. There is no default and no "unknown" — the billing engine cannot price an unclassified learner. |
| `BR-PPL-01-004` ⭐ | Every change to `enrolment_type`, `residency`, `grade_level`, `class`, `section`, or `pathway` goes through `ACT-ChangeBillingAttribute`, writes an append-only `student_attribute_changes` row with `effective_from`, and emits an event. Direct model updates to these columns throw. |
| `BR-PPL-01-005` | `effective_from` may be backdated within the current term. Backdating into a locked period requires `students.allow_backdated_attribute_change` and flags the resulting rebilling as a prior-period adjustment. |
| `BR-PPL-01-006` | An attribute change with `triggers_rebilling = 1` dispatches to `FIN-02`. Rebilling status is tracked; a failure is reported and retried, never silently dropped. |
| `BR-PPL-01-007` | `date_of_birth` must be in the past and must produce an age within `students.min_age_years` and `students.max_age_years` for the target grade level, or produce an override-able warning. |
| `BR-PPL-01-008` | National registration and birth certificate numbers are stored encrypted with a separate deterministic hash for duplicate detection. |
| `BR-PPL-01-009` | Duplicate detection runs on create and on document attachment, matching on identifier hash, and on name plus date of birth. Matches raise a review flag; the system never auto-merges. |
| `BR-PPL-01-010` | Merging duplicate learners requires `students.merge` (vendor-and-head only), preserves both admission numbers in history, reassigns every financial and academic record to the surviving learner, and writes a permanent merge record. It cannot be undone and is refused if either learner has records in a locked period. |
| `BR-PPL-01-011` | Status transitions follow the state machine. Illegal transitions throw `InvalidStateTransitionException`. |
| `BR-PPL-01-012` | Suspension does **not** stop billing. A suspended learner still occupies a place. Waiving fees during suspension is a separate, approved decision (`FIN-03`). |
| `BR-PPL-01-013` | Withdrawal emits `LearnerWithdrawn` with the exit date; `FIN-02` raises the pro-rata credit automatically. |
| `BR-PPL-01-014` | Transfer-out requires a clearance check: library items returned, boarding property returned, fees settled or an approved arrangement in place. Each failure is listed; the head may override with a reason. |
| `BR-PPL-01-015` | A learner is never hard-deleted. Only archived, and only after the `CMP-03` retention period. |
| `BR-PPL-01-016` | Class allocation validates against `school_classes.capacity` when `structure.enforce_class_capacity` is on, and always warns when exceeded. |
| `BR-PPL-01-017` | House allocation balances by headcount, gender, and grade level, and by default places siblings in the same house. Manual override is always available. |
| `BR-PPL-01-018` | Sibling links are symmetric — linking A to B creates both directions — and trigger re-evaluation of sibling discounts in `FIN-07`. |
| `BR-PPL-01-019` | Promotion advances `grade_level.ordinal` by one. Repeating keeps the ordinal and sets `is_repeat = 1` on the new enrolment. Both produce **draft** allocations for human confirmation. |
| `BR-PPL-01-020` | A learner at an exit level who completes the year transitions to `GRADUATED` and an alumni record is created (`PPL-06`). |
| `BR-PPL-01-021` | Documents with `expires_on` generate alerts at 90, 30, and 7 days. 🇿🇼 Cross-border study permits are the common case. |
| `BR-PPL-01-022` | Welfare flags on the learner record indicate **existence only**. Detail is in `BRD-06` and `BRD-08` behind their own permissions. A class teacher sees that an allergy exists; only the nurse sees what it is. |
| `BR-PPL-01-023` | Every bulk read or export of learner data writes to `data_access_log` and, above the threshold, raises a security event. |
| `BR-PPL-01-024` | The learner portal exposes a reduced field set. Financial data, welfare flags, safeguarding markers, and guardian contact details are never returned to a learner token. |
| `BR-PPL-01-025` | Learner search must return within 300 ms on a 5,000-learner dataset, matching on partial name, admission number, national registration number, or guardian phone. The bursary counter depends on it. |

### 8. Screens

| Screen | Component | Permission |
|---|---|---|
| Learner directory | `People\Students\Index` | `students.view` — filters for every billing attribute, saved views, bulk actions |
| **Learner profile** | `People\Students\Show` | `students.view` — the single most-used screen in the product; tabbed: overview, academic, financial, guardians, boarding, health flags, discipline, documents, timeline |
| Create learner | `People\Students\Create` | `students.create` — multi-step with duplicate check before save |
| Edit profile | `People\Students\Edit` | `students.update` |
| **Change billing attribute** | `People\Students\ChangeAttribute` | `students.change_billing_attribute` ⚠ — attribute, new value, **effective date**, reason, and a **live preview of the resulting fee impact** before confirming |
| Status change | `People\Students\ChangeStatus` | `students.change_status` |
| Documents | `People\Students\Documents` | `students.document.manage` — with expiry indicators |
| Prior schooling | `People\Students\PriorHistory` | `students.view` |
| Siblings | `People\Students\Siblings` | `students.update` |
| Class allocation | `People\Allocation\Classes` | `students.allocate` — drag-and-drop between streams, live counts, gender and ability balance indicators |
| House allocation | `People\Allocation\Houses` | `students.allocate` |
| Bulk operations | `People\Students\BulkActions` | `students.bulk_update` ⚠ — always preview-then-confirm |
| Duplicate review | `People\Students\Duplicates` | `students.merge` ⚠⚠ |
| Transfer out | `People\Students\TransferOut` | `students.transfer` — clearance checklist inline |
| ID card production | `People\Students\IdCards` | `students.id_card.issue` — batch, with template |
| Learner timeline | `People\Students\Timeline` | `students.view` — filterable by category |

**The change-billing-attribute screen deserves its live fee preview.** A registrar changing a learner from boarder to day scholar should see "this will credit USD 369.23 and the learner's balance becomes USD 305.77" *before* they click confirm. Surfacing the financial consequence at the moment of the administrative decision prevents most of the fee disputes a school has.

### 9. API endpoints

```
GET  /api/v1/students                        staff scope; paginated; searchable
GET  /api/v1/students/{ulid}                 field set varies by caller role
GET  /api/v1/students/{ulid}/timeline        ?category=&from=&to=
GET  /api/v1/students/{ulid}/documents
GET  /api/v1/students/{ulid}/enrolments      term history

GET  /api/v1/me/children                     guardian token → their learners
GET  /api/v1/me/profile                      learner token → own reduced profile
PATCH /api/v1/me/profile                     learner/guardian self-service, approval-gated
```

**Field visibility by caller:**

| Field group | Staff | Guardian (own child) | Learner (self) |
|---|---|---|---|
| Name, photo, class, house | ✅ | ✅ | ✅ |
| Admission number | ✅ | ✅ | ✅ |
| Date of birth, gender | ✅ | ✅ | ✅ |
| National registration number | permission-gated | masked | ❌ |
| Address, transport zone | ✅ | ✅ | ❌ |
| Welfare flags (existence) | role-gated | own child only | ❌ |
| Safeguarding flag | `BRD-08` only | ❌ | ❌ |
| Financial balance | permission-gated | ✅ | ❌ |
| Guardian contact details | ✅ | own record only | ❌ |
| Discipline record | role-gated | ✅ | ❌ |

### 10. Permissions

```
students.view                     students.view.assigned     students.view.section
students.create                   students.update            students.change_status
students.change_billing_attribute ⚠
students.allocate                 students.bulk_update ⚠
students.document.view            students.document.manage
students.document.view_sensitive ⚠
students.transfer                 students.withdraw          students.readmit
students.merge ⚠⚠                 students.id_card.issue
students.export ⚠                 students.timeline.view
```

### 11. Settings

| Key | Type | Default |
|---|---|---|
| `students.admission_number_pattern` | string | `{SCHOOL}/{YEAR}/{SEQ:4}` |
| `students.min_age_years_ecd_a` | int | `3` |
| `students.max_age_variance_years` | int | `3` |
| `identity.national_registration_required` | bool | `false` |
| `identity.national_registration_pattern` | string | *(configurable regex)* |
| `students.required_documents_on_enrolment` | array | `[birth_certificate]` |
| `students.allow_backdated_attribute_change` | bool | `true` |
| `students.backdate_limit_days` | int | `60` |
| `students.house_auto_allocate` | bool | `true` |
| `students.house_siblings_together` | bool | `true` |
| `students.duplicate_check_on_create` | bool | `true` |
| `students.clearance_required_on_transfer` | bool | `true` |
| `students.portal_min_grade_ordinal` | int | `4` |

### 12. Events published

| Event | Consumed by |
|---|---|
| `LearnerEnrolled` | `FIN-02` (immediate pro-rated billing), `COM-09`, `ACA-02` |
| `LearnerEnrolmentTypeChanged` ⭐ | `FIN-02` (rebilling) |
| `LearnerResidencyChanged` ⭐ | `FIN-02` (rebilling), `BRD-01` (hostel allocation) |
| `LearnerGradeLevelChanged` | `FIN-02`, `ACA-02` |
| `LearnerClassChanged` | `ACA-03`, `ACA-04` |
| `LearnerPathwayChanged` 🇿🇼 | `ACA-01`, `ACA-02`, `FIN-02` |
| `LearnerStatusChanged` | `FIN-03`, `BRD-01`, `COM-09` |
| `LearnerWithdrawn` | `FIN-02` (pro-rata credit), `BRD-01`, `ACA-10` (library clearance) |
| `LearnerTransferred` | `FIN-03`, `CMP-02` |
| `LearnerGraduated` | `PPL-06` (alumni record) |
| `SiblingsLinked` | `FIN-07` (sibling discount) |
| `LearnerDocumentExpiring` | `CORE-09` |
| `PossibleDuplicateLearnerDetected` | Registrar review queue |

### 13. Acceptance criteria

```gherkin
AC-PPL-01-001
  Given a new learner is created as FULL_TIME, BOARDER, Form 3
  Then an admission number is allocated gaplessly
  And a student_enrolment row exists for the current term
  And LearnerEnrolled is emitted
  And FIN-02 raises a pro-rated invoice from the enrolment date

AC-PPL-01-002
  Given a BOARDER learner changes to DAY effective in week 6 of a 13-week term
  When the change is recorded
  Then an append-only attribute change row exists with effective_from set to that date
  And FIN-02 credits the unused boarding portion pro rata
  And the credit note's calculation note states the effective date and day fraction

AC-PPL-01-003
  Given a change is recorded on 20 October with effective_from of 12 October
  Then proration uses 12 October
  And the audit trail records 20 October as the entry date

AC-PPL-01-004
  Given code attempts to update students.residency directly
  Then the write throws
  And no attribute change row is created

AC-PPL-01-005
  Given a learner whose national registration number hash matches an existing learner
  When they are created
  Then a possible-duplicate flag is raised for review
  And both records remain intact
  And no automatic merge occurs

AC-PPL-01-006
  Given a learner is suspended
  Then billing continues unchanged
  And a separate approved waiver is required to stop it

AC-PPL-01-007
  Given a learner withdraws in week 5 of a 13-week term
  Then LearnerWithdrawn is emitted with the exit date
  And a pro-rata credit note is raised
  And their hostel bed is released

AC-PPL-01-008
  Given a learner previously withdrew and is now readmitted
  Then they retain their original admission number
  And their full financial and academic history is visible

AC-PPL-01-009
  Given a learner has outstanding library items and unpaid fees
  When transfer-out is attempted
  Then the clearance check lists both failures
  And transfer is blocked unless the head overrides with a reason

AC-PPL-01-010
  Given a learner token
  When it requests the learner's own profile
  Then no financial balance, welfare flag, safeguarding marker,
       or guardian contact detail is returned

AC-PPL-01-011
  Given a 5,000-learner dataset
  When a partial surname is searched
  Then results return within 300 ms

AC-PPL-01-012
  Given a registrar opens the change-billing-attribute screen
  When they select BOARDER → DAY with an effective date
  Then the projected credit and resulting balance are shown before confirmation
```

---

# PPL-03 · Guardian, Family & Fee Liability ⭐

> Built second, because `FIN-03` cannot issue an invoice until it knows who to bill. This module models real Zimbabwean family and sponsorship arrangements, and getting it right removes an entire class of billing dispute.

### 1. Scope

**In scope.** Guardian records, learner–guardian relationships with per-relationship rights, fee liability shares, households, sponsors and corporate payers, portal account provisioning, contact preferences, custody and court restrictions.

**Out of scope.** Invoice generation (`FIN-03`). Notification dispatch (`CORE-09`). This module supplies the *audience* and the *liability*; those modules act on it.

### 2. Why guardians are not "parents"

The naïve model — two nullable parent columns on the learner — fails immediately in this market:

- A learner living with a grandmother while both parents work in South Africa.
- Fees paid by an uncle's employer as a staff benefit.
- A mission school where a diocese sponsors twelve orphans.
- Divorced parents with a court order restricting one from collecting the child.
- A child-headed household with an NGO as the responsible party.
- A father who pays but must not receive results; a mother who receives everything but pays nothing.

Every one of these is routine. So: guardians are **independent records**, linked many-to-many, and every right — pay, collect, receive results, be contacted in an emergency — is a separate flag on the relationship, not an assumption derived from the label "father".

### 3. Data model

```sql
guardians
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
user_id                 BIGINT       NULL FK → users.id    -- portal account
guardian_type           VARCHAR(20)  NOT NULL  -- individual|organisation
-- individual
title                   VARCHAR(20)  NULL      -- Mr|Mrs|Ms|Dr|Rev|Chief
first_name              VARCHAR(80)  NULL
last_name               VARCHAR(80)  NULL
national_registration_no VARCHAR(30) NULL      -- ENCRYPTED
date_of_birth           DATE         NULL
-- organisation (employer, NGO, church, trust)
organisation_name       VARCHAR(200) NULL
organisation_type       VARCHAR(30)  NULL      -- employer|ngo|church|trust|government
registration_number     VARCHAR(60)  NULL
contact_person          VARCHAR(150) NULL
-- shared
primary_phone           VARCHAR(30)  NULL      -- E.164
alternate_phone         VARCHAR(30)  NULL
whatsapp_phone          VARCHAR(30)  NULL      -- often differs from primary  🇿🇼
email                   VARCHAR(150) NULL
address_line_1          VARCHAR(200) NULL
address_line_2          VARCHAR(200) NULL
city                    VARCHAR(100) NULL
province                VARCHAR(60)  NULL
country                 CHAR(2)      NOT NULL DEFAULT 'ZW'
is_diaspora             TINYINT(1)   NOT NULL DEFAULT 0   -- 🇿🇼 affects channel choice
occupation              VARCHAR(120) NULL
employer_name           VARCHAR(200) NULL
employer_address        VARCHAR(255) NULL
monthly_income_band     VARCHAR(30)  NULL      -- for bursary means assessment
preferred_language      VARCHAR(20)  NOT NULL DEFAULT 'en'  -- en|sn|nd
preferred_channel       VARCHAR(20)  NOT NULL DEFAULT 'whatsapp'
is_alumnus              TINYINT(1)   NOT NULL DEFAULT 0    -- admission priority
alumni_record_id        BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL DEFAULT 'active'
notes                   TEXT         NULL
created_by, updated_by, created_at, updated_at, deleted_at
  INDEX (school_id, last_name, first_name)
  INDEX (school_id, primary_phone)
  INDEX (school_id, organisation_name)
  FULLTEXT (first_name, last_name, organisation_name)

student_guardian                      -- the relationship, and its rights ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
guardian_id             BIGINT       FK INDEX
relationship            VARCHAR(30)  NOT NULL  -- father|mother|guardian|grandparent|
                                               -- uncle|aunt|sibling|sponsor|employer|
                                               -- church|ngo|foster|stepfather|stepmother
-- RIGHTS: each independently granted, never inferred from `relationship`
is_primary_contact      TINYINT(1)   NOT NULL DEFAULT 0
is_emergency_contact    TINYINT(1)   NOT NULL DEFAULT 0
is_fee_responsible      TINYINT(1)   NOT NULL DEFAULT 0
may_collect_learner     TINYINT(1)   NOT NULL DEFAULT 0
may_authorise_exeat     TINYINT(1)   NOT NULL DEFAULT 0
may_authorise_medical   TINYINT(1)   NOT NULL DEFAULT 0
may_receive_results     TINYINT(1)   NOT NULL DEFAULT 1
may_view_full_balance   TINYINT(1)   NOT NULL DEFAULT 0   -- vs own liability only
may_view_discipline     TINYINT(1)   NOT NULL DEFAULT 1
has_portal_access       TINYINT(1)   NOT NULL DEFAULT 1
-- restrictions
has_court_restriction   TINYINT(1)   NOT NULL DEFAULT 0
restriction_note        TEXT         NULL
restriction_document_id BIGINT       NULL FK → files.id
lives_with_learner      TINYINT(1)   NOT NULL DEFAULT 0
contact_priority        TINYINT      NOT NULL DEFAULT 1   -- escalation order
status                  VARCHAR(20)  NOT NULL DEFAULT 'active'
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, student_id, guardian_id)
  INDEX  (school_id, student_id, is_fee_responsible)
  INDEX  (school_id, guardian_id, status)

households                            -- for sibling discounts and combined statements
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
name                    VARCHAR(150) NOT NULL   -- 'Moyo Family'
head_guardian_id        BIGINT       NULL FK → guardians.id
address_line_1          VARCHAR(200) NULL
city                    VARCHAR(100) NULL
combined_statement      TINYINT(1)   NOT NULL DEFAULT 1
sibling_discount_eligible TINYINT(1) NOT NULL DEFAULT 1
created_at, updated_at

household_members
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
household_id            BIGINT       FK INDEX
member_type             VARCHAR(20)  NOT NULL   -- student|guardian
member_id               BIGINT       NOT NULL
joined_on               DATE         NOT NULL
left_on                 DATE         NULL
  UNIQUE (household_id, member_type, member_id)

fee_liabilities                       -- ⭐ owned here, consumed by FIN-03
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
guardian_id             BIGINT       FK          -- the paying party
component_id            BIGINT       NULL FK → fee_components.id  -- null = all others
share_type              VARCHAR(20)  NOT NULL    -- percentage|fixed|full_component|
                                                 -- residual
share_percent           DECIMAL(5,2) NULL
share_amount_minor      BIGINT       NULL
currency                CHAR(3)      NULL
priority                SMALLINT     NOT NULL DEFAULT 100   -- lower evaluates first
academic_year_id        BIGINT       NULL FK     -- null = ongoing
term_id                 BIGINT       NULL FK
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
agreement_document_id   BIGINT       NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
created_by, approved_by, created_at, updated_at
  INDEX (school_id, student_id, is_active, priority)
  INDEX (school_id, guardian_id, is_active)

sponsorships                          -- organisational funding programmes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
guardian_id             BIGINT       FK          -- the sponsoring organisation
name                    VARCHAR(200) NOT NULL    -- 'Diocese Orphan Support 2026'
sponsorship_type        VARCHAR(30)  NOT NULL    -- full|partial|component_specific|capped
budget_minor            BIGINT       NULL        -- envelope across all beneficiaries
budget_currency         CHAR(3)      NULL
committed_minor         BIGINT       NOT NULL DEFAULT 0
invoiced_minor          BIGINT       NOT NULL DEFAULT 0
paid_minor              BIGINT       NOT NULL DEFAULT 0
max_beneficiaries       SMALLINT     NULL
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL
contract_document_id    BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL    -- draft|active|suspended|completed
contact_person          VARCHAR(150)
reporting_frequency     VARCHAR(20)  NULL        -- termly|annual — sponsors want reports
created_by, created_at

sponsorship_beneficiaries
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
sponsorship_id          BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
fee_liability_id        BIGINT       NULL FK
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL
status                  VARCHAR(20)  NOT NULL    -- active|ended|withdrawn
performance_condition   VARCHAR(255) NULL        -- 'maintain 60% average'
condition_met           TINYINT(1)   NULL
  UNIQUE (sponsorship_id, student_id)

guardian_verification                 -- identity checks for collection rights
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
guardian_id             BIGINT       FK INDEX
document_type           VARCHAR(40)  NOT NULL    -- national_id|passport|drivers_licence
document_file_id        BIGINT       NULL FK
verified_by             BIGINT       NULL FK
verified_at             TIMESTAMP    NULL
photo_file_id           BIGINT       NULL FK     -- gate identification
notes                   VARCHAR(255)
```

### 4. ⭐ Liability resolution algorithm

This is the algorithm `FIN-03` calls when issuing invoices. It must be exact, because it decides who receives a bill.

```php
public function resolve(Student $student, Collection $feeLines, CarbonImmutable $at): LiabilityMap
{
    $rules = FeeLiability::query()
        ->where('student_id', $student->id)
        ->where('is_active', true)
        ->where('effective_from', '<=', $at)
        ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at))
        ->orderBy('priority')                    // lower wins
        ->get();

    $map = new LiabilityMap();

    foreach ($feeLines as $line) {
        $remaining = $line->net();               // Money

        // Pass 1 — component-specific rules, in priority order
        foreach ($rules->where('component_id', $line->component_id) as $rule) {
            $share = $this->shareOf($rule, $line, $remaining);
            if ($share->isZero()) continue;
            $map->assign($rule->guardian_id, $line, $share, $rule);
            $remaining = $remaining->minus($share);
            if ($remaining->isZero()) break;
        }

        // Pass 2 — general rules (component_id IS NULL) on what is left
        if (!$remaining->isZero()) {
            foreach ($rules->whereNull('component_id') as $rule) {
                $share = $this->shareOf($rule, $line, $remaining);
                if ($share->isZero()) continue;
                $map->assign($rule->guardian_id, $line, $share, $rule);
                $remaining = $remaining->minus($share);
                if ($remaining->isZero()) break;
            }
        }

        // Pass 3 — residual falls to the default fee-responsible guardian
        if (!$remaining->isZero()) {
            $map->assign($this->defaultResponsible($student), $line, $remaining, null);
        }
    }

    // ⭐ THE INVARIANT — asserted, always
    $map->assertTotalEquals($feeLines->sum());   // per currency

    return $map;
}
```

**Rounding.** Percentage splits use `Money::allocate()`, which distributes any remainder to the largest share first, deterministically. A 60/40 split of USD 100.01 produces 60.01 and 40.00 — never 60.00 and 40.00 with a cent lost.

### 5. Worked example

```
Learner: Tinashe Moyo — Form 4 boarder, full-time
Charges: Tuition USD 450 · Boarding USD 600 · Levy ZWG 1,200 · Sports USD 25

fee_liabilities:
  priority 10  Employer (Delta Ltd)   BOARDING       full_component
  priority 20  Father (T. Moyo)       TUITION        full_component
  priority 30  Father (T. Moyo)       (all others)   percentage 60%
  priority 40  Mother (R. Moyo)       (all others)   percentage 40%

Resolution:
  Tuition  450.00 USD  → Father    450.00 USD   (pass 1, priority 20)
  Boarding 600.00 USD  → Employer  600.00 USD   (pass 1, priority 10)
  Levy   1,200.00 ZWG  → Father    720.00 ZWG   (pass 2, 60%)
                       → Mother    480.00 ZWG   (pass 2, 40%)
  Sports    25.00 USD  → Father     15.00 USD   (pass 2, 60%)
                       → Mother     10.00 USD   (pass 2, 40%)

Invoices issued:
  INV/.../0481  Father    USD 465.00 + ZWG 720.00
  INV/.../0482  Employer  USD 600.00
  INV/.../0483  Mother    USD  10.00 + ZWG 480.00

Assertion:  USD 465 + 600 + 10 = 1,075 ✓      ZWG 720 + 480 = 1,200 ✓
```

Each party sees only their own invoice, their own statement, and their own balance — unless `may_view_full_balance` is set on their relationship.

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-PPL-03-001` | A guardian record exists independently of any learner and may be linked to many learners across the school. |
| `BR-PPL-03-002` | Every learner must have at least one active guardian relationship before enrolment can complete. |
| `BR-PPL-03-003` | Every learner must have exactly one `is_primary_contact` and at least one `is_emergency_contact`. Both may be the same person. |
| `BR-PPL-03-004` | Every learner must have at least one `is_fee_responsible` guardian. This is the residual payer of last resort. |
| `BR-PPL-03-005` ⭐ | Rights are **never** inferred from `relationship`. A father with `is_fee_responsible = 0` receives no invoice. A grandmother with `may_authorise_medical = 1` can consent to treatment. The label is descriptive; the flags are authoritative. |
| `BR-PPL-03-006` | `Σ(liability shares) ≡ Σ(learner charges)`, per currency, asserted on every invoice run. A mismatch aborts the entire set for that learner and reports it. |
| `BR-PPL-03-007` | Liability rules evaluate in `priority` ascending, component-specific before general, with any residual falling to the default fee-responsible guardian. |
| `BR-PPL-03-008` | Percentage splits use deterministic allocation. No cent is ever lost or duplicated in a split. |
| `BR-PPL-03-009` | A guardian sees only their own invoices and their own liability balance, unless `may_view_full_balance` is set. |
| `BR-PPL-03-010` | Changing liability mid-term does not retrospectively alter issued invoices. It applies from `effective_from` forward; correcting an issued invoice requires a credit note and reissue. |
| `BR-PPL-03-011` | `has_court_restriction` blocks the guardian from collecting the learner, authorising exeats, and authorising medical treatment, regardless of any other flag. The restriction always wins. |
| `BR-PPL-03-012` | 🇿🇼 The gate terminal (`BRD-03`) checks `may_collect_learner` before releasing a child, presents the guardian's verification photo, and **logs every refused attempt**. |
| `BR-PPL-03-013` | Phone numbers are normalised to E.164. A separate `whatsapp_phone` is supported because it frequently differs from the primary number in this market. |
| `BR-PPL-03-014` | A guardian with `has_portal_access = 1` on any relationship is provisioned a portal account; OTP login by phone is the default, since many guardians have no email. |
| `BR-PPL-03-015` | A guardian's portal shows all their linked learners across the school in one view. |
| `BR-PPL-03-016` | Households group learners and guardians for sibling discounts and combined statements. Household membership is dated. |
| `BR-PPL-03-017` | Sibling discount eligibility is computed from household membership and active learner status, and recomputes on any change. |
| `BR-PPL-03-018` | A sponsorship creates real `fee_liabilities` against the sponsoring organisation. Sponsored fees are **invoiced to the sponsor**, never written off. The school's income is unaffected; only the payer changes. |
| `BR-PPL-03-019` | A sponsorship budget envelope blocks commitment beyond its cap. Committed, invoiced, and paid amounts are tracked separately. |
| `BR-PPL-03-020` | A sponsorship with a performance condition flags beneficiaries who fail it, for review. It never automatically withdraws support — that is a human decision. |
| `BR-PPL-03-021` | Guardian contact details are self-updatable through the portal but changes to phone, email, and address enter an approval queue before taking effect, because these fields control notification delivery and account recovery. |
| `BR-PPL-03-022` | Deactivating a guardian relationship requires that the learner still satisfies rules 003 and 004 afterwards. The last fee-responsible guardian cannot be removed without naming a replacement. |
| `BR-PPL-03-023` | Duplicate guardian detection matches on normalised phone and on name plus national registration hash, and raises a merge candidate for review. |

### 7. Screens

| Screen | Component | Permission |
|---|---|---|
| Guardian directory | `People\Guardians\Index` | `guardians.view` |
| Guardian profile | `People\Guardians\Show` | `guardians.view` — linked learners, liabilities, invoices, statements, portal status |
| Create/edit guardian | `People\Guardians\Form` | `guardians.create` / `.update` |
| **Relationship editor** | `People\Guardians\Relationship` | `guardians.link` — a rights matrix with a checkbox per right and inline explanation of each |
| **Liability designer** | `People\Liabilities\Designer` | `finance.liability.manage` ⚠ — visual allocation across parties per component, with **live validation that shares total 100%** and a preview of resulting invoices |
| Households | `People\Households\Index` | `guardians.household.manage` |
| Sponsorships | `People\Sponsorships\Index` | `sponsorships.manage` |
| Sponsorship detail | `People\Sponsorships\Show` | `sponsorships.manage` — beneficiaries, budget utilisation, performance conditions, sponsor report generation |
| Portal access | `People\Guardians\PortalAccess` | `guardians.portal.manage` — invite, resend, reset, revoke |
| Contact update queue | `People\Guardians\UpdateQueue` | `guardians.update` — approve self-service changes |
| Duplicate review | `People\Guardians\Duplicates` | `guardians.merge` ⚠ |
| Verification | `People\Guardians\Verification` | `guardians.verify` — ID document and collection photo |

**The liability designer must refuse to save an incomplete allocation.** If the shares for a component total 87%, the screen shows the gap and names the residual payer who would absorb it. Saving a silently incomplete split is how a school discovers, three weeks after invoicing, that nobody was billed for the levy.

### 8. API endpoints

```
GET   /api/v1/me/children                    guardian → learners with balance and rights
GET   /api/v1/me/liabilities                 → what this guardian is responsible for
GET   /api/v1/me/invoices                    → own invoices only
GET   /api/v1/me/statement                   ?from=&to=  → own liability only
PATCH /api/v1/me/contact-details             → enters the approval queue
GET   /api/v1/me/notification-preferences
PUT   /api/v1/me/notification-preferences

GET   /api/v1/students/{ulid}/guardians      staff scope; rights matrix included
GET   /api/v1/students/{ulid}/collection-authorised   gate terminal; honours restrictions
```

### 9. Permissions

```
guardians.view              guardians.create           guardians.update
guardians.link              guardians.unlink           guardians.merge ⚠
guardians.verify            guardians.portal.manage
guardians.household.manage  guardians.view_sensitive ⚠
sponsorships.view           sponsorships.manage        sponsorships.report
finance.liability.view      finance.liability.manage ⚠
```

### 10. Settings

| Key | Type | Default |
|---|---|---|
| `guardians.require_emergency_contact` | bool | `true` |
| `guardians.max_per_learner` | int | `6` |
| `guardians.self_update_requires_approval` | bool | `true` |
| `guardians.default_portal_access` | bool | `true` |
| `guardians.default_login_method` | enum | `otp_phone` |
| `guardians.duplicate_check_on_create` | bool | `true` |
| `guardians.collection_requires_photo_id` | bool | `true` |
| `households.auto_group_by_address` | bool | `false` |
| `households.sibling_discount_min_learners` | int | `2` |

### 11. Events published

`GuardianCreated` · `GuardianLinked` · `GuardianUnlinked` · `LiabilityChanged` ⭐ · `PrimaryContactChanged` · `CourtRestrictionApplied` ⚠ · `HouseholdFormed` · `SponsorshipCommenced` · `SponsorshipBeneficiaryAdded` · `SponsorshipConditionBreached` · `GuardianContactUpdateRequested`

### 12. Acceptance criteria

```gherkin
AC-PPL-03-001
  Given a learner with charges of USD 1,075 and ZWG 1,200
  And liabilities of employer-boarding, father-tuition, and 60/40 on the remainder
  When invoices are issued
  Then three invoices exist
  And their totals are USD 465 + 600 + 10 and ZWG 720 + 480
  And the sum equals the learner's charges exactly in both currencies

AC-PPL-03-002
  Given a liability split totalling 87% of a component
  When I attempt to save it
  Then the designer refuses
  And names the residual payer who would absorb the 13%

AC-PPL-03-003
  Given a father with relationship 'father' and is_fee_responsible = 0
  When invoices are issued
  Then he receives no invoice
  And his portal shows no balance

AC-PPL-03-004
  Given a guardian with has_court_restriction = 1 and may_collect_learner = 1
  When the gate terminal checks collection authority
  Then collection is refused
  And the attempt is logged

AC-PPL-03-005
  Given a 60/40 percentage split of USD 100.01
  Then the shares are 60.01 and 40.00
  And their sum is exactly 100.01

AC-PPL-03-006
  Given a sponsorship covers a learner's full fees
  When invoices are issued
  Then the sponsoring organisation is invoiced
  And no write-off is posted
  And the school's fee income is unchanged

AC-PPL-03-007
  Given a sponsorship budget of USD 20,000 with USD 19,500 committed
  When a beneficiary requiring USD 1,000 is added
  Then the addition is blocked with the remaining envelope stated

AC-PPL-03-008
  Given a guardian updates their phone number in the portal
  Then the change enters the approval queue
  And notifications continue to the old number until approved

AC-PPL-03-009
  Given a learner has exactly one fee-responsible guardian
  When I attempt to deactivate that relationship
  Then it is refused until a replacement is named

AC-PPL-03-010
  Given a guardian is linked to three learners in the school
  When they log into the portal
  Then all three appear in one dashboard with individual balances
```

---

# PPL-02 · Admissions & Enrolment CRM

> Built third, because it creates both learners and guardians and collects money, so it needs `PPL-01`, `PPL-03`, and `FIN-04` already working. Commercially this is the module a head will demo to their board.

### 1. Scope

**In scope.** Public application intake, enquiry pipeline, entrance examinations, interviews, offers and acceptance, acceptance deposits converting to fee credit, waiting lists, priority rules, intake capacity, conversion analytics, one-click conversion to a learner record.

**Out of scope.** The learner record itself (`PPL-01`). Fee structures (`FIN-02`).

### 2. Data model

```sql
intakes                               -- an admissions cycle
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
name                    VARCHAR(150) NOT NULL   -- 'Form 1 Intake 2027'
grade_level_id          BIGINT       FK
opens_on                DATE         NOT NULL
closes_on               DATE         NOT NULL
target_places           SMALLINT     NOT NULL
places_offered          SMALLINT     NOT NULL DEFAULT 0
places_accepted         SMALLINT     NOT NULL DEFAULT 0
application_fee_minor   BIGINT       NULL
application_fee_currency CHAR(3)     NULL
acceptance_deposit_minor BIGINT      NULL
acceptance_deposit_currency CHAR(3)  NULL
deposit_deadline_days   SMALLINT     NOT NULL DEFAULT 14
requires_entrance_exam  TINYINT(1)   NOT NULL DEFAULT 0
requires_interview      TINYINT(1)   NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL   -- draft|open|closed|completed
public_form_enabled     TINYINT(1)   NOT NULL DEFAULT 1
public_form_slug        VARCHAR(80)  NULL UNIQUE
created_by, created_at, updated_at

enquiries                             -- top of funnel
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
intake_id               BIGINT       NULL FK
source                  VARCHAR(30)  NOT NULL   -- website|walk_in|phone|referral|
                                                -- alumni|social_media|open_day|agent
enquirer_name           VARCHAR(150) NOT NULL
enquirer_phone          VARCHAR(30)  NULL
enquirer_email          VARCHAR(150) NULL
learner_name            VARCHAR(150) NULL
learner_dob             DATE         NULL
interested_grade_level_id BIGINT     NULL FK
interested_residency    VARCHAR(20)  NULL
message                 TEXT         NULL
stage                   VARCHAR(30)  NOT NULL   -- new|contacted|information_sent|
                                                -- visit_booked|visited|applied|
                                                -- lost|converted
lost_reason             VARCHAR(60)  NULL       -- fees|distance|places_full|
                                                -- chose_other_school|no_response
assigned_to             BIGINT       NULL FK → users.id
next_follow_up_on       DATE         NULL
application_id          BIGINT       NULL FK
created_at, updated_at
  INDEX (school_id, stage, next_follow_up_on)

enquiry_activities
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
enquiry_id              BIGINT       FK INDEX
activity_type           VARCHAR(30)  NOT NULL   -- call|email|whatsapp|sms|visit|note
summary                 VARCHAR(500) NOT NULL
outcome                 VARCHAR(60)  NULL
performed_by            BIGINT       FK → users.id
occurred_at             TIMESTAMP    NOT NULL

applications
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
intake_id               BIGINT       FK INDEX
application_number      VARCHAR(40)  NOT NULL   -- gapless, CORE-06
enquiry_id              BIGINT       NULL FK
-- applicant (mirrors PPL-01 to enable zero-rekey conversion) ⭐
first_name              VARCHAR(80)  NOT NULL
middle_names            VARCHAR(150) NULL
last_name               VARCHAR(80)  NOT NULL
date_of_birth           DATE         NOT NULL
gender                  VARCHAR(10)  NOT NULL
nationality             CHAR(2)      NOT NULL DEFAULT 'ZW'
national_registration_no VARCHAR(30) NULL       -- ENCRYPTED
birth_certificate_no    VARCHAR(40)  NULL       -- ENCRYPTED
home_language           VARCHAR(40)  NULL
religion                VARCHAR(60)  NULL
address_line_1          VARCHAR(200) NULL
city                    VARCHAR(100) NULL
province                VARCHAR(60)  NULL
-- requested placement
requested_grade_level_id BIGINT      FK
requested_enrolment_type VARCHAR(20) NOT NULL   -- FULL_TIME | PART_TIME
requested_residency     VARCHAR(20)  NOT NULL   -- DAY | BOARDER | WEEKLY_BOARDER
requested_pathway       VARCHAR(20)  NULL       -- 🇿🇼 ACADEMIC | VOCATIONAL
requested_subjects      JSON         NULL       -- part-time and A-Level applicants
-- prior schooling
previous_school         VARCHAR(200) NULL
previous_grade          VARCHAR(30)  NULL
previous_results        JSON         NULL
-- priority factors
has_sibling_at_school   TINYINT(1)   NOT NULL DEFAULT 0
sibling_student_id      BIGINT       NULL FK
guardian_is_alumnus     TINYINT(1)   NOT NULL DEFAULT 0
guardian_is_staff       TINYINT(1)   NOT NULL DEFAULT 0
priority_score          DECIMAL(6,2) NULL       -- computed
-- pipeline
status                  VARCHAR(30)  NOT NULL   -- draft|submitted|fee_pending|
                                                -- under_review|exam_scheduled|
                                                -- exam_completed|interview_scheduled|
                                                -- interview_completed|offered|
                                                -- accepted|deposit_paid|enrolled|
                                                -- waitlisted|declined|withdrawn|expired
application_fee_receipt_id BIGINT     NULL FK
deposit_receipt_id      BIGINT       NULL FK
offer_made_at           TIMESTAMP    NULL
offer_expires_at        TIMESTAMP    NULL
offer_document_id       BIGINT       NULL FK
accepted_at             TIMESTAMP    NULL
declined_reason         VARCHAR(120) NULL
waitlist_position       SMALLINT     NULL
student_id              BIGINT       NULL FK    -- set on conversion
converted_at            TIMESTAMP    NULL
submitted_at            TIMESTAMP    NULL
created_by, created_at, updated_at
  UNIQUE (school_id, application_number)
  INDEX  (school_id, intake_id, status)
  INDEX  (school_id, status, priority_score)

application_guardians                 -- captured at application, converted with it
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
application_id          BIGINT       FK INDEX
relationship            VARCHAR(30)  NOT NULL
title                   VARCHAR(20)  NULL
first_name              VARCHAR(80)  NULL
last_name               VARCHAR(80)  NULL
organisation_name       VARCHAR(200) NULL
primary_phone           VARCHAR(30)  NULL
whatsapp_phone          VARCHAR(30)  NULL
email                   VARCHAR(150) NULL
occupation              VARCHAR(120) NULL
employer_name           VARCHAR(200) NULL
national_registration_no VARCHAR(30) NULL       -- ENCRYPTED
is_primary_contact      TINYINT(1)   NOT NULL DEFAULT 0
is_fee_responsible      TINYINT(1)   NOT NULL DEFAULT 0
existing_guardian_id    BIGINT       NULL FK    -- matched to an existing record

application_documents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
application_id          BIGINT       FK INDEX
document_type           VARCHAR(50)  NOT NULL
file_id                 BIGINT       FK → files.id
is_verified             TINYINT(1)   NOT NULL DEFAULT 0
verified_by             BIGINT       NULL FK

entrance_exams
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
intake_id               BIGINT       FK
name                    VARCHAR(150) NOT NULL
exam_date               DATE         NOT NULL
start_time              TIME         NOT NULL
venue                   VARCHAR(150)
capacity                SMALLINT     NULL
papers                  JSON         NOT NULL   -- [{subject, max_mark, weight}]
pass_mark_percent       DECIMAL(5,2) NULL
status                  VARCHAR(20)  NOT NULL   -- scheduled|in_progress|marked|published

entrance_exam_candidates
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
exam_id                 BIGINT       FK INDEX
application_id          BIGINT       FK INDEX
candidate_number        VARCHAR(30)  NOT NULL
seat_number             VARCHAR(20)  NULL
attended                TINYINT(1)   NULL
marks                   JSON         NULL       -- {subject: mark}
total_mark              DECIMAL(6,2) NULL
percentage              DECIMAL(5,2) NULL
rank_in_exam            SMALLINT     NULL
  UNIQUE (exam_id, application_id)

interviews
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
application_id          BIGINT       FK INDEX
scheduled_at            TIMESTAMP    NOT NULL
venue                   VARCHAR(150)
panel_user_ids          JSON         NOT NULL
attended                TINYINT(1)   NULL
scores                  JSON         NULL       -- {criterion: score}
total_score             DECIMAL(6,2) NULL
recommendation          VARCHAR(20)  NULL       -- accept|waitlist|decline
panel_notes             TEXT         NULL
completed_at            TIMESTAMP    NULL
```

### 3. ⭐ Conversion: zero re-keying

The single highest-value feature in this module. Every field on `applications` and `application_guardians` mirrors its counterpart on `students` and `guardians`, so acceptance converts in one transaction with no data re-entry.

```
ACT-ConvertApplicationToStudent  (single transaction)

 1  Verify status = 'deposit_paid' (or deposit explicitly waived)
 2  Verify the intake's grade level still has capacity
 3  Allocate admission number (CORE-06)
 4  Create students row from application fields
      enrolment_type, residency, pathway carried straight across ⭐
 5  For each application_guardian:
      match to existing guardian (phone / national registration hash)
      → link if matched, create if not
      → create student_guardian with the captured rights
 6  Create fee_liabilities from is_fee_responsible flags
 7  Copy application_documents → student_documents (files are referenced, not re-uploaded)
 8  Copy previous_results → student_prior_results
 9  Create student_enrolment for the target term
10  ⭐ Convert the acceptance deposit:
       reallocate the deposit receipt from Refundable Deposits
       to the learner's fee account as a credit
11  If requested_subjects present → create ACA-02 subject enrolments
12  Link sibling if sibling_student_id present
13  Emit LearnerEnrolled  → FIN-02 raises the first invoice, net of the deposit credit
14  Application → 'enrolled', student_id set, intake counters incremented
```

Step 10 matters. A parent who paid a USD 200 acceptance deposit in October must see that USD 200 on their January invoice. Deposits that vanish between admissions and finance are a classic source of the complaint *"we already paid that"*.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-PPL-02-001` | The public application form is unauthenticated, rate-limited, CAPTCHA-protected, and writes only to `applications`. It can never touch `students`. |
| `BR-PPL-02-002` | Application numbers are gapless per school and allocated on submission, not on draft creation. |
| `BR-PPL-02-003` | Where an application fee is configured, the application remains `fee_pending` and is invisible to the review queue until payment is received. |
| `BR-PPL-02-004` | Application fees are non-refundable by default and post to income on receipt. Acceptance deposits are **refundable liabilities** and post to Refundable Deposits until conversion. The two are never conflated. |
| `BR-PPL-02-005` | `priority_score` is computed from configurable weights: sibling at school, guardian alumnus, guardian staff, entrance exam percentage, interview score, distance, application date. The formula is a setting and the computed breakdown is visible on every application. |
| `BR-PPL-02-006` | Offers carry an expiry. An unaccepted offer expires automatically and the place returns to the pool, promoting the next waitlisted applicant. |
| `BR-PPL-02-007` | Acceptance requires the deposit within `deposit_deadline_days`, or the offer lapses. A head may waive or extend, with a reason. |
| `BR-PPL-02-008` | Conversion is refused unless the intake's grade level has capacity, unless overridden with `admissions.override_capacity` and a reason. |
| `BR-PPL-02-009` | Conversion is a single transaction. A partial conversion is impossible. |
| `BR-PPL-02-010` | Guardian matching on conversion uses normalised phone and national registration hash. A match links the existing guardian rather than creating a duplicate. |
| `BR-PPL-02-011` | The acceptance deposit converts to a fee credit on the learner's account, reducing the first invoice. It is never left as an orphan liability. |
| `BR-PPL-02-012` | A declined or withdrawn applicant's data is retained for the `CMP-03` retention period, then purged. Unsuccessful applicants' data is not kept indefinitely. |
| `BR-PPL-02-013` | Waitlist position is derived from `priority_score` and recomputes whenever a place is released or a score changes. |
| `BR-PPL-02-014` | Entrance exam marks feed `priority_score` automatically on publication. |
| `BR-PPL-02-015` | An applicant can track their own status through a public link keyed on application number plus date of birth, without an account. Most parents will not create one. |
| `BR-PPL-02-016` | Every stage transition notifies the applicant on their preferred channel. |
| `BR-PPL-02-017` | Part-time applicants must supply `requested_subjects`; the count is shown alongside the indicative fee at application time, so the family knows the cost before they commit. |
| `BR-PPL-02-018` | 🇿🇼 A-Level applicants' subject choices validate against pathway rules and the Heritage-Based Curriculum subject-count limits from `ACA-01`. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Intake setup | `Admissions\Intakes\Index` | `admissions.intake.manage` |
| **Enquiry pipeline** | `Admissions\Enquiries\Board` | `admissions.enquiry.manage` — kanban by stage, follow-up dates, owner |
| Application list | `Admissions\Applications\Index` | `admissions.application.view` — filter by status, priority, exam result |
| Application detail | `Admissions\Applications\Show` | `admissions.application.view` — full record, documents, exam, interview, priority breakdown, action bar |
| Review queue | `Admissions\Applications\Review` | `admissions.application.review` — ranked by priority, bulk offer |
| Entrance exam | `Admissions\Exams\Manage` | `admissions.exam.manage` — scheduling, seating, mark capture, ranking |
| Interviews | `Admissions\Interviews\Schedule` | `admissions.interview.manage` — panel scorecards |
| Offers | `Admissions\Offers\Manage` | `admissions.offer.make` ⚠ — generate, send, track, expire |
| Waiting list | `Admissions\Waitlist\Index` | `admissions.application.review` |
| **Conversion** | `Admissions\Applications\Convert` | `admissions.convert` ⚠ — preflight checks, guardian match preview, deposit credit preview, one confirm |
| Funnel analytics | `Admissions\Reports\Funnel` | `admissions.report.view` — enquiry → application → offer → acceptance → enrolment, with loss reasons |

### 6. API endpoints

```
POST /api/v1/public/applications              PUBLIC, rate-limited, CAPTCHA
GET  /api/v1/public/intakes                   PUBLIC — open intakes and requirements
GET  /api/v1/public/applications/track        PUBLIC — application_number + date_of_birth
POST /api/v1/public/applications/{ulid}/documents   PUBLIC, signed upload
POST /api/v1/public/enquiries                 PUBLIC

GET  /api/v1/admissions/applications          staff
POST /api/v1/admissions/applications/{ulid}/offer
POST /api/v1/admissions/applications/{ulid}/convert
```

### 7. Acceptance criteria

```gherkin
AC-PPL-02-001
  Given a public application is submitted
  Then an application record is created
  And no student record exists
  And a gapless application number is issued

AC-PPL-02-002
  Given an application fee is configured
  When the fee is unpaid
  Then the application does not appear in the review queue
  And the applicant is told what is outstanding

AC-PPL-02-003
  Given an applicant pays a USD 200 acceptance deposit
  Then it posts to Refundable Deposits, not to income
  And on conversion it becomes a credit on the learner's fee account
  And the first invoice is net of USD 200

AC-PPL-02-004
  Given an accepted application with two guardians, one already in the system
  When it is converted
  Then the existing guardian is linked, not duplicated
  And the new guardian is created
  And fee liabilities are created from the captured is_fee_responsible flags

AC-PPL-02-005
  Given an application specifies PART_TIME with three subjects
  When it is converted
  Then the learner is created as PART_TIME
  And three subject enrolments exist
  And FIN-02 bills per subject, not a flat fee

AC-PPL-02-006
  Given an offer expires unaccepted
  Then the place returns to the pool
  And the next waitlisted applicant is promoted and notified

AC-PPL-02-007
  Given conversion fails at the guardian-matching step
  Then no student record exists
  And no admission number is consumed
  And the application status is unchanged

AC-PPL-02-008
  Given a part-time applicant selects four subjects on the public form
  Then the indicative termly fee is shown before submission
```

---

# PPL-04 · Staff & Human Resources

> Independent of the learner chain, so it can be built in parallel from week two. It supplies the teacher identity that `ACA-02` allocates to subjects and the establishment that `PPL-05` pays.

### 1. Scope

**In scope.** Staff master records, contracts, establishment and posts, departments and reporting lines, teacher–subject–class allocation with workload, leave, duty rosters, appraisal and observation, disciplinary and grievance, training, document expiry.

**Out of scope.** Payroll and statutory deductions (`PPL-05`, Book H). Lesson observation rubrics in depth (`ACA-11`).

### 2. Data model

```sql
staff
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
user_id                 BIGINT       NULL FK → users.id
staff_number            VARCHAR(40)  NOT NULL   -- gapless, CORE-06
-- identity
title                   VARCHAR(20)  NULL
first_name              VARCHAR(80)  NOT NULL
middle_names            VARCHAR(150) NULL
last_name               VARCHAR(80)  NOT NULL
preferred_name          VARCHAR(80)  NULL
date_of_birth           DATE         NOT NULL
gender                  VARCHAR(10)  NOT NULL
nationality             CHAR(2)      NOT NULL DEFAULT 'ZW'
national_registration_no VARCHAR(30) NULL       -- ENCRYPTED
passport_no             VARCHAR(30)  NULL       -- ENCRYPTED
marital_status          VARCHAR(20)  NULL
photo_file_id           BIGINT       NULL FK
-- contact
primary_phone           VARCHAR(30)  NOT NULL
alternate_phone         VARCHAR(30)  NULL
personal_email          VARCHAR(150) NULL
work_email              VARCHAR(150) NULL
address_line_1          VARCHAR(200) NULL
city                    VARCHAR(100) NULL
province                VARCHAR(60)  NULL
-- next of kin
kin_name                VARCHAR(150) NULL
kin_relationship        VARCHAR(40)  NULL
kin_phone               VARCHAR(30)  NULL
kin_address             VARCHAR(255) NULL
-- employment
staff_category          VARCHAR(30)  NOT NULL   -- teaching|administration|boarding|
                                                -- catering|maintenance|transport|
                                                -- security|health|farm|ancillary
department_id           BIGINT       NULL FK
post_id                 BIGINT       NULL FK → establishment_posts.id
reports_to_staff_id     BIGINT       NULL FK → staff.id
joined_on               DATE         NOT NULL
confirmed_on            DATE         NULL       -- end of probation
exited_on               DATE         NULL
exit_reason             VARCHAR(60)  NULL       -- resignation|contract_end|retirement|
                                                -- dismissal|redundancy|death
status                  VARCHAR(20)  NOT NULL   -- probation|active|on_leave|suspended|
                                                -- notice|exited|archived
-- 🇿🇼 statutory identifiers (used by PPL-05)
zimra_bp_number         VARCHAR(30)  NULL       -- ENCRYPTED
nssa_number             VARCHAR(30)  NULL       -- ENCRYPTED
nec_membership_number   VARCHAR(30)  NULL
pension_scheme          VARCHAR(60)  NULL
medical_aid_provider    VARCHAR(80)  NULL
medical_aid_number      VARCHAR(40)  NULL       -- ENCRYPTED
-- banking (payroll)
bank_name               VARCHAR(80)  NULL
bank_branch             VARCHAR(80)  NULL
bank_account_number     VARCHAR(40)  NULL       -- ENCRYPTED
bank_account_currency   CHAR(3)      NULL
-- teaching identity
is_teaching             TINYINT(1)   NOT NULL DEFAULT 0
teacher_registration_no VARCHAR(40)  NULL       -- 🇿🇼 professional registration
max_weekly_periods      SMALLINT     NULL       -- workload ceiling
notes                   TEXT         NULL
created_by, updated_by, created_at, updated_at, deleted_at
  UNIQUE (school_id, staff_number)
  INDEX  (school_id, status, staff_category)
  INDEX  (school_id, department_id)
  FULLTEXT (first_name, last_name)

departments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
parent_id               BIGINT       NULL FK
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL   -- 'Sciences','Bursary','Boarding'
type                    VARCHAR(20)  NOT NULL   -- academic|administrative|operational
head_staff_id           BIGINT       NULL FK → staff.id
cost_centre_id          BIGINT       NULL FK    -- links to FIN-01
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

establishment_posts                   -- approved staffing levels
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
department_id           BIGINT       NULL FK
title                   VARCHAR(120) NOT NULL   -- 'Senior Teacher — Mathematics'
grade                   VARCHAR(30)  NULL       -- salary grade
approved_count          SMALLINT     NOT NULL DEFAULT 1
filled_count            SMALLINT     NOT NULL DEFAULT 0
is_teaching             TINYINT(1)   NOT NULL DEFAULT 0
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, department_id)

staff_contracts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
staff_id                BIGINT       FK INDEX
contract_type           VARCHAR(30)  NOT NULL   -- permanent|fixed_term|relief|
                                                -- part_time|attachment|volunteer
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL       -- null = permanent
probation_months        TINYINT      NULL
notice_period_days      SMALLINT     NOT NULL DEFAULT 30
weekly_hours            DECIMAL(5,2) NULL
basic_salary_minor      BIGINT       NULL       -- ENCRYPTED
salary_currency         CHAR(3)      NULL
salary_grade            VARCHAR(30)  NULL
salary_notch            VARCHAR(20)  NULL
contract_document_id    BIGINT       NULL FK
signed_on               DATE         NULL
status                  VARCHAR(20)  NOT NULL   -- draft|active|expiring|expired|
                                                -- terminated|renewed
renewed_to_contract_id  BIGINT       NULL FK
terminated_on           DATE         NULL
termination_reason      VARCHAR(120) NULL
created_by, approved_by, created_at
  INDEX (school_id, staff_id, status)
  INDEX (school_id, ends_on, status)

staff_qualifications
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
staff_id                BIGINT       FK INDEX
qualification_type      VARCHAR(30)  NOT NULL   -- certificate|diploma|degree|
                                                -- postgraduate|professional
title                   VARCHAR(200) NOT NULL
institution             VARCHAR(200) NOT NULL
country                 CHAR(2)      NOT NULL DEFAULT 'ZW'
year_obtained           SMALLINT     NULL
grade_class             VARCHAR(40)  NULL
subjects                JSON         NULL       -- teaching subjects covered
certificate_file_id     BIGINT       NULL FK
is_verified             TINYINT(1)   NOT NULL DEFAULT 0
verified_by             BIGINT       NULL FK

staff_documents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
staff_id                BIGINT       FK INDEX
document_type           VARCHAR(50)  NOT NULL   -- contract|national_id|police_clearance|
                                                -- medical_certificate|teaching_certificate|
                                                -- work_permit|drivers_licence|
                                                -- food_handler_certificate
file_id                 BIGINT       FK
reference_number        VARCHAR(80)  NULL
issued_on               DATE         NULL
expires_on              DATE         NULL
is_verified             TINYINT(1)   NOT NULL DEFAULT 0
  INDEX (school_id, expires_on)

teacher_allocations                   -- ⭐ consumed by ACA-02 and ACA-03
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
staff_id                BIGINT       FK INDEX
subject_id              BIGINT       FK          -- ACA-01
class_id                BIGINT       FK          -- school_classes
role                    VARCHAR(20)  NOT NULL    -- teacher|assistant|moderator
weekly_periods          SMALLINT     NOT NULL
is_class_teacher        TINYINT(1)   NOT NULL DEFAULT 0
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL
status                  VARCHAR(20)  NOT NULL    -- active|ended|reassigned
allocated_by            BIGINT       FK → users.id
  UNIQUE (school_id, term_id, staff_id, subject_id, class_id)
  INDEX  (school_id, term_id, class_id)

staff_workload                        -- derived cache, per term
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
staff_id                BIGINT       FK INDEX
term_id                 BIGINT       FK
teaching_periods        SMALLINT     NOT NULL DEFAULT 0
class_teacher_count     TINYINT      NOT NULL DEFAULT 0
duty_count              SMALLINT     NOT NULL DEFAULT 0
subject_count           TINYINT      NOT NULL DEFAULT 0
class_count             TINYINT      NOT NULL DEFAULT 0
learner_count           SMALLINT     NOT NULL DEFAULT 0
utilisation_percent     DECIMAL(5,2) NULL
is_overloaded           TINYINT(1)   NOT NULL DEFAULT 0
recalculated_at         TIMESTAMP
  UNIQUE (school_id, staff_id, term_id)

leave_types
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL   -- ANN|SICK|MAT|PAT|COMP|STUDY|UNPAID
name                    VARCHAR(80)  NOT NULL
annual_entitlement_days DECIMAL(5,1) NULL
accrual_method          VARCHAR(20)  NOT NULL   -- annual|monthly|none
is_paid                 TINYINT(1)   NOT NULL DEFAULT 1
requires_document       TINYINT(1)   NOT NULL DEFAULT 0   -- sick note
max_consecutive_days    SMALLINT     NULL
carry_forward_days      DECIMAL(5,1) NULL
requires_cover          TINYINT(1)   NOT NULL DEFAULT 1
applies_to_categories   JSON         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

leave_balances
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
staff_id                BIGINT       FK INDEX
leave_type_id           BIGINT       FK
academic_year_id        BIGINT       FK
entitlement_days        DECIMAL(5,1) NOT NULL DEFAULT 0
accrued_days            DECIMAL(5,1) NOT NULL DEFAULT 0
carried_forward_days    DECIMAL(5,1) NOT NULL DEFAULT 0
taken_days              DECIMAL(5,1) NOT NULL DEFAULT 0
pending_days            DECIMAL(5,1) NOT NULL DEFAULT 0
available_days          DECIMAL(5,1) NOT NULL DEFAULT 0
  UNIQUE (school_id, staff_id, leave_type_id, academic_year_id)

leave_requests
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
staff_id                BIGINT       FK INDEX
leave_type_id           BIGINT       FK
academic_year_id        BIGINT       FK
starts_on               DATE         NOT NULL
ends_on                 DATE         NOT NULL
working_days            DECIMAL(5,1) NOT NULL
reason                  TEXT         NULL
supporting_document_id  BIGINT       NULL FK
cover_staff_id          BIGINT       NULL FK → staff.id
status                  VARCHAR(20)  NOT NULL   -- draft|pending|approved|rejected|
                                                -- cancelled|taken
approval_request_id     BIGINT       NULL FK    -- CORE-07
contact_while_away      VARCHAR(120) NULL
created_at
  INDEX (school_id, staff_id, starts_on)
  INDEX (school_id, status, starts_on)

duty_rosters
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
duty_type               VARCHAR(40)  NOT NULL   -- teacher_on_duty|boarding_master|
                                                -- weekend_duty|prep_supervision|
                                                -- dining_hall|assembly|invigilation|
                                                -- transport_escort
name                    VARCHAR(120) NOT NULL
rotation_pattern        VARCHAR(20)  NOT NULL   -- daily|weekly|weekend|custom
eligible_categories     JSON         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1

duty_assignments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
roster_id               BIGINT       FK INDEX
staff_id                BIGINT       FK INDEX
starts_at               TIMESTAMP    NOT NULL
ends_at                 TIMESTAMP    NOT NULL
status                  VARCHAR(20)  NOT NULL   -- assigned|swapped|completed|missed
swapped_with_staff_id   BIGINT       NULL FK
swap_approved_by        BIGINT       NULL FK
notes                   TEXT         NULL
  INDEX (school_id, staff_id, starts_at)
  INDEX (school_id, roster_id, starts_at)

staff_appraisals
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK
staff_id                BIGINT       FK INDEX
academic_year_id        BIGINT       FK
cycle                   VARCHAR(30)  NOT NULL   -- probation|mid_year|annual
appraiser_staff_id      BIGINT       FK
self_assessment         JSON         NULL
appraiser_assessment    JSON         NULL
objectives              JSON         NULL
overall_rating          VARCHAR(30)  NULL
development_plan        TEXT         NULL
staff_comments          TEXT         NULL
status                  VARCHAR(20)  NOT NULL   -- draft|self_assessment|
                                                -- appraiser_review|meeting_held|
                                                -- signed_off
signed_off_at           TIMESTAMP    NULL

staff_disciplinary_cases
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK
staff_id                BIGINT       FK INDEX
case_number             VARCHAR(40)  NOT NULL
category                VARCHAR(60)  NOT NULL
description             TEXT         NOT NULL
incident_date           DATE         NOT NULL
reported_by             BIGINT       FK → users.id
stage                   VARCHAR(30)  NOT NULL   -- reported|investigation|hearing|
                                                -- decided|appealed|closed
outcome                 VARCHAR(40)  NULL       -- no_case|verbal_warning|
                                                -- written_warning|final_warning|
                                                -- suspension|dismissal
outcome_date            DATE         NULL
is_confidential         TINYINT(1)   NOT NULL DEFAULT 1
created_at
```

### 3. ⭐ Workload calculation

The teacher–subject–class allocation matrix is where a school discovers it has given one teacher 38 periods and another 12. The workload cache makes that visible before the timetable is generated, not after.

```php
public function recalculate(Staff $staff, Term $term): StaffWorkload
{
    $allocations = TeacherAllocation::where('staff_id', $staff->id)
        ->where('term_id', $term->id)
        ->where('status', 'active')
        ->with('class')
        ->get();

    $periods   = $allocations->sum('weekly_periods');
    $ceiling   = $staff->max_weekly_periods
                 ?? Setting::get('staff.default_max_weekly_periods');   // default 30

    return StaffWorkload::updateOrCreate(
        ['staff_id' => $staff->id, 'term_id' => $term->id],
        [
            'teaching_periods'    => $periods,
            'subject_count'       => $allocations->unique('subject_id')->count(),
            'class_count'         => $allocations->unique('class_id')->count(),
            'learner_count'       => $allocations->sum(fn($a) => $a->class->active_learner_count),
            'class_teacher_count' => $allocations->where('is_class_teacher', true)->count(),
            'duty_count'          => $this->dutyCountFor($staff, $term),
            'utilisation_percent' => $ceiling > 0 ? round($periods / $ceiling * 100, 2) : null,
            'is_overloaded'       => $periods > $ceiling,
            'recalculated_at'     => now(),
        ],
    );
}
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-PPL-04-001` | `staff_number` is gapless per school and immutable. |
| `BR-PPL-04-002` | A staff member has at most one active contract at a time. Renewal creates a new contract linked to the prior one. |
| `BR-PPL-04-003` | Contracts expiring within `staff.contract_expiry_warning_days` (default 60) alert the head and the bursar. An expired contract sets the staff member to `notice` and blocks payroll inclusion until resolved. |
| `BR-PPL-04-004` | Filling a post increments `filled_count`. Exceeding `approved_count` requires `staff.override_establishment` and a reason — a school that quietly overstaffs cannot budget. |
| `BR-PPL-04-005` | Teacher allocation validates that the staff member is `is_teaching = 1`, holds a qualification covering the subject (warning, not block), and would not exceed their period ceiling. |
| `BR-PPL-04-006` | Exceeding the ceiling produces a warning with the current and proposed load. It blocks only when `staff.enforce_workload_ceiling` is on. |
| `BR-PPL-04-007` | A class may have exactly one `is_class_teacher = 1` allocation per term. |
| `BR-PPL-04-008` | Workload recalculates on every allocation change and on term roll-over. |
| `BR-PPL-04-009` | Leave balances accrue per `accrual_method`. Monthly accrual runs on the first of each month; annual accrual on the academic year start. |
| `BR-PPL-04-010` | A leave request is validated against the available balance. Requests exceeding it require `staff.approve_leave_overdraft`. |
| `BR-PPL-04-011` | Requested days are moved from `available` to `pending` on submission, to `taken` on approval, and released on rejection or cancellation. The balance is never double-counted. |
| `BR-PPL-04-012` | Leave types with `requires_document = 1` reject submission without the document, or accept with a grace period configurable per school (a teacher who falls ill on Monday cannot upload a sick note that morning). |
| `BR-PPL-04-013` | Leave for a teacher with `requires_cover = 1` triggers cover assignment. Uncovered teaching periods appear on the daily cover report for the deputy head. |
| `BR-PPL-04-014` | Approved leave marks the staff member `on_leave` for its duration and feeds `ACA-03` substitution and `PPL-05` payroll where unpaid. |
| `BR-PPL-04-015` | Duty rosters generate with fairness balancing — the count of duties per staff member per term is levelled — and weekend duty is distributed evenly across eligible staff. |
| `BR-PPL-04-016` | Duty swaps require both parties' consent and approval by the roster owner. Both original and swapped assignments remain visible. |
| `BR-PPL-04-017` | A staff member on approved leave is excluded from duty rostering for that period. |
| `BR-PPL-04-018` | Staff documents with `expires_on` alert at 90, 30, and 7 days. Expired police clearance, medical certificate, or work permit flags the staff member for compliance review. 🇿🇼 Food handlers' certificates are tracked for kitchen staff. |
| `BR-PPL-04-019` | Salary, banking, and statutory identifiers are encrypted at rest, visible only with `staff.view_compensation`, and redacted in every export not explicitly authorised. |
| `BR-PPL-04-020` | Disciplinary cases are confidential by default, visible only to the head, the deputy, and the case handler. Every access writes to `data_access_log`. |
| `BR-PPL-04-021` | Exit processing requires a clearance checklist — keys, assets, laptop, library items, staff advances settled — before final pay is released. |
| `BR-PPL-04-022` | An exited staff member's user account is deactivated and all tokens revoked on their exit date, automatically. |
| `BR-PPL-04-023` | A staff member who is also a guardian at the school is a single `users` row linked to both a `staff` and a `guardian` record. Their staff-child discount resolves through `FIN-07` from that link. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Staff directory | `People\Staff\Index` | `staff.view` |
| Staff profile | `People\Staff\Show` | `staff.view` — tabs: personal, employment, qualifications, allocations, workload, leave, duties, appraisal, documents |
| Create/edit staff | `People\Staff\Form` | `staff.create` / `.update` |
| Contracts | `People\Staff\Contracts` | `staff.contract.manage` — with expiry dashboard |
| Departments & establishment | `People\Establishment\Index` | `staff.establishment.manage` — approved vs filled, vacancy list |
| **Allocation matrix** | `People\Allocation\TeacherMatrix` | `staff.allocate` ⭐ — subjects × classes grid, drag teachers in, **live period counters per teacher with overload highlighting** |
| Workload report | `People\Staff\Workload` | `staff.view` — sortable, with utilisation bars and outliers flagged |
| Leave calendar | `People\Leave\Calendar` | `staff.leave.view` — who is away when, by department |
| Leave request | `People\Leave\Request` | own |
| Leave approvals | `People\Leave\Approvals` | `staff.leave.approve` |
| Leave balances | `People\Leave\Balances` | `staff.leave.view` |
| Duty rosters | `People\Duty\Rosters` | `staff.duty.manage` — generate, balance, publish |
| My duties | `People\Duty\Mine` | own |
| Appraisals | `People\Appraisal\Index` | `staff.appraisal.manage` |
| Disciplinary | `People\Staff\Disciplinary` | `staff.disciplinary.manage` ⚠ |
| Document expiry | `People\Staff\Compliance` | `staff.document.manage` — everything expiring, by urgency |
| Exit processing | `People\Staff\Exit` | `staff.exit.process` — clearance checklist |

### 6. API endpoints

```
GET   /api/v1/me/staff-profile
GET   /api/v1/me/allocations              ?term=   → my subjects and classes
GET   /api/v1/me/workload                 ?term=
GET   /api/v1/me/timetable                ?term=   → delegated to ACA-03
GET   /api/v1/me/duties                   ?from=&to=
POST  /api/v1/me/duties/{ulid}/swap-request
GET   /api/v1/me/leave/balances
GET   /api/v1/me/leave/requests
POST  /api/v1/me/leave/requests
DELETE /api/v1/me/leave/requests/{ulid}
GET   /api/v1/staff                       staff scope, permission-gated
GET   /api/v1/staff/{ulid}                compensation fields excluded by default
```

### 7. Permissions

```
staff.view                      staff.view_compensation ⚠
staff.create                    staff.update              staff.deactivate
staff.contract.view             staff.contract.manage ⚠
staff.establishment.view        staff.establishment.manage
staff.override_establishment ⚠
staff.allocate                  staff.enforce_workload_override
staff.leave.view                staff.leave.approve
staff.approve_leave_overdraft ⚠
staff.duty.view                 staff.duty.manage
staff.appraisal.view            staff.appraisal.manage
staff.disciplinary.view ⚠       staff.disciplinary.manage ⚠
staff.document.view             staff.document.manage
staff.exit.process ⚠            staff.export ⚠
```

### 8. Settings

| Key | Type | Default |
|---|---|---|
| `staff.staff_number_pattern` | string | `{SCHOOL}/STF/{SEQ:4}` |
| `staff.default_max_weekly_periods` | int | `30` |
| `staff.enforce_workload_ceiling` | bool | `false` |
| `staff.contract_expiry_warning_days` | int | `60` |
| `staff.document_expiry_warning_days` | array | `[90,30,7]` |
| `staff.default_notice_period_days` | int | `30` |
| `staff.probation_months` | int | `3` |
| `staff.sick_note_required_after_days` | int | `2` |
| `staff.sick_note_grace_days` | int | `3` |
| `staff.leave_year_starts` | enum | `academic_year` |
| `staff.duty_fairness_balancing` | bool | `true` |
| `staff.auto_deactivate_account_on_exit` | bool | `true` |

### 9. Events published

`StaffCreated` · `ContractStarted` · `ContractExpiring` · `ContractTerminated` · `TeacherAllocated` (→ `ACA-02`, `ACA-03`) · `TeacherAllocationEnded` · `WorkloadExceeded` · `LeaveRequested` · `LeaveApproved` (→ `ACA-03` cover, `PPL-05` payroll) · `LeaveCancelled` · `DutyAssigned` · `DutySwapped` · `StaffExited` (→ `CORE-05` token revocation) · `StaffDocumentExpiring`

### 10. Acceptance criteria

```gherkin
AC-PPL-04-001
  Given a teacher with a 30-period ceiling and 28 allocated periods
  When I allocate a further class of 4 periods
  Then a warning states the current load, the addition, and the resulting overload
  And the allocation proceeds unless the ceiling is enforced

AC-PPL-04-002
  Given a teacher's allocations change
  Then their workload cache recalculates immediately
  And the workload report reflects it without a manual refresh

AC-PPL-04-003
  Given a teacher has 8 days annual leave available
  When they request 10 days
  Then the request is refused
  Unless the approver holds staff.approve_leave_overdraft

AC-PPL-04-004
  Given a leave request for 5 days is submitted
  Then 5 days move from available to pending
  And on approval they move to taken
  And on rejection they return to available
  And the balance is never double-counted

AC-PPL-04-005
  Given an approved leave period for a teaching staff member
  Then their teaching periods appear on the daily cover report
  And ACA-03 is notified for substitution

AC-PPL-04-006
  Given a staff member's police clearance expires in 25 days
  Then a compliance alert is raised
  And they appear on the document expiry dashboard

AC-PPL-04-007
  Given a staff member's exit date is reached
  Then their user account is deactivated
  And all their API tokens are revoked
  And they are excluded from duty rostering

AC-PPL-04-008
  Given final pay is pending and the clearance checklist has open items
  Then release is blocked with the outstanding items named

AC-PPL-04-009
  Given a user without staff.view_compensation
  When they view a staff profile or export staff data
  Then salary, banking, and statutory identifiers are absent

AC-PPL-04-010
  Given a duty roster is generated for a term
  Then duties are distributed within one duty of the mean across eligible staff
  And staff on approved leave receive none in that period
```

---

## Part 3 — Domain B Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **B1** | `PPL-01` core record, identity, documents, status machine | Learner created; admission number gapless; state machine enforced |
| **B2** | `PPL-01` billing attributes, `student_attribute_changes`, events | **Direct writes to billing columns throw.** `LearnerEnrolled` triggers `FIN-02` billing. |
| **B3** | `PPL-01` enrolments, prior schooling, siblings, timeline, duplicates | Duplicate detection flags; merge restricted and audited |
| **B4** | `PPL-01` allocation (class, house), bulk operations, ID cards | Bulk operations preview before commit |
| **B5** | `PPL-03` guardians, relationships, rights matrix | Rights never inferred from relationship label |
| **B6** | `PPL-03` fee liabilities, resolution algorithm, households | **`Σ(splits) ≡ Σ(charges)` asserted per currency.** `FIN-03` issues split invoices correctly. |
| **B7** | `PPL-03` sponsorships, portal provisioning, verification | Sponsored fees invoice the sponsor, not written off |
| **B8** | `PPL-02` intakes, public form, enquiry pipeline | Public form cannot touch `students` |
| **B9** | `PPL-02` exams, interviews, priority scoring, offers, waitlist | Offer expiry promotes the waitlist automatically |
| **B10** | `PPL-02` conversion | **Zero re-keying. Deposit converts to fee credit. Atomic.** |
| **B11** | `PPL-04` staff, contracts, establishment, departments | Contract expiry alerting live |
| **B12** | `PPL-04` allocation matrix, workload, leave | Workload recalculates on change; leave balances never double-count |
| **B13** | `PPL-04` duties, appraisal, disciplinary, exit | Exit revokes access automatically |

`PPL-04` (B11–B13) has no dependency on B1–B10 and should run in parallel with a second developer.

---

## Part 4 — Domain B Acceptance Gate

### Contract compliance

- [ ] Every attribute the `FIN-02` rule engine matches on exists, is non-nullable, and emits an event on change
- [ ] `student_attribute_changes` records `effective_from` separately from `changed_at`, and `FIN-02` prorates on the former
- [ ] Direct writes to `enrolment_type`, `residency`, `grade_level_id`, `class_id`, `section_id`, `pathway` throw
- [ ] `Σ(liability shares) ≡ Σ(learner charges)` per currency, asserted across a 1,400-learner dataset with mixed family arrangements
- [ ] No cent is lost or duplicated in any percentage split, verified against 10,000 randomised splits

### Functional

- [ ] All `AC-PPL-01-*`, `AC-PPL-02-*`, `AC-PPL-03-*`, `AC-PPL-04-*` pass
- [ ] A full-time boarder and a three-subject part-time day scholar both bill correctly end to end
- [ ] Mid-term residency change produces a correct pro-rata credit with a readable calculation note
- [ ] An application converts to a learner with zero re-keying and the deposit becomes a fee credit
- [ ] A learner with employer-funded boarding, father-funded tuition, and a 60/40 levy split receives three correct invoices
- [ ] Withdrawal, transfer, readmission, and graduation all behave per the state machine

### Security & privacy

- [ ] National registration numbers, birth certificate numbers, salaries, and bank details are encrypted at rest
- [ ] Learner tokens cannot retrieve financial, welfare, safeguarding, or guardian-contact data
- [ ] A guardian sees only their own liability unless `may_view_full_balance` is set
- [ ] `has_court_restriction` overrides every other collection and authorisation flag
- [ ] Staff compensation fields are absent for users without `staff.view_compensation`, including in exports
- [ ] Every bulk learner read writes to `data_access_log`; above threshold it raises a security event
- [ ] Tenancy isolation suite passes for all Domain B models

### Performance

- [ ] Learner search returns within 300 ms on 5,000 learners
- [ ] The learner profile page loads within 800 ms with all tabs lazy-loaded
- [ ] Liability resolution for 1,400 learners completes within 30 seconds
- [ ] Workload recalculation for 180 staff completes within 10 seconds

### Quality

- [ ] Coverage ≥ 85% for Domain B (higher than the 80% floor because `PPL-03` feeds billing)
- [ ] PHPStan level 8 clean
- [ ] Every business rule has a named test referencing its rule ID

---

## Appendix A — The Billing Attribute Contract, Restated

The exact surface `FIN-02` depends on. Any change here is a breaking change to Book B and requires both documents to be amended together.

| Attribute | Source | Type | Nullable | Emits on change | Triggers rebilling |
|---|---|---|---|---|---|
| `enrolment_type` | `students` | `FULL_TIME` \| `PART_TIME` | ❌ | `LearnerEnrolmentTypeChanged` | ✅ |
| `residency` | `students` | `DAY` \| `BOARDER` \| `WEEKLY_BOARDER` | ❌ | `LearnerResidencyChanged` | ✅ |
| `section_id` | `students` | FK | ❌ | `LearnerSectionChanged` | ✅ |
| `grade_level_id` | `students` | FK | ❌ | `LearnerGradeLevelChanged` | ✅ |
| `class_id` | `students` | FK | ✅ | `LearnerClassChanged` | ⚪ (rarely) |
| `house_id` | `students` | FK | ✅ | `LearnerHouseChanged` | ⚪ |
| `pathway` | `students` | `ACADEMIC` \| `VOCATIONAL` | ✅ | `LearnerPathwayChanged` | ✅ |
| `nationality` | `students` | ISO-3166 alpha-2 | ❌ | `LearnerProfileUpdated` | ⚪ |
| `gender` | `students` | `male` \| `female` | ❌ | — | ❌ |
| `entry_cohort_year` | `students` | year | ❌ | — | ❌ |
| `status` | `students` | state machine | ❌ | `LearnerStatusChanged` | ✅ on withdraw |
| *subject count* | `ACA-02` | derived | — | `SubjectEnrolment*` | ✅ for `PART_TIME` |
| *custom fields* | `CORE-04` | typed | — | `CustomFieldValueChanged` | ⚪ if referenced by a rule |

⚪ = rebills only when a fee structure rule actually references that attribute; the engine determines this from the rule set rather than rebilling unconditionally.

---

## Appendix B — Next Book

**Book D — Domain C Academic Core (`ACA-01`, `ACA-02`, `ACA-04`, `ACA-05`)** covers Curriculum and Pathways under the Heritage-Based Curriculum, Class and Subject Enrolment — **which is the authoritative source of the per-subject count that drives part-time billing** — Attendance, and Assessment, Grading and Report Cards.

`ACA-02` closes the last open dependency in the billing chain. After Book D, the full fee cycle for both enrolment types is complete end to end.

---

*End of Volume 2, Book C.*

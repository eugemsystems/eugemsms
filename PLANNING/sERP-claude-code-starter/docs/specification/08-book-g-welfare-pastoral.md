# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book G — Domain E: Welfare & Pastoral (`BRD-06` → `BRD-08`)

| Field | Value |
|---|---|
| Document | Volume 2, Book G of 10 |
| Covers | Health, Clinic & Sanatorium · Discipline, Conduct & Behaviour · Counselling & Safeguarding |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A–D and F complete.** Closes the two roll-status stubs left open in Book F. |
| Next book | Book H — Operations, Payroll & Compliance |

---

## Part 0 — Read This First

### 0.1 Why this book is different

Every other book in this specification describes systems that hold data about children. This book describes systems that hold the data children would be most harmed by having disclosed: their medical history, their disciplinary record, and — in `BRD-08` — disclosures they may have made about being hurt.

Three consequences follow, and none of them is negotiable.

**Access is narrower than permission.** In the rest of the system, holding a permission means seeing the data. Here, three separate mechanisms narrow it further: tiered visibility, per-case access control lists, and mandatory read logging. A Head who can see every fee, every mark and every exeat cannot necessarily see a safeguarding case.

**Reads are logged, not just writes.** Elsewhere the audit trail answers "who changed this?". Here it must answer "who *looked* at this?", because in a safeguarding context inappropriate viewing is the harm.

**Super Admin is not exempt.** `BRD-08` is the one module in the platform where the vendor's own super-administrator has no implicit access. This is specified in Volume 1 and implemented here.

### 0.2 ⭐ The three-tier visibility model

The same underlying fact is visible at three different resolutions depending on who is asking. This model runs through all three modules in this book and is what makes them safe to deploy.

```
TIER 1 — EXISTENCE          "This learner has a medical alert."
   Who: class teacher, housemaster, duty staff, matron
   Purpose: know that care is needed and who to ask
   Stored: boolean flag on students (PPL-01)

TIER 2 — ACTIONABLE         "Severe nut allergy. EpiPen in the sanatorium.
                             Do not serve items containing nuts."
   Who: matron, catering staff, duty staff, first aider, sports staff
   Purpose: act correctly in the moment
   Stored: dietary_requirements, emergency_care_plans

TIER 3 — CLINICAL           Full history, diagnosis, medication, consultations,
                             practitioner correspondence
   Who: nurse, doctor, and nobody else without explicit grant
   Purpose: clinical care
   Stored: clinical tables, encrypted, read-logged
```

A teacher taking a class on a field trip needs Tier 2. They do not need Tier 3, and giving it to them is a privacy failure, not thoroughness.

### 0.3 What this book closes

| Stub | Opened in | Closed by |
|---|---|---|
| Roll status `sick_bay` | `BRD-02` §4 | `BRD-06` sick bay admissions |
| Roll status `hospital` | `BRD-02` §4 | `BRD-06` external referrals |
| Roll status `detention` | `BRD-02` §4 | `BRD-07` detention scheduling |
| Roll status `suspended` | `BRD-02` §4 | `BRD-07` sanction records |
| `medical_source_id` on dietary requirements | `BRD-04` | `BRD-06` clinical records |
| Medical proximity constraint | `BRD-01` §3 | `BRD-06` care plans |
| `conduct_grade` on term results | `ACA-05` | `BRD-07` conduct computation |
| `has_safeguarding_flag` on students | `PPL-01` | `BRD-08` |

### 0.4 Build order

```
BRD-06  Health, Clinic & Sanatorium   ← closes four Book F stubs
   ↓
BRD-07  Discipline, Conduct           ← closes two more; feeds ACA-05
   ↓
BRD-08  Counselling & Safeguarding    ← ⭐ inverted access model
```

`BRD-08` is built last deliberately. Its access model is unlike anything else in the platform, and building it after the team has internalised the normal model makes the difference obvious rather than accidental.

---

# BRD-06 · Health, Clinic & Sanatorium 🔒

### 1. Scope

**In scope.** Confidential medical records, allergy and chronic condition registers, immunisation tracking, sick bay admission and discharge, consultations, the medication administration record, clinic stock, injury and incident reporting, external referrals, consent, outbreak monitoring.

**Out of scope.** Dietary provision (`BRD-04`, which consumes the requirement). Counselling and mental health disclosures (`BRD-08`). Staff health (`PPL-04`).

### 2. Data model

```sql
medical_records                       -- one per learner, ENCRYPTED, read-logged
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK UNIQUE_PER_SCHOOL
blood_group             VARCHAR(5)   NULL
height_cm               SMALLINT     NULL
weight_kg               DECIMAL(5,2) NULL
last_measured_on        DATE         NULL
medical_aid_provider    VARCHAR(80)  NULL
medical_aid_number      VARCHAR(40)  NULL        -- ENCRYPTED
medical_aid_principal   VARCHAR(150) NULL
family_doctor_name      VARCHAR(150) NULL
family_doctor_phone     VARCHAR(30)  NULL
preferred_hospital      VARCHAR(150) NULL
notes                   TEXT         NULL        -- ENCRYPTED
last_reviewed_at        TIMESTAMP    NULL
last_reviewed_by        BIGINT       NULL FK
created_at, updated_at
  UNIQUE (school_id, student_id)

medical_conditions                    -- allergies, chronic conditions, disabilities
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
condition_type          VARCHAR(30)  NOT NULL    -- allergy|chronic|disability|
                                                 -- mental_health|temporary
category                VARCHAR(40)  NULL        -- asthma|epilepsy|diabetes|
                                                 -- sickle_cell|anaphylaxis|
                                                 -- visual|hearing|mobility
name                    VARCHAR(150) NOT NULL    -- ENCRYPTED
severity                VARCHAR(20)  NOT NULL    -- life_threatening|severe|
                                                 -- moderate|mild
-- ⭐ TIER 2: what non-clinical staff may see
public_summary          VARCHAR(255) NULL        -- 'Severe nut allergy — EpiPen'
requires_emergency_plan TINYINT(1)   NOT NULL DEFAULT 0
affects_dietary         TINYINT(1)   NOT NULL DEFAULT 0
affects_physical_activity TINYINT(1) NOT NULL DEFAULT 0
affects_accommodation   TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ BRD-01 proximity
accommodation_requirement VARCHAR(120) NULL      -- 'ground floor','near exit'
-- ⭐ TIER 3: clinical detail
diagnosis_notes         TEXT         NULL        -- ENCRYPTED
diagnosed_on            DATE         NULL
diagnosed_by            VARCHAR(150) NULL
supporting_document_id  BIGINT       NULL FK
verified_by_nurse       TINYINT(1)   NOT NULL DEFAULT 0
verified_at             TIMESTAMP    NULL
verified_by             BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL    -- active|resolved|monitoring
declared_by             VARCHAR(30)  NOT NULL    -- guardian|nurse|doctor|admission
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
created_by, created_at, updated_at
  INDEX (school_id, student_id, status)
  INDEX (school_id, severity, status)

emergency_care_plans                  -- ⭐ TIER 2, deliberately readable
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
condition_id            BIGINT       NULL FK
title                   VARCHAR(150) NOT NULL    -- 'Anaphylaxis response'
trigger_signs           TEXT         NOT NULL    -- what to look for
immediate_actions       TEXT         NOT NULL    -- what to do, in order
medication_location     VARCHAR(150) NULL        -- 'Sanatorium fridge, shelf 2'
medication_name         VARCHAR(120) NULL
do_not_do               TEXT         NULL
who_to_call             VARCHAR(255) NOT NULL
review_due_on           DATE         NULL
approved_by_nurse       BIGINT       NULL FK
approved_at             TIMESTAMP    NULL
guardian_acknowledged   TINYINT(1)   NOT NULL DEFAULT 0
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, student_id, is_active)

immunisations
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
vaccine                 VARCHAR(120) NOT NULL
dose_number             TINYINT      NULL
administered_on         DATE         NULL
administered_by         VARCHAR(150) NULL
batch_number            VARCHAR(60)  NULL
next_due_on             DATE         NULL
certificate_file_id     BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL    -- recorded|due|overdue|
                                                 -- declined|exempt
decline_reason          VARCHAR(255) NULL
  INDEX (school_id, student_id)
  INDEX (school_id, next_due_on, status)

sick_bay_admissions                   -- ⭐ closes BRD-02 'sick_bay' status
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
student_id              BIGINT       FK INDEX
admitted_at             TIMESTAMP    NOT NULL
admitted_by             BIGINT       FK → users.id
presenting_complaint    VARCHAR(255) NOT NULL
initial_observations    JSON         NULL        -- temp, pulse, resp, BP
bed_reference           VARCHAR(30)  NULL
is_isolation            TINYINT(1)   NOT NULL DEFAULT 0
isolation_reason        VARCHAR(120) NULL
severity                VARCHAR(20)  NOT NULL    -- minor|moderate|serious|emergency
guardian_notified_at    TIMESTAMP    NULL
guardian_notified_by    BIGINT       NULL FK
expected_discharge_at   TIMESTAMP    NULL
discharged_at           TIMESTAMP    NULL
discharged_by           BIGINT       NULL FK
discharge_destination   VARCHAR(30)  NULL        -- hostel|home|hospital|clinic
discharge_notes         TEXT         NULL
excused_from_lessons    TINYINT(1)   NOT NULL DEFAULT 1
excused_from_activity   TINYINT(1)   NOT NULL DEFAULT 1
status                  VARCHAR(20)  NOT NULL    -- admitted|observing|
                                                 -- referred|discharged
  INDEX (school_id, student_id, admitted_at)
  INDEX (school_id, status)          -- ⭐ BRD-02 queries this

clinic_observations                   -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
admission_id            BIGINT       FK INDEX
observed_at             TIMESTAMP    NOT NULL
temperature_c           DECIMAL(4,1) NULL
pulse_bpm               SMALLINT     NULL
respiration_rate        SMALLINT     NULL
blood_pressure          VARCHAR(20)  NULL
oxygen_saturation       SMALLINT     NULL
pain_score              TINYINT      NULL
notes                   TEXT         NULL
observed_by             BIGINT       FK → users.id
  INDEX (admission_id, observed_at)

consultations
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
admission_id            BIGINT       NULL FK
consulted_at            TIMESTAMP    NOT NULL
consultation_type       VARCHAR(30)  NOT NULL    -- walk_in|scheduled|
                                                 -- admission_review|follow_up
presenting_complaint    TEXT         NOT NULL    -- ENCRYPTED
assessment              TEXT         NULL        -- ENCRYPTED
plan                    TEXT         NULL        -- ENCRYPTED
practitioner_type       VARCHAR(30)  NOT NULL    -- nurse|visiting_doctor|
                                                 -- physiotherapist|dentist
practitioner_staff_id   BIGINT       NULL FK
external_practitioner   VARCHAR(150) NULL
follow_up_on            DATE         NULL
  INDEX (school_id, student_id, consulted_at)

medication_administrations            -- ⭐ APPEND-ONLY LEGAL RECORD
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
admission_id            BIGINT       NULL FK
prescription_id         BIGINT       NULL FK
medication_name         VARCHAR(150) NOT NULL
dose                    VARCHAR(60)  NOT NULL    -- '5ml','1 tablet'
route                   VARCHAR(30)  NOT NULL    -- oral|topical|inhaled|
                                                 -- injection|rectal
scheduled_at            TIMESTAMP    NULL
administered_at         TIMESTAMP    NOT NULL
administered_by         BIGINT       FK → users.id
witnessed_by            BIGINT       NULL FK     -- required for some categories
batch_number            VARCHAR(60)  NULL
expiry_date             DATE         NULL
consent_reference       VARCHAR(80)  NULL        -- ⭐ which consent authorised this
outcome                 VARCHAR(30)  NULL        -- given|refused_by_learner|
                                                 -- omitted|vomited
omission_reason         VARCHAR(255) NULL
adverse_reaction        TEXT         NULL
notes                   VARCHAR(255) NULL
  INDEX (school_id, student_id, administered_at)
  INDEX (school_id, admission_id)
  -- DB grants: INSERT, SELECT only. No UPDATE. No DELETE. Ever.

prescriptions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
medication_name         VARCHAR(150) NOT NULL
dose                    VARCHAR(60)  NOT NULL
frequency               VARCHAR(60)  NOT NULL    -- 'twice daily','as required'
route                   VARCHAR(30)  NOT NULL
prescribed_by           VARCHAR(150) NOT NULL    -- external practitioner
prescribed_on           DATE         NOT NULL
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL
is_prn                  TINYINT(1)   NOT NULL DEFAULT 0   -- as required
max_doses_per_day       TINYINT      NULL
prescription_file_id    BIGINT       NULL FK
guardian_consent_id     BIGINT       NULL FK     -- ⭐ mandatory
is_self_administered    TINYINT(1)   NOT NULL DEFAULT 0   -- inhaler, EpiPen
storage_location        VARCHAR(150) NULL
status                  VARCHAR(20)  NOT NULL    -- active|completed|
                                                 -- discontinued|expired
  INDEX (school_id, student_id, status)

medical_consents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
guardian_id             BIGINT       FK          -- must hold may_authorise_medical
consent_type            VARCHAR(40)  NOT NULL    -- routine_treatment|
                                                 -- otc_medication|prescribed_medication|
                                                 -- emergency_treatment|
                                                 -- hospital_transfer|dental|
                                                 -- immunisation|information_sharing
scope_detail            VARCHAR(255) NULL
granted                 TINYINT(1)   NOT NULL
granted_at              TIMESTAMP    NOT NULL
granted_via             VARCHAR(30)  NOT NULL    -- portal|form|verbal_recorded
witness_staff_id        BIGINT       NULL FK     -- verbal consent
document_file_id        BIGINT       NULL FK
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
withdrawn_at            TIMESTAMP    NULL
withdrawn_reason        VARCHAR(255) NULL
  INDEX (school_id, student_id, consent_type, granted)

health_incidents                      -- injuries and accidents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
student_id              BIGINT       FK INDEX
incident_type           VARCHAR(40)  NOT NULL    -- sports_injury|fall|burn|
                                                 -- cut|fracture|head_injury|
                                                 -- collapse|bite|poisoning
occurred_at             TIMESTAMP    NOT NULL
location                VARCHAR(150) NOT NULL
activity_at_time        VARCHAR(150) NULL
description             TEXT         NOT NULL
witnesses               JSON         NULL
first_aid_given         TEXT         NULL
first_aider_staff_id    BIGINT       NULL FK
admission_id            BIGINT       NULL FK
referral_id             BIGINT       NULL FK
guardian_notified_at    TIMESTAMP    NULL
severity                VARCHAR(20)  NOT NULL    -- minor|moderate|serious|critical
is_reportable           TINYINT(1)   NOT NULL DEFAULT 0   -- statutory reporting
reported_to             VARCHAR(150) NULL
reported_at             TIMESTAMP    NULL
follow_up_required      TINYINT(1)   NOT NULL DEFAULT 0
photo_file_ids          JSON         NULL
reported_by             BIGINT       FK → users.id
  INDEX (school_id, term_id, occurred_at)
  INDEX (school_id, severity, is_reportable)

external_referrals                    -- ⭐ closes BRD-02 'hospital' status
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
admission_id            BIGINT       NULL FK
incident_id             BIGINT       NULL FK
referral_type           VARCHAR(30)  NOT NULL    -- hospital|clinic|specialist|
                                                 -- dentist|optician|physio
facility_name           VARCHAR(200) NOT NULL
reason                  TEXT         NOT NULL
urgency                 VARCHAR(20)  NOT NULL    -- routine|urgent|emergency
referred_at             TIMESTAMP    NOT NULL
referred_by             BIGINT       FK → users.id
transport_method        VARCHAR(30)  NULL        -- school_vehicle|ambulance|
                                                 -- guardian|taxi
transport_request_id    BIGINT       NULL FK     -- OPS-01
escort_staff_id         BIGINT       NULL FK
guardian_notified_at    TIMESTAMP    NULL
guardian_present        TINYINT(1)   NULL
consent_reference       VARCHAR(80)  NULL
departed_at             TIMESTAMP    NULL
returned_at             TIMESTAMP    NULL
outcome                 TEXT         NULL        -- ENCRYPTED
cost_minor              BIGINT       NULL
cost_borne_by           VARCHAR(30)  NULL        -- school|guardian|medical_aid
ad_hoc_charge_id        BIGINT       NULL FK     -- FIN-02
status                  VARCHAR(20)  NOT NULL    -- referred|in_transit|
                                                 -- at_facility|returned|admitted
  INDEX (school_id, student_id, referred_at)
  INDEX (school_id, status)          -- ⭐ BRD-02 queries this

clinic_stock                          -- lightweight; full mechanics in FIN-09
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
inventory_item_id       BIGINT       NULL FK
name                    VARCHAR(150) NOT NULL
category                VARCHAR(30)  NOT NULL    -- medication|consumable|equipment
is_controlled           TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ stricter handling
quantity_on_hand        DECIMAL(10,2) NOT NULL DEFAULT 0
unit                    VARCHAR(20)  NOT NULL
reorder_level           DECIMAL(10,2) NULL
batch_number            VARCHAR(60)  NULL
expiry_date             DATE         NULL
storage_location        VARCHAR(150) NULL
  INDEX (school_id, expiry_date)
  INDEX (school_id, is_controlled)

controlled_stock_log                  -- APPEND-ONLY, two-person
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
clinic_stock_id         BIGINT       FK INDEX
action                  VARCHAR(20)  NOT NULL    -- received|administered|
                                                 -- disposed|counted|discrepancy
quantity                DECIMAL(10,2) NOT NULL
balance_after           DECIMAL(10,2) NOT NULL
administration_id       BIGINT       NULL FK
performed_by            BIGINT       FK → users.id
witnessed_by            BIGINT       FK → users.id   -- ⭐ mandatory, different person
occurred_at             TIMESTAMP    NOT NULL
notes                   VARCHAR(255) NULL

health_screenings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
student_id              BIGINT       FK INDEX
screening_type          VARCHAR(40)  NOT NULL    -- vision|hearing|dental|
                                                 -- growth|general
screened_on             DATE         NOT NULL
results                 JSON         NULL        -- ENCRYPTED
outcome                 VARCHAR(30)  NOT NULL    -- normal|monitor|refer
referral_id             BIGINT       NULL FK
guardian_informed_at    TIMESTAMP    NULL
screened_by             VARCHAR(150) NOT NULL
```

### 3. ⭐ Access enforcement

Tier is enforced in the policy layer, not in the view. An API response for a housemaster must not contain Tier 3 fields at all — hiding them client-side is not a control.

```php
final class MedicalConditionPolicy
{
    public function viewTier(User $user, Student $learner): MedicalTier
    {
        // Tier 3 — clinical
        if ($user->hasPermission('health.clinical.view')) {
            $this->accessLog->record($user, $learner, 'clinical_record', 'view');
            return MedicalTier::Clinical;
        }

        // Tier 2 — actionable, for those responsible for the learner right now
        if ($user->hasPermission('health.actionable.view')
            && $this->hasCareResponsibility($user, $learner)) {
            $this->accessLog->record($user, $learner, 'care_plan', 'view');
            return MedicalTier::Actionable;
        }

        // Tier 1 — existence only
        if ($user->canView($learner)) {
            return MedicalTier::Existence;
        }

        throw new InsufficientScopeException();
    }

    /** Guardians see their own child's full record. It is their child. */
    public function viewAsGuardian(Guardian $g, Student $learner): MedicalTier
    {
        return $g->relationshipTo($learner)?->is_active
            ? MedicalTier::Clinical
            : throw new InsufficientScopeException();
    }
}
```

**`hasCareResponsibility()`** resolves to: the learner's housemaster or matron, their class teacher, a staff member currently rostered on duty in their hostel, the staff member leading an activity the learner is on, or catering staff for dietary requirements only. It is a *current* relationship, not a permanent one — a teacher who taught the learner last year does not retain Tier 2 access.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-06-001` ⭐ | Medical data is tiered. An API response returns only the fields for the caller's resolved tier. Tier 3 fields are absent, not hidden. |
| `BR-BRD-06-002` ⭐ | Every read of a clinical record, care plan, consultation, or medication record writes to `data_access_log` (`CORE-08`) with the user, tier, learner, IP and time. |
| `BR-BRD-06-003` | Guardians with an active relationship see their own child's full record. Restricting a parent from their child's medical history requires a court restriction on the relationship (`PPL-03`). |
| `BR-BRD-06-004` | Clinical free-text fields are encrypted at rest with a key separate from general application encryption. |
| `BR-BRD-06-005` | A condition declared by a guardian is `verified_by_nurse = 0` until a nurse reviews it. Unverified conditions still produce Tier 1 and Tier 2 alerts — a school does not wait for verification before taking an allergy seriously. |
| `BR-BRD-06-006` | Conditions with `severity` of `life_threatening` or `severe` require an emergency care plan before the term begins, and their absence appears on the nurse's dashboard. |
| `BR-BRD-06-007` | Emergency care plans are deliberately Tier 2: written in plain language, readable by any staff member with care responsibility, containing no diagnosis beyond what is needed to act. |
| `BR-BRD-06-008` | Conditions with `affects_dietary = 1` create or update a `dietary_requirements` record in `BRD-04` with the `public_summary`, never the clinical detail. |
| `BR-BRD-06-009` | Conditions with `affects_accommodation = 1` supply a boolean constraint to `BRD-01` allocation. The allocation engine receives the requirement, never the reason. |
| `BR-BRD-06-010` ⭐ | `medication_administrations` is append-only, enforced at database grant level. An administration record is a legal document and is never edited. A correction is a new record annotating the original. |
| `BR-BRD-06-011` ⭐ | No medication is administered without a valid, unwithdrawn consent from a guardian holding `may_authorise_medical`. The consent reference is recorded on every administration. |
| `BR-BRD-06-012` | Over-the-counter medication requires a standing consent; prescribed medication requires a prescription-specific consent. |
| `BR-BRD-06-013` | Controlled medication requires a witness — a second, different staff member — on receipt, administration, and disposal, recorded in `controlled_stock_log`. |
| `BR-BRD-06-014` | A refused or omitted dose is recorded with a reason. Silence is not an acceptable record. |
| `BR-BRD-06-015` | A sick bay admission sets the learner's roll status to `sick_bay` for every roll while admitted, automatically (`BRD-02` §4). |
| `BR-BRD-06-016` | An admission of severity `serious` or `emergency` notifies the guardian immediately and unconditionally. Notification is not subject to quiet hours or cost caps. |
| `BR-BRD-06-017` | An external referral sets the roll status to `hospital` until return is recorded. |
| `BR-BRD-06-018` | An admission or referral automatically excuses the learner from lessons and activities for its duration, feeding `ACA-04` and `OPS-07`. |
| `BR-BRD-06-019` | Emergency treatment may proceed without consent where a life is at risk. The system records that it proceeded under emergency provision, who decided, and when, and notifies the guardian immediately. **This is recorded, not silent.** |
| `BR-BRD-06-020` | Health incidents of severity `serious` or `critical` notify the head and the guardian, and flag for statutory reporting review. |
| `BR-BRD-06-021` | A head injury is always at least `moderate` severity, always notifies the guardian, and always requires a follow-up observation record. |
| `BR-BRD-06-022` | Clinic stock expiry alerts at 90, 30 and 7 days. Expired medication cannot be selected for administration. |
| `BR-BRD-06-023` | Outbreak monitoring clusters admissions by presenting complaint and date; exceeding the configured threshold within a window alerts the nurse and the head. |
| `BR-BRD-06-024` | Referral costs borne by the school raise an ad hoc charge to the guardian only where `cost_borne_by = guardian`, after approval. |
| `BR-BRD-06-025` | Medical records are retained per `CMP-03` for a period longer than general learner data, and are excluded from routine archival purges. |

### 5. Screens

| Screen | Component | Permission | Tier |
|---|---|---|---|
| Clinical record | `Welfare\Health\Record` | `health.clinical.view` | 3 |
| Condition register | `Welfare\Health\Conditions` | `health.clinical.manage` | 3 |
| **Care plan** | `Welfare\Health\CarePlan` | `health.actionable.view` | 2 — plain language, printable, mobile |
| **Alert board** | `Welfare\Health\Alerts` | `health.actionable.view` | 2 — by hostel or class, photograph, summary, action |
| Sick bay | `Welfare\Health\SickBay` | `health.admission.manage` | 3 — admit, observe, discharge |
| Observations | `Welfare\Health\Observations` | `health.admission.manage` | 3 — timed vitals chart |
| **Medication round** | `Welfare\Health\MedicationRound` | `health.medication.administer` ⭐ | 3 — due now, consent check, witness prompt, one-tap record |
| Prescriptions | `Welfare\Health\Prescriptions` | `health.medication.manage` | 3 |
| Consents | `Welfare\Health\Consents` | `health.consent.manage` | 3 |
| Immunisations | `Welfare\Health\Immunisations` | `health.clinical.view` | 3 |
| Incidents | `Welfare\Health\Incidents` | `health.incident.report` | 2 to report, 3 to review |
| Referrals | `Welfare\Health\Referrals` | `health.referral.manage` | 3 |
| Clinic stock | `Welfare\Health\Stock` | `health.stock.manage` | — |
| Controlled register | `Welfare\Health\Controlled` | `health.stock.controlled` ⚠ | — two-person |
| Outbreak monitor | `Welfare\Health\Outbreak` | `health.clinical.view` | aggregate only |
| Screenings | `Welfare\Health\Screenings` | `health.clinical.manage` | 3 |

**The medication round screen is the one to get right.** It lists what is due in the next window, shows the learner's photograph, checks consent validity before allowing the record, prompts for a witness where the medication is controlled, and records in one tap. A matron doing a round of forty learners before breakfast will not use a screen that takes six taps per dose.

### 6. API endpoints

```
GET  /api/v1/health/alerts                     ?hostel=&class=   Tier 2 only
GET  /api/v1/health/care-plans/{student}       Tier 2
GET  /api/v1/health/medication-due             ?window=          nurse/matron
POST /api/v1/health/medication-administered    Idempotency-Key ⭐
POST /api/v1/health/incidents                  report from mobile with photos
GET  /api/v1/health/sick-bay/current           who is admitted now

GET  /api/v1/students/{ulid}/health            guardian: full record
POST /api/v1/students/{ulid}/health/consents   guardian: grant or withdraw
GET  /api/v1/me/health                         learner: own record, age-gated,
                                                 excludes mental health entries
```

**Note on the learner endpoint.** A learner sees their own allergies, immunisations and current medication — information they need. Mental-health condition entries are excluded from the learner view by default, because a learner discovering a diagnosis through an app is not how that should happen.

### 7. Permissions · Settings · Events

```
health.actionable.view          -- Tier 2: care plans, alerts, dietary
health.clinical.view ⚠          -- Tier 3
health.clinical.manage ⚠
health.admission.manage         health.medication.administer ⚠
health.medication.manage ⚠      health.consent.manage
health.incident.report          health.incident.review
health.referral.manage          health.stock.manage
health.stock.controlled ⚠⚠      health.report.view
```

| Setting | Type | Default |
|---|---|---|
| `health.notify_guardian_on_admission` | enum | `moderate_and_above` |
| `health.serious_admission_bypasses_quiet_hours` | bool | `true` (**locked**) |
| `health.require_care_plan_severity` | enum | `severe` |
| `health.controlled_requires_witness` | bool | `true` (**locked**) |
| `health.outbreak_threshold_cases` | int | `5` |
| `health.outbreak_window_days` | int | `3` |
| `health.stock_expiry_alert_days` | array | `[90,30,7]` |
| `health.learner_portal_shows_conditions` | bool | `true` |
| `health.learner_portal_excludes_mental_health` | bool | `true` |

Events: `MedicalConditionAdded` · `LifeThreateningConditionFlagged` ⚠ · `CarePlanApproved` · `SickBayAdmission` (→ `BRD-02`) · `SickBayDischarge` · `MedicationAdministered` · `MedicationOmitted` ⚠ · `HealthIncidentReported` · `SeriousIncidentReported` ⚠ · `ExternalReferralMade` (→ `BRD-02`, `OPS-01`) · `EmergencyTreatmentProceeded` ⚠ · `OutbreakThresholdReached` ⚠ · `ConsentWithdrawn`

### 8. Acceptance criteria

```gherkin
AC-BRD-06-001
  Given a housemaster with health.actionable.view
  When they request a learner's medical data via the API
  Then the response contains the public summary and care plan
  And contains no diagnosis, consultation notes, or clinical history fields

AC-BRD-06-002
  Given a nurse views a learner's clinical record
  Then a data_access_log entry records user, tier, learner, IP and time

AC-BRD-06-003
  Given no valid guardian consent exists for prescribed medication
  When administration is attempted
  Then it is refused
  And the reason names the missing consent

AC-BRD-06-004
  Given a medication administration has been recorded
  When any user attempts to edit or delete it
  Then the database refuses
  And a correction must be entered as a new annotating record

AC-BRD-06-005
  Given controlled medication is administered
  Then a second, different staff member must be recorded as witness
  And the controlled stock log balances

AC-BRD-06-006
  Given a learner is admitted to the sick bay
  When the next roll call is opened
  Then their status is pre-populated as 'sick_bay'
  And they are not counted as missing

AC-BRD-06-007
  Given a serious admission at 23:40 during configured quiet hours
  Then the guardian is notified immediately
  And quiet hours and cost caps do not suppress it

AC-BRD-06-008
  Given a learner has a severe nut allergy with an approved care plan
  When catering staff scan them at the serving terminal
  Then the public summary and action display
  And no diagnosis or clinical note is shown

AC-BRD-06-009
  Given a learner requires ground-floor accommodation for medical reasons
  Then BRD-01 receives the constraint
  And the housemaster's allocation view shows the constraint without the reason

AC-BRD-06-010
  Given emergency treatment proceeds without consent to save a life
  Then the record states it proceeded under emergency provision
  And names the deciding staff member and the time
  And the guardian is notified immediately

AC-BRD-06-011
  Given 6 admissions with the same presenting complaint within 3 days
  And the outbreak threshold is 5
  Then the nurse and head are alerted

AC-BRD-06-012
  Given medication in clinic stock has expired
  Then it cannot be selected on the medication round screen

AC-BRD-06-013
  Given a learner opens their own health record in the portal
  Then allergies, immunisations and current medication are visible
  And mental-health condition entries are not
```

---

# BRD-07 · Discipline, Conduct & Behaviour

> A disciplinary record is protective for both the learner and the school — protective for the learner because it forces consistency and evidence, protective for the school because it demonstrates that a sanction followed a process.

### 1. Scope

**In scope.** Merit and demerit points, incident logging, sanction catalogue and automatic triggers, detention scheduling, suspension and exclusion workflow with appeal, positive behaviour recording, conduct grades, prefect and leadership register, behaviour analytics.

**Out of scope.** Safeguarding concerns (`BRD-08` — see §3 for the routing rule). Staff discipline (`PPL-04`).

### 2. Data model

```sql
behaviour_categories
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL
name                    VARCHAR(120) NOT NULL
polarity                VARCHAR(10)  NOT NULL    -- positive | negative
default_points          SMALLINT     NOT NULL    -- +5 merit, -3 demerit
severity_level          TINYINT      NULL        -- 1..5 for negative
requires_evidence       TINYINT(1)   NOT NULL DEFAULT 0
requires_head_review    TINYINT(1)   NOT NULL DEFAULT 0
auto_notify_guardian    TINYINT(1)   NOT NULL DEFAULT 0
suggests_sanction_id    BIGINT       NULL FK
is_safeguarding_trigger TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ routes to BRD-08
sort_order              SMALLINT
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

behaviour_records                     -- merits and demerits, and incidents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
category_id             BIGINT       FK
polarity                VARCHAR(10)  NOT NULL
points                  SMALLINT     NOT NULL
occurred_at             TIMESTAMP    NOT NULL
location                VARCHAR(120) NULL
context                 VARCHAR(40)  NULL        -- lesson|prep|dining|hostel|
                                                 -- sport|transport|off_campus
subject_id              BIGINT       NULL FK
class_id                BIGINT       NULL FK
hostel_id               BIGINT       NULL FK
description             TEXT         NOT NULL
witnesses               JSON         NULL
other_learners_involved JSON         NULL        -- linked records
evidence_file_ids       JSON         NULL
reported_by             BIGINT       FK → users.id
status                  VARCHAR(20)  NOT NULL    -- recorded|under_review|
                                                 -- sanctioned|dismissed|appealed
guardian_notified_at    TIMESTAMP    NULL
is_confidential         TINYINT(1)   NOT NULL DEFAULT 0
safeguarding_case_id    BIGINT       NULL FK     -- ⭐ where routed to BRD-08
created_at, updated_at
  INDEX (school_id, student_id, occurred_at)
  INDEX (school_id, term_id, polarity)
  INDEX (school_id, status)

behaviour_point_balances              -- CACHE, rebuilt from records
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
term_id                 BIGINT       FK
merit_points            SMALLINT     NOT NULL DEFAULT 0
demerit_points          SMALLINT     NOT NULL DEFAULT 0
net_points              SMALLINT     NOT NULL DEFAULT 0
record_count            SMALLINT     NOT NULL DEFAULT 0
conduct_grade           VARCHAR(20)  NULL        -- ⭐ fed to ACA-05
rebuilt_at              TIMESTAMP
  UNIQUE (school_id, student_id, term_id)

sanction_types
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL    -- 'VERBAL','WRITTEN','DETENTION',
                                                 -- 'COMMUNITY','SUSPENSION','EXCLUSION'
name                    VARCHAR(120) NOT NULL
severity_level          TINYINT      NOT NULL    -- 1..6
approval_chain_id       BIGINT       NULL FK     -- CORE-07
requires_guardian_meeting TINYINT(1) NOT NULL DEFAULT 0
requires_committee      TINYINT(1)   NOT NULL DEFAULT 0
removes_from_lessons    TINYINT(1)   NOT NULL DEFAULT 0
removes_from_campus     TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ sets roll status
max_duration_days       SMALLINT     NULL
appealable              TINYINT(1)   NOT NULL DEFAULT 1
appeal_window_days      SMALLINT     NOT NULL DEFAULT 5
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

behaviour_trigger_rules               -- points thresholds → suggested sanction
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
name                    VARCHAR(120) NOT NULL
trigger_type            VARCHAR(30)  NOT NULL    -- points_threshold|
                                                 -- repeat_category|severity_single
demerit_threshold       SMALLINT     NULL
window_days             SMALLINT     NULL
category_id             BIGINT       NULL FK
repeat_count            SMALLINT     NULL
suggested_sanction_id   BIGINT       FK
notify_role_id          BIGINT       NULL FK
is_automatic            TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ default: suggest only
is_active               TINYINT(1)   NOT NULL DEFAULT 1

sanctions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
sanction_type_id        BIGINT       FK
behaviour_record_ids    JSON         NOT NULL    -- what it responds to
reason                  TEXT         NOT NULL
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL
duration_days           SMALLINT     NULL
status                  VARCHAR(20)  NOT NULL    -- proposed|pending_approval|
                                                 -- approved|active|completed|
                                                 -- appealed|overturned|cancelled
approval_request_id     BIGINT       NULL FK
issued_by               BIGINT       FK → users.id
approved_by             BIGINT       NULL FK
guardian_notified_at    TIMESTAMP    NULL
guardian_meeting_at     TIMESTAMP    NULL
guardian_meeting_notes  TEXT         NULL
committee_record_id     BIGINT       NULL FK
conditions              TEXT         NULL        -- re-entry conditions
completed_at            TIMESTAMP    NULL
completion_notes        TEXT         NULL
document_id             BIGINT       NULL FK     -- formal letter
  INDEX (school_id, student_id, status)
  INDEX (school_id, status, starts_on, ends_on)   -- ⭐ BRD-02 queries this

disciplinary_committees
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
convened_on             DATE         NOT NULL
panel_staff_ids         JSON         NOT NULL
guardian_present        TINYINT(1)   NULL
learner_present         TINYINT(1)   NULL
learner_statement       TEXT         NULL        -- ⭐ the learner's own account
guardian_statement      TEXT         NULL
evidence_reviewed       JSON         NULL
findings                TEXT         NOT NULL
decision                VARCHAR(40)  NOT NULL    -- no_case|warning|sanction|
                                                 -- referred_to_board
recommended_sanction_id BIGINT       NULL FK
minutes_document_id     BIGINT       NULL FK
chaired_by              BIGINT       FK → users.id
  INDEX (school_id, student_id, convened_on)

appeals
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
sanction_id             BIGINT       FK INDEX
lodged_by_guardian_id   BIGINT       NULL FK
lodged_by_student       TINYINT(1)   NOT NULL DEFAULT 0
grounds                 TEXT         NOT NULL
supporting_document_id  BIGINT       NULL FK
lodged_at               TIMESTAMP    NOT NULL
heard_at                TIMESTAMP    NULL
heard_by                JSON         NULL
outcome                 VARCHAR(30)  NULL        -- upheld|reduced|overturned|
                                                 -- dismissed
outcome_reason          TEXT         NULL
new_sanction_id         BIGINT       NULL FK
decided_at              TIMESTAMP    NULL
decided_by              BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL    -- lodged|under_review|decided

detentions                            -- ⭐ closes BRD-02 'detention' status
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
sanction_id             BIGINT       NULL FK
student_id              BIGINT       FK INDEX
scheduled_date          DATE         NOT NULL
starts_at               TIME         NOT NULL
ends_at                 TIME         NOT NULL
venue                   VARCHAR(120) NULL
supervisor_staff_id     BIGINT       NULL FK
task_set                TEXT         NULL
attended                TINYINT(1)   NULL
attendance_note         VARCHAR(255) NULL
status                  VARCHAR(20)  NOT NULL    -- scheduled|attended|
                                                 -- missed|rescheduled|waived
  INDEX (school_id, scheduled_date, status)
  INDEX (school_id, student_id, scheduled_date)

student_leadership
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
student_id              BIGINT       FK INDEX
role_title              VARCHAR(80)  NOT NULL    -- 'Head Boy','House Prefect'
scope_type              VARCHAR(20)  NULL        -- school|house|hostel|class
scope_id                BIGINT       NULL
granted_permissions     JSON         NULL        -- limited system rights
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL
appointed_by            BIGINT       FK → users.id
status                  VARCHAR(20)  NOT NULL    -- active|ended|revoked
revocation_reason       VARCHAR(255) NULL
```

### 3. ⭐ The safeguarding routing rule

The most important rule in this module, and the one most likely to be got wrong.

**Some behaviour recorded as a disciplinary matter is actually a safeguarding matter.** A learner who is repeatedly absconding, self-harming, showing sudden aggression, or making a disclosure during a disciplinary interview is not primarily a discipline case. Treating them as one causes real harm.

```
Behaviour record created
  │
  ├─ category.is_safeguarding_trigger = 1  ────────────────┐
  │     (absconding, self-harm indicators, disclosure,     │
  │      peer-on-peer harm, sexualised behaviour,          │
  │      signs of neglect)                                 │
  │                                                        ▼
  │                                          Opens a BRD-08 concern
  │                                          Notifies the safeguarding lead
  │                                          Sets is_confidential = 1
  │                                          Links safeguarding_case_id
  │                                          ⭐ Disciplinary process PAUSES
  │                                             pending safeguarding assessment
  │
  └─ otherwise → normal disciplinary process
```

| # | Rule |
|---|---|
| A record routed to safeguarding becomes confidential immediately. General staff lose visibility of its detail; they see that a matter is under review. |
| Sanctions cannot be issued on a paused record until the safeguarding lead releases it back. |
| The safeguarding lead can release it back to discipline, keep it in safeguarding, or run both. That decision is recorded. |
| The trigger categories are configurable per school but the seeded set cannot be emptied — at least one trigger category must remain active. |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-07-001` | Behaviour records carry both polarities. **The system records commendation as readily as it records misconduct**, and the interface makes positive recording at least as easy. |
| `BR-BRD-07-002` | Point balances are derived from records and cached, rebuilt nightly and recomputed at term close. |
| `BR-BRD-07-003` | Trigger rules are `is_automatic = 0` by default: they **suggest** a sanction to a human, who decides. Automatic sanctioning is available but off, and enabling it is a logged setting change. |
| `BR-BRD-07-004` ⭐ | No sanction is applied to a learner by the system alone. A named person issues every sanction. |
| `BR-BRD-07-005` | Sanctions above the configured severity require approval through `CORE-07`. Suspension and exclusion always require it. |
| `BR-BRD-07-006` | Suspension and exclusion require a disciplinary committee record containing the learner's own statement, or an explicit record that the learner declined to give one. |
| `BR-BRD-07-007` | Guardians are notified of every sanction before it takes effect, except where immediate removal is necessary for safety, in which case notification is immediate and the reason recorded. |
| `BR-BRD-07-008` | Appealable sanctions carry an appeal window. An appeal lodged within it suspends the sanction unless the sanction type is marked as continuing during appeal. |
| `BR-BRD-07-009` | An overturned sanction is marked overturned. **It is not deleted.** The record shows that it was issued and overturned, which protects the learner. |
| `BR-BRD-07-010` | Sanctions with `removes_from_campus = 1` set the learner's roll status to `suspended` for their duration (`BRD-02` §4). |
| `BR-BRD-07-011` | Detention scheduling sets roll status to `detention` for that period and excuses the learner from any clashing activity. |
| `BR-BRD-07-012` | A missed detention creates a follow-up record and may trigger escalation per the rule set. |
| `BR-BRD-07-013` | A suspended learner continues to be billed unless a waiver is separately approved (`BR-PPL-01-012`). |
| `BR-BRD-07-014` | Suspension does not remove the school's duty of care. Where the learner is a boarder, arrangements for their supervision or transport home are recorded as part of the sanction. |
| `BR-BRD-07-015` | Conduct grade per term is computed from net points against configurable bands and supplied to `ACA-05` for the report card. |
| `BR-BRD-07-016` | Guardians see their own child's behaviour record including positive records. They never see other learners named in an incident. |
| `BR-BRD-07-017` | Other learners involved in an incident are linked by reference; each learner's guardian sees only their own child's record. |
| `BR-BRD-07-018` ⭐ | Categories flagged `is_safeguarding_trigger` route to `BRD-08` per §3 and pause the disciplinary process. |
| `BR-BRD-07-019` | Behaviour analytics are available by learner, class, house, hostel, category and time. Analytics identify patterns for pastoral intervention, never for public ranking of learners. |
| `BR-BRD-07-020` | Student leadership roles may carry limited system permissions (a prefect marking hostel roll call), granted explicitly and revocable. |
| `BR-BRD-07-021` | Disciplinary records are retained per `CMP-03` and are excluded from routine references and transcripts unless the school explicitly configures otherwise. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| **Record behaviour** | `Welfare\Behaviour\Record` | `behaviour.record` — mobile-first, category picker with positive first, photo evidence, one-tap common categories |
| Learner behaviour | `Welfare\Behaviour\Learner` | `behaviour.view` — timeline, points, patterns, both polarities |
| Behaviour board | `Welfare\Behaviour\Board` | `behaviour.view` — by class, house, hostel; merit leaders and concerns |
| Review queue | `Welfare\Behaviour\Review` | `behaviour.review` — records requiring head or HOD review |
| Sanctions | `Welfare\Sanctions\Index` | `behaviour.sanction.view` |
| Issue sanction | `Welfare\Sanctions\Issue` | `behaviour.sanction.issue` ⚠ — linked records, reason, duration, guardian notification preview |
| Detention register | `Welfare\Detentions\Register` | `behaviour.detention.manage` — schedule, supervise, mark attendance |
| Committee | `Welfare\Committee\Hearing` | `behaviour.committee.convene` ⚠ — panel, statements, findings, minutes |
| Appeals | `Welfare\Appeals\Index` | `behaviour.appeal.manage` |
| Trigger rules | `Welfare\Behaviour\Rules` | `behaviour.manage` ⚠ |
| Conduct grades | `Welfare\Behaviour\Conduct` | `behaviour.view` — computed per term, feeds report card |
| Leadership | `Welfare\Leadership\Index` | `behaviour.leadership.manage` |
| Analytics | `Welfare\Behaviour\Analytics` | `behaviour.report.view` |

### 6. API endpoints

```
POST /api/v1/behaviour/records                staff, mobile
GET  /api/v1/behaviour/categories             quick-pick list
GET  /api/v1/behaviour/my-records             learner: own record
GET  /api/v1/students/{ulid}/behaviour        guardian: own child
GET  /api/v1/behaviour/detentions/today       supervisor
POST /api/v1/behaviour/detentions/{ulid}/attendance
GET  /api/v1/sanctions/pending-approval       approver queue
POST /api/v1/appeals                          guardian: lodge an appeal
```

### 7. Permissions · Settings · Events

```
behaviour.view                  behaviour.record
behaviour.review                behaviour.manage ⚠
behaviour.sanction.view         behaviour.sanction.issue ⚠
behaviour.sanction.approve ⚠    behaviour.detention.manage
behaviour.committee.convene ⚠   behaviour.appeal.manage
behaviour.leadership.manage     behaviour.report.view
```

| Setting | Type | Default |
|---|---|---|
| `behaviour.automatic_sanctioning_enabled` | bool | `false` (**enabling is logged**) |
| `behaviour.sanction_approval_from_severity` | int | `3` |
| `behaviour.appeal_window_days` | int | `5` |
| `behaviour.appeal_suspends_sanction` | bool | `true` |
| `behaviour.conduct_grade_bands` | json | seeded |
| `behaviour.notify_guardian_from_severity` | int | `2` |
| `behaviour.show_conduct_on_report_card` | bool | `true` |
| `behaviour.merit_visible_to_learner` | bool | `true` |

Events: `BehaviourRecorded` · `PositiveBehaviourRecorded` · `TriggerThresholdReached` · `SanctionProposed` · `SanctionApproved` · `SanctionActive` (→ `BRD-02`) · `DetentionScheduled` (→ `BRD-02`) · `AppealLodged` · `SanctionOverturned` · `SafeguardingTriggerDetected` ⚠⚠ (→ `BRD-08`) · `ConductGradeComputed` (→ `ACA-05`)

### 8. Acceptance criteria

```gherkin
AC-BRD-07-001
  Given a learner accumulates demerits past a trigger threshold
  Then a sanction is SUGGESTED to a named staff member
  And no sanction is applied automatically
  Unless automatic sanctioning has been explicitly enabled

AC-BRD-07-002
  Given a suspension is issued
  Then it requires approval before taking effect
  And a committee record exists containing the learner's statement
      or an explicit note that they declined to give one

AC-BRD-07-003
  Given a suspension is active
  When a roll call is opened
  Then the learner's status is pre-populated as 'suspended'

AC-BRD-07-004
  Given a sanction is overturned on appeal
  Then it is marked overturned
  And it is not deleted
  And the record shows both the issue and the overturn

AC-BRD-07-005
  Given a behaviour category flagged is_safeguarding_trigger is recorded
  Then a BRD-08 concern opens
  And the safeguarding lead is notified
  And the disciplinary process pauses
  And no sanction can be issued until safeguarding releases it

AC-BRD-07-006
  Given an incident involves three learners
  When each guardian views their child's record
  Then they see their own child's involvement
  And no other learner is named

AC-BRD-07-007
  Given a boarder is suspended
  Then arrangements for supervision or transport home are recorded
      as part of the sanction before it takes effect

AC-BRD-07-008
  Given a learner earns merit points
  When their guardian views the record
  Then positive records appear alongside negative ones

AC-BRD-07-009
  Given a detention is scheduled during a sports fixture
  Then the clash is flagged
  And one is rescheduled before both take effect
```

---

# BRD-08 · Counselling & Safeguarding 🔒🔒

> **The one module in this platform where Super Admin has no implicit access.**
>
> Everything here exists to protect children who may have been harmed. The access model is inverted relative to the rest of the system: nobody sees anything unless they have been explicitly, individually granted access to that specific case, and every single read is recorded.

### 1. Scope

**In scope.** Counselling session records, safeguarding concern reporting and case management, per-case access control, the hardened audit stream, vulnerable-learner welfare monitoring, external agency referrals, risk assessment, anonymous learner reporting, case review.

**Out of scope.** Clinical medical care (`BRD-06`). Disciplinary process (`BRD-07`, which routes here). Counsellor employment records (`PPL-04`).

### 2. ⭐ The inverted access model

Everywhere else in the platform: **role grants permission, permission grants access.**

Here: **role grants candidacy. Access is granted per case, to a named individual, by the safeguarding lead, and every read is logged.**

```
                    NORMAL MODULE                    BRD-08
                    ─────────────                    ──────
Super Admin         implicit full access             NO ACCESS ⭐
Head                broad access by role             candidate only; must be granted
Deputy Head         broad access by role             candidate only; must be granted
Safeguarding Lead   n/a                              full access + grants access
Counsellor          n/a                              own cases + granted cases
Class teacher       scoped access                    NO ACCESS unless granted
Housemaster         scoped access                    NO ACCESS unless granted
Support engineer    audited impersonation            NO ACCESS, no impersonation ⭐
```

**Why Super Admin is excluded.** The vendor's super-administrator exists to operate the platform, not to read children's disclosures. Excluding them is not a lack of trust in any individual; it is the removal of a capability that should not exist. A vendor employee who *could* read a safeguarding case is a risk to the school's families regardless of whether they ever would.

**How the exclusion is enforced.** At the policy layer, not by omitting a menu item.

```php
final class SafeguardingCasePolicy
{
    public function view(User $user, SafeguardingCase $case): bool
    {
        // ⭐ HARD EXCLUSION — first check, no exceptions
        if ($user->isVendorSuperAdmin() || $user->isImpersonating()) {
            $this->hardenedAudit->recordAttempt($user, $case, 'blocked_vendor_access');
            $this->alert->safeguardingLead($case->school, 'vendor_access_attempt');
            return false;
        }

        // Safeguarding lead for this school
        if ($user->isSafeguardingLead($case->school_id)) {
            $this->hardenedAudit->recordRead($user, $case, 'lead');
            return true;
        }

        // Explicitly granted on THIS case, unexpired
        $grant = $case->activeGrantFor($user);
        if ($grant !== null) {
            $this->hardenedAudit->recordRead($user, $case, "grant:{$grant->ulid}");
            return true;
        }

        // Break-glass: allowed, but loud
        if ($user->hasPermission('safeguarding.emergency_access')) {
            $this->hardenedAudit->recordBreakGlass($user, $case);
            $this->alert->safeguardingLead($case->school, 'break_glass_used');
            $this->alert->head($case->school, 'break_glass_used');
            return true;    // access granted, everyone told
        }

        $this->hardenedAudit->recordAttempt($user, $case, 'denied');
        return false;
    }
}
```

**Break-glass is permitted and noisy.** A safeguarding lead unreachable at 2am while a child is at risk cannot be an obstacle. So emergency access exists — and using it immediately alerts the safeguarding lead, the head, and writes a permanent, prominent record. The control is not prevention; it is unavoidable visibility.

### 3. Data model

```sql
safeguarding_concerns                 -- initial report; low friction by design
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       NULL FK     -- null for anonymous reports
reported_at             TIMESTAMP    NOT NULL
report_source           VARCHAR(30)  NOT NULL    -- staff|learner_self|learner_peer|
                                                 -- guardian|external|anonymous|
                                                 -- system_triggered
reporter_user_id        BIGINT       NULL FK     -- null when anonymous
anonymous_token         CHAR(26)     NULL        -- reporter can follow up, unnamed
concern_category        VARCHAR(40)  NOT NULL    -- neglect|physical|emotional|
                                                 -- sexual|peer_on_peer|self_harm|
                                                 -- bullying|online|exploitation|
                                                 -- home_circumstances|other
description             TEXT         NOT NULL    -- ENCRYPTED
immediate_risk          TINYINT(1)   NOT NULL DEFAULT 0
initial_action_taken    TEXT         NULL
case_id                 BIGINT       NULL FK
triage_status           VARCHAR(20)  NOT NULL    -- awaiting_triage|escalated|
                                                 -- monitoring|no_further_action
triaged_by              BIGINT       NULL FK
triaged_at              TIMESTAMP    NULL
triage_rationale        TEXT         NULL
  INDEX (school_id, triage_status, reported_at)
  INDEX (school_id, immediate_risk, triage_status)
  -- No DELETE. Ever. At any permission level.

safeguarding_cases
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
case_reference          VARCHAR(40)  NOT NULL    -- gapless, CORE-06
student_id              BIGINT       FK INDEX
opened_at               TIMESTAMP    NOT NULL
opened_by               BIGINT       FK → users.id
lead_staff_id           BIGINT       FK          -- case owner
category                VARCHAR(40)  NOT NULL
risk_level              VARCHAR(20)  NOT NULL    -- low|medium|high|critical
summary                 TEXT         NOT NULL    -- ENCRYPTED
status                  VARCHAR(20)  NOT NULL    -- open|monitoring|referred|
                                                 -- closed|transferred
external_agency_involved TINYINT(1)  NOT NULL DEFAULT 0
guardians_informed      TINYINT(1)   NULL        -- ⭐ may be NO, deliberately
guardians_not_informed_reason TEXT   NULL        -- ENCRYPTED
next_review_on          DATE         NULL
closed_at               TIMESTAMP    NULL
closed_by               BIGINT       NULL FK
closure_summary         TEXT         NULL        -- ENCRYPTED
retention_until         DATE         NULL        -- ⭐ far longer than normal
  UNIQUE (school_id, case_reference)
  INDEX (school_id, status, risk_level)
  INDEX (school_id, next_review_on)
  -- No DELETE. Ever.

case_access_grants                    -- ⭐ per-case, per-person
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK
case_id                 BIGINT       FK INDEX
user_id                 BIGINT       FK INDEX
access_level            VARCHAR(20)  NOT NULL    -- read|contribute|full
granted_by              BIGINT       FK → users.id
granted_at              TIMESTAMP    NOT NULL
reason                  VARCHAR(255) NOT NULL    -- ⭐ why this person needs it
expires_at              TIMESTAMP    NULL        -- time-boxed by default
revoked_at              TIMESTAMP    NULL
revoked_by              BIGINT       NULL FK
revocation_reason       VARCHAR(255) NULL
  INDEX (case_id, user_id, revoked_at)

case_entries                          -- APPEND-ONLY chronology
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK
case_id                 BIGINT       FK INDEX
entry_type              VARCHAR(30)  NOT NULL    -- observation|conversation|
                                                 -- action_taken|referral|
                                                 -- agency_contact|review|
                                                 -- guardian_contact|decision
entry_at                TIMESTAMP    NOT NULL    -- when it happened
recorded_at             TIMESTAMP    NOT NULL    -- when it was written
content                 TEXT         NOT NULL    -- ENCRYPTED
is_learner_account      TINYINT(1)   NOT NULL DEFAULT 0  -- the child's own words
present_persons         JSON         NULL
recorded_by             BIGINT       FK → users.id
attachment_file_ids     JSON         NULL
  INDEX (case_id, entry_at)
  -- No UPDATE. No DELETE. Corrections are new entries.

counselling_sessions                  -- separate from casework
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
counsellor_staff_id     BIGINT       FK
session_at              TIMESTAMP    NOT NULL
duration_minutes        SMALLINT     NULL
session_type            VARCHAR(30)  NOT NULL    -- individual|group|family|
                                                 -- crisis|follow_up
referral_source         VARCHAR(30)  NULL        -- self|teacher|guardian|
                                                 -- safeguarding|medical
presenting_theme        VARCHAR(120) NULL        -- ⭐ theme only at this level
session_notes           TEXT         NULL        -- ENCRYPTED, counsellor only
risk_indicators_present TINYINT(1)   NOT NULL DEFAULT 0
escalated_to_case_id    BIGINT       NULL FK
next_session_on         DATE         NULL
attended                TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, student_id, session_at)
  INDEX (school_id, counsellor_staff_id, session_at)

risk_assessments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK
case_id                 BIGINT       FK INDEX
assessed_at             TIMESTAMP    NOT NULL
assessed_by             BIGINT       FK → users.id
risk_factors            JSON         NOT NULL    -- ENCRYPTED
protective_factors      JSON         NULL        -- ENCRYPTED
risk_level              VARCHAR(20)  NOT NULL
rationale               TEXT         NOT NULL    -- ENCRYPTED
mitigation_plan         TEXT         NOT NULL    -- ENCRYPTED
review_due_on           DATE         NOT NULL
  INDEX (case_id, assessed_at)

agency_referrals
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK
case_id                 BIGINT       FK INDEX
agency_type             VARCHAR(40)  NOT NULL    -- social_services|police|
                                                 -- child_protection|health|
                                                 -- ngo|legal|court
agency_name             VARCHAR(200) NOT NULL
contact_person          VARCHAR(150) NULL
referred_at             TIMESTAMP    NOT NULL
referred_by             BIGINT       FK → users.id
reason                  TEXT         NOT NULL    -- ENCRYPTED
information_shared      TEXT         NULL        -- ENCRYPTED; what was disclosed
consent_basis           VARCHAR(40)  NOT NULL    -- guardian_consent|
                                                 -- vital_interest|legal_obligation
acknowledgement_at      TIMESTAMP    NULL
agency_reference        VARCHAR(80)  NULL
outcome                 TEXT         NULL        -- ENCRYPTED
status                  VARCHAR(20)  NOT NULL    -- made|acknowledged|
                                                 -- in_progress|closed

vulnerable_learner_register
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
vulnerability_type      VARCHAR(40)  NOT NULL    -- orphan|child_headed_household|
                                                 -- bereavement|chronic_illness|
                                                 -- financial_hardship|
                                                 -- family_disruption|refugee
identified_at           TIMESTAMP    NOT NULL
identified_by           BIGINT       FK → users.id
support_plan            TEXT         NULL        -- ENCRYPTED
assigned_mentor_id      BIGINT       NULL FK
review_frequency_days   SMALLINT     NOT NULL DEFAULT 30
next_review_on          DATE         NULL
status                  VARCHAR(20)  NOT NULL    -- active|monitoring|resolved
  INDEX (school_id, status, next_review_on)

safeguarding_audit                    -- ⭐ SEPARATE HARDENED STREAM
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
sequence                BIGINT       NOT NULL    -- monotonic per school
event_type              VARCHAR(40)  NOT NULL    -- case_read|entry_read|
                                                 -- entry_written|grant_issued|
                                                 -- grant_revoked|access_denied|
                                                 -- break_glass|vendor_blocked|
                                                 -- export|case_closed
case_id                 BIGINT       NULL
concern_id              BIGINT       NULL
user_id                 BIGINT       NOT NULL FK
user_role_at_time       VARCHAR(80)  NOT NULL
access_basis            VARCHAR(80)  NULL        -- lead|grant:ulid|break_glass
ip_address              VARCHAR(45)
user_agent              VARCHAR(500)
payload_hash            CHAR(64)     NOT NULL
previous_hash           CHAR(64)     NULL        -- chained, tamper-evident
occurred_at             TIMESTAMP(6) NOT NULL
  UNIQUE (school_id, sequence)
  INDEX (school_id, case_id, occurred_at)
  INDEX (school_id, event_type, occurred_at)
  -- DB grants: INSERT, SELECT only. Separate from the general audit stream.
```

### 4. ⭐ Anonymous learner reporting

A learner must be able to report something without fear of being identified by the staff member they are reporting.

```
LEARNER (portal or mobile)
  │  "Tell someone" — one tap from the home screen, no menu diving
  │  Category, free text, optional name of who it concerns
  │  Choice: report as myself, or report anonymously
  ▼
ANONYMOUS PATH
  │  anonymous_token generated and shown ONCE to the learner
  │  reporter_user_id is NOT stored ⭐
  │  The concern reaches the safeguarding lead with no reporter identity
  │  The learner can return with their token to see a response
  ▼
SAFEGUARDING LEAD triages
```

| # | Rule |
|---|---|
| The anonymous path stores **no** link to the learner's account. Not encrypted, not hashed — absent. |
| Application logs, request logs, and analytics must not record the submitting session for anonymous reports. This requires an explicit exclusion in the logging middleware and is tested. |
| The token is shown once and is the only way back to the report. It is the learner's, not the school's. |
| A learner reporting about themselves may still choose anonymity, and the lead may act on the content without knowing who sent it. |
| The "tell someone" entry point is one tap from the app home screen, always, with no ability to hide it. |

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-BRD-08-001` ⭐ | Vendor super-administrators and any impersonating session have **no access** to concerns, cases, entries, counselling sessions, or the safeguarding audit stream. Enforced at the policy layer as the first check. An attempt is logged and alerts the safeguarding lead. |
| `BR-BRD-08-002` ⭐ | Access to a case requires being the safeguarding lead for that school, holding an active per-case grant, or using break-glass. Role alone never suffices. |
| `BR-BRD-08-003` | Grants name a reason, are time-boxed by default, and are individually revocable. Granting is itself audited. |
| `BR-BRD-08-004` ⭐ | Break-glass access is permitted and immediately alerts the safeguarding lead and the head, and writes a prominent permanent record. It is never silent. |
| `BR-BRD-08-005` ⭐ | **Every read** of a case, entry, counselling note or concern writes to `safeguarding_audit`. Reads, not just writes. |
| `BR-BRD-08-006` | `safeguarding_audit` is append-only and hash-chained, in a stream separate from the general audit log, verified nightly. |
| `BR-BRD-08-007` | Concerns and cases cannot be deleted by anyone at any permission level. No deletion path exists in the code. |
| `BR-BRD-08-008` | Case entries are append-only. A correction is a new entry referencing the earlier one. |
| `BR-BRD-08-009` | Reporting a concern is deliberately low-friction: minimal required fields, available from every learner and staff surface, no approval to submit. **The cost of a report that turns out to be nothing is far lower than the cost of one never made.** |
| `BR-BRD-08-010` | Concerns marked `immediate_risk` alert the safeguarding lead and a designated deputy immediately on all channels, bypassing quiet hours and cost caps. |
| `BR-BRD-08-011` | Anonymous reports store no link to the reporting account. Middleware excludes them from request logging. |
| `BR-BRD-08-012` | Every concern is triaged with a recorded rationale, including those resulting in no further action. A concern closed without a reason is not closed. |
| `BR-BRD-08-013` | `guardians_informed` may legitimately be `false` where informing a guardian would place the child at greater risk. The reason is recorded and encrypted. **The system must support not telling a parent**, because sometimes the parent is the risk. |
| `BR-BRD-08-014` | Agency referrals record the legal basis for sharing information. |
| `BR-BRD-08-015` | Counselling notes are visible to the recording counsellor and the safeguarding lead only. Not to the head by default; the head may be granted per case. |
| `BR-BRD-08-016` | A counselling session with `risk_indicators_present = 1` prompts the counsellor to escalate. The system prompts; the counsellor decides and the decision is recorded. |
| `BR-BRD-08-017` | Only the **existence** of a safeguarding flag propagates to `PPL-01` (`has_safeguarding_flag`). No category, no detail, no case reference. |
| `BR-BRD-08-018` | Learners on the vulnerable register receive periodic review; overdue reviews escalate. |
| `BR-BRD-08-019` | Risk assessments carry a mandatory review date; overdue assessments appear on the lead's dashboard. |
| `BR-BRD-08-020` | Case data is retained far longer than general learner data, per `CMP-03`, and is excluded from every routine archival and purge process. Transfer of a case to another school is a deliberate, documented act. |
| `BR-BRD-08-021` | Safeguarding data is excluded from every general export, every report builder, every BI dataset, and every backup accessible to non-safeguarding staff. Backups containing it are separately encrypted. |
| `BR-BRD-08-022` | The system makes no automated decision about any child in this module. Every escalation, referral and closure is a named human's decision, recorded. |

### 6. Screens

| Screen | Component | Access |
|---|---|---|
| **Report a concern** | `Welfare\Safeguarding\Report` | any staff — one screen, minimal fields |
| Triage queue | `Welfare\Safeguarding\Triage` | safeguarding lead |
| Case list | `Welfare\Safeguarding\Cases` | lead, or granted |
| Case record | `Welfare\Safeguarding\Case` | lead, or granted — chronology, risk assessments, referrals, reviews |
| Add entry | `Welfare\Safeguarding\Entry` | contribute-level grant |
| **Access grants** | `Welfare\Safeguarding\Grants` | lead only ⭐ — who can see this case, why, until when |
| Risk assessment | `Welfare\Safeguarding\RiskAssessment` | lead, or granted |
| Agency referrals | `Welfare\Safeguarding\Referrals` | lead |
| Counselling diary | `Welfare\Counselling\Diary` | counsellor — own sessions |
| Session record | `Welfare\Counselling\Session` | recording counsellor + lead |
| Vulnerable register | `Welfare\Safeguarding\Vulnerable` | lead, pastoral team |
| **Audit review** | `Welfare\Safeguarding\Audit` | lead + governor role ⭐ — every access, break-glass highlighted |
| Overdue reviews | `Welfare\Safeguarding\Reviews` | lead |

### 7. API endpoints

```
POST /api/v1/safeguarding/report              staff or learner; low friction
POST /api/v1/safeguarding/report/anonymous    ⭐ no reporter identity stored
GET  /api/v1/safeguarding/anonymous/{token}   learner follow-up
GET  /api/v1/safeguarding/cases               lead or granted only
GET  /api/v1/safeguarding/cases/{ulid}        ⭐ every call audited
POST /api/v1/safeguarding/cases/{ulid}/entries
POST /api/v1/safeguarding/cases/{ulid}/grants lead only
POST /api/v1/safeguarding/break-glass         ⚠ alerts on use
```

### 8. Permissions · Settings · Events

```
safeguarding.report                  -- every staff member; also learners via portal
safeguarding.lead ⚠⚠                 -- the designated safeguarding lead
safeguarding.deputy_lead ⚠⚠
safeguarding.case.view               -- candidacy only; still requires a grant
safeguarding.case.contribute
safeguarding.grant.manage ⚠⚠         -- lead only
safeguarding.emergency_access ⚠⚠     -- break-glass; alerts on every use
safeguarding.counselling.record      -- counsellors
safeguarding.audit.review ⚠⚠         -- lead + governor
safeguarding.vulnerable.manage
```

**No permission in this list may be assigned to a vendor role.** The role seeder enforces this and a test asserts it.

| Setting | Type | Default |
|---|---|---|
| `safeguarding.lead_staff_id` | int | required at setup |
| `safeguarding.deputy_lead_staff_id` | int | required at setup |
| `safeguarding.immediate_risk_bypasses_quiet_hours` | bool | `true` (**locked**) |
| `safeguarding.default_grant_expiry_days` | int | `30` |
| `safeguarding.anonymous_reporting_enabled` | bool | `true` (**cannot be disabled**) |
| `safeguarding.learner_report_entry_point_visible` | bool | `true` (**locked**) |
| `safeguarding.retention_years_after_exit` | int | `25` |
| `safeguarding.review_frequency_days` | int | `30` |

Events: `ConcernReported` ⚠ · `ImmediateRiskConcern` ⚠⚠ · `CaseOpened` · `AccessGranted` · `AccessRevoked` · `BreakGlassUsed` ⚠⚠ · `VendorAccessBlocked` ⚠⚠ · `AgencyReferralMade` · `RiskLevelChanged` · `ReviewOverdue` · `CaseClosed`

### 9. Acceptance criteria

```gherkin
AC-BRD-08-001
  Given a vendor super-administrator
  When they request any safeguarding case, concern, entry or audit record
  Then access is denied at the policy layer
  And the attempt is logged in the hardened audit stream
  And the safeguarding lead is alerted

AC-BRD-08-002
  Given a support engineer is impersonating a school user
  When they attempt to view a safeguarding case
  Then access is denied
  Regardless of the impersonated user's own access

AC-BRD-08-003
  Given a Head with no grant on a case
  When they attempt to view it
  Then access is denied
  And the attempt is logged

AC-BRD-08-004
  Given a housemaster is granted read access to one case for 14 days
  Then they can view that case only
  And no other case
  And access expires automatically after 14 days

AC-BRD-08-005
  Given a counsellor views a case entry
  Then a safeguarding_audit row records the read, the user, the access basis,
      the IP and the time
  And the hash chains to the previous entry

AC-BRD-08-006
  Given break-glass access is used
  Then access is granted
  And the safeguarding lead and head are alerted immediately
  And a prominent permanent record exists

AC-BRD-08-007
  Given a learner submits an anonymous report
  Then no reporter_user_id is stored
  And no request log, analytics event, or application log records the session
  And the learner can follow up using only their token

AC-BRD-08-008
  Given any user at any permission level
  When they attempt to delete a concern, case, or entry
  Then no code path exists to do so

AC-BRD-08-009
  Given a case where informing the guardian would increase risk to the child
  Then guardians_informed can be set to false with an encrypted recorded reason
  And no notification is sent to the guardian

AC-BRD-08-010
  Given a safeguarding case exists for a learner
  When any general export, report builder query, or BI dataset is produced
  Then no safeguarding data appears in it
  And only the existence flag is visible on the learner record

AC-BRD-08-011
  Given a learner opens the mobile app
  Then a "tell someone" entry point is visible from the home screen
  And it cannot be disabled by school configuration

AC-BRD-08-012
  Given a concern is triaged to no further action
  Then a rationale is mandatory
  And the concern remains permanently

AC-BRD-08-013
  Given the safeguarding audit chain is verified nightly
  When any record has been altered directly in the database
  Then verification fails at that sequence
  And a critical alert is raised
```

---

## Part 3 — Domain E Welfare Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **G1** | `BRD-06` records, conditions, tiered access | **Tier 3 fields absent from Tier 2 API responses** |
| **G2** | `BRD-06` care plans, alerts, dietary and accommodation feeds | `BRD-04` and `BRD-01` stubs closed |
| **G3** | `BRD-06` sick bay, observations, referrals | `BRD-02` sick_bay and hospital stubs closed |
| **G4** | `BRD-06` consent, prescriptions, medication round | **Append-only enforced at DB grant; no dose without consent** |
| **G5** | `BRD-06` incidents, stock, controlled register, outbreak | Two-person controlled handling |
| **G6** | `BRD-07` categories, records, points, conduct grade | Positive recording as easy as negative; `ACA-05` fed |
| **G7** | `BRD-07` sanctions, approval, detention, suspension | `BRD-02` detention and suspended stubs closed |
| **G8** | `BRD-07` committee, appeals, leadership, analytics | Overturned sanctions preserved, not deleted |
| **G9** | `BRD-07` safeguarding routing | Trigger categories pause the disciplinary process |
| **G10** | `BRD-08` concerns, anonymous reporting, triage | **No reporter identity stored or logged on the anonymous path** |
| **G11** | `BRD-08` cases, per-case grants, hardened audit | **Vendor exclusion enforced at policy layer** |
| **G12** | `BRD-08` risk assessment, referrals, vulnerable register, review | Overdue reviews escalate |

---

## Part 4 — Domain E Welfare Acceptance Gate

> This gate is stricter than any other in the specification. Nothing here is deferrable.

### Access control

- [ ] Tier 3 medical fields are **absent** from Tier 2 API responses, not merely hidden
- [ ] `hasCareResponsibility()` resolves current relationships only; last year's teacher has no Tier 2 access
- [ ] Vendor super-admin access to `BRD-08` is denied at the policy layer and logged
- [ ] Impersonating sessions are denied `BRD-08` access regardless of the impersonated user
- [ ] Case access requires lead status, an active grant, or break-glass — role alone never suffices
- [ ] Grants are time-boxed, reasoned, individually revocable, and expire automatically
- [ ] Break-glass grants access and alerts the lead and head immediately
- [ ] No safeguarding permission can be assigned to any vendor role — asserted by test

### Auditing

- [ ] Every **read** of clinical, counselling and safeguarding records writes an audit entry
- [ ] `safeguarding_audit` is a separate, append-only, hash-chained stream, verified nightly
- [ ] `medication_administrations` confirmed `INSERT`/`SELECT` only at database grant level
- [ ] No deletion code path exists for concerns, cases, entries, or medication records

### Anonymity

- [ ] Anonymous reports store no reporter identity anywhere
- [ ] Request logs, application logs and analytics exclude anonymous submissions — verified by inspecting logs during the test
- [ ] The token is the only route back to the report
- [ ] The learner "tell someone" entry point is present and cannot be configured away

### Consent and medication

- [ ] No medication is administered without a valid, unwithdrawn consent
- [ ] Controlled medication requires a second witness on every action
- [ ] Expired stock cannot be selected
- [ ] Emergency treatment without consent is recorded as such with the deciding person named

### Stub closure

- [ ] `sick_bay`, `hospital`, `detention` and `suspended` roll statuses all populate correctly in `BRD-02`
- [ ] Dietary requirements flow to `BRD-04` carrying `public_summary` only
- [ ] Accommodation constraints flow to `BRD-01` carrying no diagnosis
- [ ] Conduct grades flow to `ACA-05`
- [ ] `has_safeguarding_flag` on `PPL-01` carries existence only

### Data protection

- [ ] All clinical and safeguarding free text is encrypted at rest with a separate key
- [ ] Safeguarding data is absent from every general export, report builder output and BI dataset
- [ ] Retention periods exceed general learner data and are excluded from routine purges

### Quality

- [ ] **Coverage ≥ 95% for `BRD-08` policy and audit code**
- [ ] Coverage ≥ 90% for `BRD-06` access tiering and medication
- [ ] Tenancy isolation suite passes for every model in this book
- [ ] A penetration test specifically targeting `BRD-08` access control is completed before any school goes live with the module

---

## Appendix A — Visibility Matrix

Who sees what, across all three modules. This table is the specification; if code disagrees with it, code is wrong.

| Data | Teacher | Housemaster | Matron | Nurse | Counsellor | Head | S/G Lead | Guardian | Learner | Vendor |
|---|---|---|---|---|---|---|---|---|---|---|
| Medical alert exists | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | own | own | ✅ |
| Care plan (Tier 2) | care resp. | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | own | own | ❌ |
| Clinical record (Tier 3) | ❌ | ❌ | ❌ | ✅ | ❌ | grant | grant | own | partial | ❌ |
| Medication record | ❌ | ❌ | ✅ | ✅ | ❌ | grant | grant | own | ❌ |❌ |
| Sick bay status | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | own | own | ✅ |
| Behaviour record | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | own | own | ✅ |
| Sanction | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | own | own | ✅ |
| Committee minutes | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | own | ❌ | ❌ |
| Counselling session | ❌ | ❌ | ❌ | ❌ | own | ❌ | ✅ | ❌ | ❌ | ❌ |
| Safeguarding flag exists | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Safeguarding case | grant | grant | grant | grant | grant | grant | ✅ | ❌ | ❌ | **❌** |
| Safeguarding audit | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | **❌** |

`grant` = only with an active per-case access grant. `care resp.` = only where the user currently has care responsibility for that learner. `own` = own record or own child. **Bold ❌** = hard-blocked at the policy layer with an alert on attempt.

---

## Appendix B — Remaining Books

| Book | Domain | Modules |
|---|---|---|
| **H** | Operations, Payroll & Compliance | `OPS-01`–`OPS-07`, `PPL-05` payroll, `FIN-08`–`FIN-14`, `CMP-01`–`CMP-04` |
| **I** | Communication & Portals | `COM-01` → `COM-08` |
| **J** | Intelligence & SaaS Control | `INT-01`–`INT-04`, `SAA-01`–`SAA-03` |

Book H is the largest remaining and closes the `FIN-09` store dependency from Book F, the farm-to-kitchen transfer, and the ZIMSEC candidate registration interface opened in Book E.

---

*End of Volume 2, Book G.*

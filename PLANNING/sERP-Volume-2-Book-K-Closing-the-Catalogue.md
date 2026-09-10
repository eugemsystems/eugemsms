# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book K — Closing the Catalogue (`FIN-07`, `PPL-06`, `ACA-08`–`ACA-11`)

| Field | Value |
|---|---|
| Document | Volume 2, Book K of 11 — the final book |
| Covers | Scholarships, Bursaries & Discounts · Alumni & Institutional Development · Learning Management System · Computer-Based Testing · Library & Textbook Management · Teaching Quality & Supervision |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | Books A–J complete. `FIN-07` in particular should be built **before** any team treats `FIN-02` as finished — see §0.1. |
| Companion | None. This closes Volume 1's 78-module catalogue in full. See the closing appendix. |

---

## Part 0 — Why This Book Exists

### 0.1 ⭐ `FIN-07` is not a nice-to-have — it is a hole in Book B

Book B's fee pipeline diagram, written months ago in this project, contains this line without ever specifying what it means:

```
FIN-02 §3, step 4:  "DISCOUNTS (FIN-07 hook)
                     apply matching discounts per component
                     discount_minor recorded separately — gross is NEVER reduced ⭐"
```

`FIN-02` computes gross correctly. `FIN-03` posts `discount_minor` on an invoice line correctly. `PPL-03`'s `fee_liabilities` table references sibling and sponsor arrangements correctly. **None of that means anything without `FIN-07` defining what a discount actually is, who approved it, what budget it draws against, and how a school stays financially honest about the true cost of its own generosity.** This book writes that module first, because every other module that references it has been waiting.

### 0.2 The other five modules

`PPL-06` (Alumni), `ACA-08` (LMS), `ACA-09` (CBT), `ACA-10` (Library), `ACA-11` (Teaching Supervision) were correctly scoped in Volume 1 and correctly sequenced to a later build phase — they were never broken dependencies the way `FIN-07` was. They are specified here to the same standard as everything else, because "later phase" in a roadmap is a build-order decision, not licence to leave a module undocumented indefinitely.

### 0.3 Build order

```
FIN-07  Scholarships & Discounts   ← retro-fits into FIN-02/FIN-03, build first
   ↓
ACA-08  LMS            ─┐
ACA-09  CBT              ├─ ACA-09 optionally extends ACA-08's gradebook sync
ACA-10  Library         ─┘   independent of both
ACA-11  Teaching Supervision  independent
   ↓
PPL-06  Alumni          ← consumes ACA-05 result history and OPS-07 awards on graduation
```

---

# FIN-07 · Scholarships, Bursaries & Discounts ⭐

### 1. Scope

**In scope.** The discount and scholarship catalogue, automatic and individually-granted awards, means-assessment applications, budget envelopes per scheme, sponsor-funded awards that invoice the sponsor rather than write off income, full GL treatment of discounting.

**Out of scope.** Fee computation itself (`FIN-02`, which calls into this module). Sponsor liability posting mechanics (`PPL-03`'s `fee_liabilities`, which this module's sponsor awards feed).

### 2. Data model

```sql
discount_schemes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                     VARCHAR(30)  NOT NULL   -- 'SIBLING','STAFF_CHILD',
                                                 -- 'ACADEMIC_SCHOLARSHIP',
                                                 -- 'ORPHAN_BURSARY','SPORTS'
name                       VARCHAR(150) NOT NULL
scheme_type                   VARCHAR(20)  NOT NULL   -- automatic|application_based|
                                                       -- individually_granted
category                         VARCHAR(30)  NOT NULL   -- sibling|staff|
                                                          -- academic|sport|
                                                          -- hardship|orphan|
                                                          -- corporate|church|
                                                          -- early_settlement
calculation_method                  VARCHAR(20)  NOT NULL   -- percentage|
                                                             -- fixed_amount|
                                                             -- tiered
applies_to_components                   JSON         NULL   -- null = all components
default_percent                            DECIMAL(5,2) NULL
default_amount_minor                          BIGINT       NULL
currency                                         CHAR(3)      NULL
tier_bands                                          JSON         NULL   -- for
                                                                        -- sibling: nth
                                                                        -- child bands
requires_means_assessment                              TINYINT(1)   NOT NULL DEFAULT 0
requires_academic_threshold                                TINYINT(1)   NOT NULL DEFAULT 0
minimum_average_percent                                       DECIMAL(5,2) NULL
requires_approval                                                TINYINT(1)   NOT NULL DEFAULT 1
approval_chain_id                                                   BIGINT       NULL FK
is_sponsor_funded                                                      TINYINT(1)   NOT NULL DEFAULT 0
contra_account_id                                                         BIGINT       FK
                                                                                        -- 4190
                                                                                        -- Fee
                                                                                        -- Discounts
                                                                                        -- (Book B)
renewal_frequency                                                            VARCHAR(20)  NULL
                                                                                          -- termly|
                                                                                          -- annual|
                                                                                          -- once
is_active                                                                       TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

scheme_budget_envelopes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
scheme_id                  BIGINT       FK INDEX
academic_year_id              BIGINT       FK
budget_minor                     BIGINT       NULL   -- null = uncapped
                                                      -- (e.g. staff-child, sibling)
currency                            CHAR(3)      NULL
committed_minor                        BIGINT       NOT NULL DEFAULT 0
utilised_minor                            BIGINT       NOT NULL DEFAULT 0
  UNIQUE (school_id, scheme_id, academic_year_id)

scholarship_applications
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id           BIGINT       FK
scheme_id                     BIGINT       FK INDEX
student_id                       BIGINT       FK INDEX
applied_by_guardian_id               BIGINT       NULL FK
household_income_band                    VARCHAR(30)  NULL
supporting_document_ids                     JSON         NULL
means_assessment_score                         DECIMAL(5,2) NULL
academic_average_at_application                   DECIMAL(5,2) NULL
narrative                                            TEXT         NULL
status                                                  VARCHAR(20)  NOT NULL   -- draft|
                                                                                -- submitted|
                                                                                -- under_review|
                                                                                -- committee_review|
                                                                                -- approved|
                                                                                -- rejected|
                                                                                -- waitlisted
committee_notes                                            TEXT         NULL
decided_by                                                    BIGINT       NULL FK
decided_at                                                       TIMESTAMP    NULL
rejection_reason                                                    VARCHAR(255) NULL
  INDEX (school_id, academic_year_id, scheme_id, status)

awards                                -- ⭐ the granted instance; what FIN-02 reads
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
scheme_id                  BIGINT       FK INDEX
student_id                    BIGINT       FK INDEX
application_id                    BIGINT       NULL FK
academic_year_id                     BIGINT       FK
term_id                                  BIGINT       NULL FK   -- null = whole year
applies_to_components                       JSON         NULL   -- overrides scheme
                                                                 -- default if set
award_method                                   VARCHAR(20)  NOT NULL   -- percentage|
                                                                        -- fixed_amount
award_percent                                     DECIMAL(5,2) NULL
award_amount_minor                                   BIGINT       NULL
currency                                                CHAR(3)      NULL
sponsor_guardian_id                                        BIGINT       NULL FK
                                                                        -- ⭐ if
                                                                        -- sponsor-funded,
                                                                        -- links to
                                                                        -- PPL-03
effective_from                                                DATE         NOT NULL
effective_to                                                      DATE         NULL
status                                                               VARCHAR(20)  NOT NULL   -- active|
                                                                                              -- suspended|
                                                                                              -- ended|
                                                                                              -- revoked
condition_note                                                          VARCHAR(255) NULL
                                                                                       -- 'maintain
                                                                                       -- 60%
                                                                                       -- average'
condition_last_checked_at                                                  TIMESTAMP    NULL
condition_met                                                                 TINYINT(1)   NULL
granted_by                                                                       BIGINT       FK → users.id
approval_request_id                                                                 BIGINT       NULL FK
revoked_reason                                                                        VARCHAR(255) NULL
  UNIQUE (school_id, scheme_id, student_id, academic_year_id, term_id)
  INDEX  (school_id, student_id, status)
  INDEX  (school_id, scheme_id, academic_year_id)

award_utilisation                     -- APPEND-ONLY; one row per term this award billed
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
award_id                  BIGINT       FK INDEX
term_id                     BIGINT       FK
component_id                   BIGINT       FK
discount_minor                    BIGINT       NOT NULL
currency                             CHAR(3)      NOT NULL
fee_line_id                            BIGINT       FK   -- FIN-02 learner_fee_lines
journal_id                                BIGINT       FK   -- FIN-01
posted_at                                    TIMESTAMP    NOT NULL
  INDEX (school_id, award_id, term_id)
```

### 3. ⭐ The `FIN-02` hook, specified

This is what "apply matching discounts per component" in Book B actually resolves to.

```php
interface DiscountResolver   // the interface FIN-02's billing pipeline calls
{
    public function discountsFor(
        Student $student,
        FeeComponent $component,
        Money $grossAmount,
        Term $term,
    ): Collection;   // Collection<DiscountApplication>
}

final class AwardDiscountResolver implements DiscountResolver
{
    public function discountsFor(Student $s, FeeComponent $c, Money $gross, Term $t): Collection
    {
        $awards = Award::where('student_id', $s->id)
            ->where('status', 'active')
            ->where('academic_year_id', $t->academic_year_id)
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $t->id))
            ->get()
            ->filter(fn ($a) => $a->appliesToComponent($c));

        return $awards->map(function (Award $award) use ($gross, $c, $t) {
            // ⭐ Budget envelope check — an award never silently exceeds its scheme's cap
            $envelope = $this->envelopes->for($award->scheme_id, $t->academic_year_id);
            $discount = $award->computeDiscount($gross);

            if ($envelope !== null && $envelope->wouldExceed($discount)) {
                $this->flagForReview($award, $discount, $envelope);
                return DiscountApplication::blocked($award, 'budget_envelope_exceeded');
            }

            return DiscountApplication::apply($award, $discount, contraAccount: $award->scheme->contra_account_id);
        });
    }
}
```

**What `FIN-02` does with the result, exactly as Book B specified:** `gross_minor` is never touched. `discount_minor` accumulates the sum of every applied `DiscountApplication`. `net_minor = gross_minor − discount_minor`. The invoice line carries all three figures, and the discount posts `Dr Fee Discount Contra (4190) / Cr Fee Debtors` — never a reduction of the income line itself, so the school's gross-billed figure in every report remains the true sticker price regardless of how much of it was waived.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-07-001` ⭐ | `FIN-02`'s billing pipeline resolves discounts exclusively through `DiscountResolver`. There is no other code path that reduces a fee line. |
| `BR-FIN-07-002` | Automatic schemes (sibling, staff-child) evaluate their eligibility rule against the learner's live data on every billing run — a sibling discount is not a one-time grant, it re-evaluates as the household's enrolled-child count changes. |
| `BR-FIN-07-003` | Sibling tier bands are configured per school (a common pattern: 2nd child 10%, 3rd child 15%, 4th and beyond 20%) and evaluated against active, non-withdrawn siblings in the same household at the point of billing. |
| `BR-FIN-07-004` | Staff-child discounts read the staff member's active contract status from `PPL-04`; a discount does not survive the staff member's exit beyond the configured notice period. |
| `BR-FIN-07-005` | Application-based schemes require a submitted `scholarship_applications` record before an award can be granted; an award cannot be created standalone for a scheme that requires an application. |
| `BR-FIN-07-006` | Means-assessed applications record household income band and supporting documents; the assessment score and decision rationale are retained permanently, since scholarship decisions are exactly the kind of record a school must be able to defend years later. |
| `BR-FIN-07-007` | Academic scholarships validate the stated minimum average against `ACA-05`'s actual term results at renewal, not against a self-reported figure. |
| `BR-FIN-07-008` | Awards above `finance.award_approval_threshold_minor` or from any scheme with `requires_approval = 1` route through `CORE-07` before taking effect. |
| `BR-FIN-07-009` ⭐ | An award drawing against a capped `scheme_budget_envelopes` row is refused, not silently over-committed, when granting it would exceed the envelope. The refusal names the shortfall and requires either a budget increase or a different scheme. |
| `BR-FIN-07-010` ⭐ | A sponsor-funded award (`is_sponsor_funded = 1`) does **not** post a discount to the contra account. It creates or updates a `PPL-03` `fee_liabilities` row against the `sponsor_guardian_id`, so the sponsoring organisation is invoiced the full amount — the school's income is completely unaffected; only the payer changes. |
| `BR-FIN-07-011` | A conditional award (`condition_note` set) is checked at the configured review point — typically each term's results publication — and a failed condition suspends the award pending human review, never auto-revokes without a recorded decision. |
| `BR-FIN-07-012` | Award revocation requires a reason, does not retroactively re-invoice already-billed terms, and takes effect from the specified date forward. |
| `BR-FIN-07-013` | `award_utilisation` is append-only and is the audit trail answering, for any scheme in any year, exactly how much was granted, to whom, and posted to which journals. |
| `BR-FIN-07-014` | The school always sees, per scheme and in aggregate: gross fees that would have been billed, total discount granted, and net billed — the true cost of the school's own generosity, never obscured by netting it into the headline income figure. |
| `BR-FIN-07-015` | A withdrawn or transferred learner's active awards end automatically on their exit date, consistent with every other billing attribute change in `PPL-01`. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Discount schemes | `Finance\Discounts\Schemes` | `finance.discount_scheme.manage` — automatic rule definition, tier bands |
| **Budget envelopes** | `Finance\Discounts\Budgets` | `finance.discount_scheme.manage` — committed vs utilised, live |
| Applications | `Finance\Scholarships\Applications` | `finance.scholarship.review` — means data, documents |
| **Committee review** | `Finance\Scholarships\Committee` | `finance.scholarship.decide` — panel view, scoring, decision |
| Awards | `Finance\Awards\Index` | `finance.award.view` — by scheme, by learner, status |
| Grant award | `Finance\Awards\Grant` | `finance.award.grant` ⚠ — envelope check shown live before submission |
| Condition review | `Finance\Awards\ConditionReview` | `finance.award.review` — renewal-point academic/behavioural checks |
| **Cost of generosity report** | `Finance\Reports\Discounts` | `finance.report.view` — gross billed, discount granted, net, by scheme |
| Sponsor awards | `Finance\Awards\Sponsors` | `finance.award.grant` — links to `PPL-03` sponsorships |

### 6. API endpoints

```
POST /api/v1/scholarships/applications           guardian submits
GET  /api/v1/scholarships/applications/mine       guardian tracks status
GET  /api/v1/students/{ulid}/awards               guardian: own child's active awards
```

### 7. Permissions · Settings · Events

```
finance.discount_scheme.view       finance.discount_scheme.manage
finance.scholarship.apply          finance.scholarship.review
finance.scholarship.decide ⚠       finance.award.view
finance.award.grant ⚠              finance.award.revoke ⚠
finance.award.review               finance.report.discounts
```

| Setting | Type | Default |
|---|---|---|
| `finance.award_approval_threshold_minor` | int | `0` (all require approval) |
| `finance.sibling_discount_bands` | json | seeded per §3 example |
| `finance.condition_review_trigger` | enum | `on_results_publication` |
| `finance.academic_scholarship_min_average` | decimal | school-set |

Events: `SchemeCreated` · `ApplicationSubmitted` · `ApplicationDecided` · `AwardGranted` · `AwardBudgetExceeded` ⚠ · `AwardConditionFailed` · `AwardSuspended` · `AwardRevoked` · `SponsorAwardLinked` (→ `PPL-03`)

### 8. Acceptance criteria

```gherkin
AC-FIN-07-001
  Given a second sibling enrols mid-year in a household already billing
       one active learner
  When the next billing run computes their fees
  Then the sibling discount tier for a second child applies automatically
  Without any manual award being created

AC-FIN-07-002
  Given a staff member's contract ends
  Then their children's staff-child discount ends per the configured
       notice period, not immediately and not indefinitely

AC-FIN-07-003
  Given a scheme's budget envelope for the year is USD 20,000 with
       USD 19,500 already committed
  When an award requiring USD 1,000 is granted
  Then it is refused, naming the USD 500 shortfall

AC-FIN-07-004
  Given a sponsor-funded award covers a learner's full boarding fee
  When the term is billed
  Then the sponsor's fee_liabilities row is invoiced the full amount
  And no discount posts to the contra account
  And the school's gross fee income is unaffected

AC-FIN-07-005
  Given an academic scholarship requires a 60% average and the learner's
       actual average this term is 54%
  Then the award is flagged condition_met = false and suspended pending review
  It is not auto-revoked

AC-FIN-07-006
  Given the cost-of-generosity report is run for a term
  Then it shows gross billed, total discount, and net billed separately
  For every scheme, and the school's headline income figure is unaffected
       by how much was discounted

AC-FIN-07-007
  Given a learner withdraws mid-term with an active award
  Then the award ends on their exit date
  And no retroactive re-invoicing of already-billed terms occurs
```

---

# PPL-06 · Alumni & Institutional Development

### 1. Scope

**In scope.** Automatic alumni record creation on graduation, the alumni directory, career and further-education tracking, alumni portal, event management, donation and pledge management, capital campaign tracking, donor recognition, bursary endowment linkage.

**Out of scope.** The scholarship mechanics an endowment funds (`FIN-07`, which this module's endowed awards create records in). General event infrastructure (`COM-06`, which this module's alumni events use).

### 2. Data model

```sql
alumni
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id                 BIGINT       FK UNIQUE   -- the PPL-01 record it came from
user_id                       BIGINT       NULL FK   -- portal account, once created
admission_number                 VARCHAR(40)  NOT NULL   -- preserved from PPL-01
graduation_year                     SMALLINT     NOT NULL
final_grade_level_id                    BIGINT       FK
final_house_id                             BIGINT       NULL FK
academic_summary_snapshot                     JSON         NOT NULL   -- ⭐ frozen at
                                                                       -- graduation:
                                                                       -- results,
                                                                       -- awards,
                                                                       -- colours
current_occupation                               VARCHAR(200) NULL
current_employer                                    VARCHAR(200) NULL
further_education                                      VARCHAR(200) NULL
current_city                                              VARCHAR(100) NULL
current_country                                              CHAR(2)      NULL
is_notable                                                      TINYINT(1)   NOT NULL DEFAULT 0
contact_preferences                                                JSON         NULL
last_contact_at                                                       TIMESTAMP    NULL
status                                                                   VARCHAR(20)  NOT NULL   -- active|
                                                                                                  -- unreachable|
                                                                                                  -- deceased|
                                                                                                  -- opted_out
  UNIQUE (school_id, student_id)
  INDEX  (school_id, graduation_year)

alumni_career_updates                 -- self-reported, dated history
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
alumnus_id                 BIGINT       FK INDEX
update_type                   VARCHAR(20)  NOT NULL   -- education|employment|
                                                       -- achievement
title                            VARCHAR(200) NOT NULL
institution_or_employer             VARCHAR(200) NULL
starts_on                              DATE         NULL
ends_on                                   DATE         NULL
is_current                                   TINYINT(1)   NOT NULL DEFAULT 0
verified                                        TINYINT(1)   NOT NULL DEFAULT 0
submitted_at                                       TIMESTAMP    NOT NULL

alumni_house_groups                   -- year groups for reunions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
graduation_year             SMALLINT     NOT NULL
group_name                     VARCHAR(120) NULL   -- 'Class of 2015'
coordinator_alumnus_id             BIGINT       NULL FK
  UNIQUE (school_id, graduation_year)

alumni_events                         -- built on COM-06's calendar infrastructure
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
calendar_event_id           BIGINT       FK   -- COM-06
event_type                     VARCHAR(30)  NOT NULL   -- reunion|founders_day|
                                                        -- sports_gala|
                                                        -- fundraising_dinner
target_graduation_years              JSON         NULL   -- null = all
requires_ticket                         TINYINT(1)   NOT NULL DEFAULT 0

capital_campaigns
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
name                     VARCHAR(150) NOT NULL   -- 'New Science Block Appeal'
purpose                    TEXT         NOT NULL
target_amount_minor           BIGINT       NOT NULL
raised_amount_minor              BIGINT       NOT NULL DEFAULT 0
currency                            CHAR(3)      NOT NULL
starts_on                              DATE         NOT NULL
ends_on                                   DATE         NULL
income_account_id                            BIGINT       FK   -- FIN-01, 4250
                                                                -- Donations
status                                                            VARCHAR(20)  NOT NULL   -- active|
                                                                                          -- completed|
                                                                                          -- closed

pledges
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
campaign_id                BIGINT       NULL FK   -- null = general/unrestricted
alumnus_id                    BIGINT       NULL FK
donor_name                       VARCHAR(200) NOT NULL   -- external donors too
donor_type                          VARCHAR(20)  NOT NULL   -- alumnus|parent|
                                                             -- staff|corporate|
                                                             -- foundation
pledged_amount_minor                    BIGINT       NOT NULL
currency                                   CHAR(3)      NOT NULL
schedule                                      JSON         NULL   -- instalment plan
paid_to_date_minor                               BIGINT       NOT NULL DEFAULT 0
recognition_tier                                    VARCHAR(30)  NULL   -- bronze|
                                                                        -- silver|
                                                                        -- gold|
                                                                        -- platinum
is_anonymous                                           TINYINT(1)   NOT NULL DEFAULT 0
status                                                    VARCHAR(20)  NOT NULL   -- pledged|
                                                                                  -- fulfilling|
                                                                                  -- completed|
                                                                                  -- lapsed
  INDEX (school_id, campaign_id, status)

donations                             -- actual receipts against a pledge or ad hoc
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
pledge_id                   BIGINT       NULL FK
donor_name                     VARCHAR(200) NOT NULL
amount_minor                      BIGINT       NOT NULL
currency                             CHAR(3)      NOT NULL
received_at                             TIMESTAMP    NOT NULL
receipt_id                                 BIGINT       NULL FK   -- FIN-04
journal_id                                    BIGINT       NULL FK   -- FIN-01
is_restricted                                    TINYINT(1)   NOT NULL DEFAULT 0
restriction_purpose                                 VARCHAR(255) NULL
                                                                    -- 'endowed
                                                                    -- bursary
                                                                    -- for
                                                                    -- orphans'

bursary_endowments                    -- ⭐ links a donor to FIN-07's ongoing scheme
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
donor_name                VARCHAR(200) NOT NULL
alumnus_id                   BIGINT       NULL FK
endowment_capital_minor         BIGINT       NULL   -- if capital-funded
annual_commitment_minor            BIGINT       NULL   -- if ongoing-pledge funded
currency                              CHAR(3)      NOT NULL
funds_scheme_id                          BIGINT       FK   -- FIN-07
                                                            -- discount_schemes
named_recognition                           VARCHAR(200) NULL
                                                          -- 'The Moyo Family
                                                          -- Bursary'
starts_on                                     DATE         NOT NULL
status                                           VARCHAR(20)  NOT NULL   -- active|
                                                                        -- fully_utilised|
                                                                        -- ended
```

### 3. ⭐ Graduation — the moment this module's data is born

```
PPL-01 emits LearnerGraduated (an exit-level learner completes the year)
        │
        ▼
ACT-CreateAlumniRecord runs automatically:
   1  Freeze academic_summary_snapshot from ACA-05's term_results across
      every year the learner attended — final average, positions held,
      subjects taken — READ ONCE, stored, never re-queried live. A school's
      grading scale can change five years later; the alumnus's record must
      not silently reinterpret under it.
   2  Pull colours and honours from OPS-07 awards, same snapshot principle
   3  Create the alumni row, carrying admission_number forward permanently
   4  Assign to the correct alumni_house_group by graduation year
   5  Offer (not force) a portal account, distinct from their now-closed
      learner account — a graduated learner's PPL-01 record becomes
      read-only; their NEW identity here is the one they use going forward
```

**Why the snapshot, not a live query.** An alumnus asking "what was my final average?" ten years after leaving should get the same answer every time, exactly like every other historical figure in this specification. The academic system they graduated under may not exist in the same form by the time they ask.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-PPL-06-001` ⭐ | An alumni record creates automatically on `LearnerGraduated`. It is never manually created for a graduate, only for genuinely external additions (an alumnus who attended before the system's adoption). |
| `BR-PPL-06-002` | `academic_summary_snapshot` and honours are frozen at graduation and never recomputed against a later-changed grading scale or template. |
| `BR-PPL-06-003` | The original `PPL-01` learner record becomes read-only on graduation, consistent with every other exit status in that module's state machine; the alumni record is the forward-looking one. |
| `BR-PPL-06-004` | Career updates are self-reported by the alumnus and marked `verified = 0` until the school confirms them; unverified updates still display, clearly marked, since an alumni directory that only shows verified facts is mostly empty. |
| `BR-PPL-06-005` | Alumni events reuse `COM-06`'s calendar and ticketing infrastructure entirely — this module adds `target_graduation_years` targeting and nothing else to that system. |
| `BR-PPL-06-006` | A pledge's schedule is advisory; actual `donations` received are the only figures that post to the ledger. A pledge is a stated intention, not a receivable the school's books recognise as certain income. |
| `BR-PPL-06-007` | Every donation received posts through `FIN-04`/`FIN-01` exactly as any other receipt does — `Dr Bank / Cr Donation Income`, or to a restricted fund account where `is_restricted = 1`. |
| `BR-PPL-06-008` ⭐ | A `bursary_endowments` row links a donor to a `FIN-07` discount scheme. Donations against it accumulate; when the endowment's available balance can no longer fund the scheme's committed awards, the scheme's `scheme_budget_envelopes` reflects the shortfall exactly as any other capped scheme would, and `FIN-07`'s own budget-refusal rule applies unchanged. |
| `BR-PPL-06-009` | Named recognition (a school renaming a bursary after its donor) displays on the award itself in `FIN-07` and on any document referencing it, unless the donor opted for anonymity. |
| `BR-PPL-06-010` | Capital campaign progress is derived from `donations`, never manually adjusted, and drills through to every contributing receipt. |
| `BR-PPL-06-011` | An alumnus may opt out of contact at any point; opting out suppresses `COM-01`/`CORE-09` outreach immediately and permanently until they opt back in themselves. |
| `BR-PPL-06-012` | Alumni portal accounts are optional, separate from any residual access to the closed learner account, and carry their own narrow ability set — an alumnus token can never reach current learners' data. |

### 5. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Alumni directory | `Alumni\Directory\Index` | `alumni.view` — by year group, searchable |
| Alumnus profile | `Alumni\Directory\Show` | `alumni.view` — frozen academic summary, career history, giving history |
| Events | `Alumni\Events\Index` | `alumni.event.manage` — built on `COM-06` |
| **Campaigns** | `Alumni\Campaigns\Index` | `alumni.campaign.manage` — progress bar from real donations |
| Pledges | `Alumni\Pledges\Index` | `alumni.pledge.manage` |
| Record a donation | `Alumni\Donations\Record` | `alumni.donation.record` — posts through `FIN-04` |
| **Endowments** | `Alumni\Endowments\Index` | `alumni.endowment.manage` — linked scheme, available balance vs committed |

```
GET  /api/v1/alumni/me                        alumnus: own profile
PATCH /api/v1/alumni/me/career                submit a career update
GET  /api/v1/alumni/directory                 ?year=   privacy-respecting fields only
GET  /api/v1/campaigns/{ulid}/progress        public, for donation appeal pages
```

### 6. Acceptance criteria

```gherkin
AC-PPL-06-001
  Given a Form 6 learner completes their final year
  Then an alumni record is created automatically
  With their academic summary and honours frozen from ACA-05 and OPS-07

AC-PPL-06-002
  Given the school's grading scale changes five years after a learner graduated
  When that alumnus's profile is viewed
  Then their final average still displays exactly as it did at graduation

AC-PPL-06-003
  Given a pledge of USD 5,000 with USD 1,200 actually received
  Then the campaign progress bar reflects USD 1,200
  Not the pledged amount

AC-PPL-06-004
  Given a bursary endowment funds a FIN-07 scheme and its available
       balance is exhausted
  When a new award against that scheme is attempted
  Then FIN-07's ordinary budget-envelope refusal applies unchanged

AC-PPL-06-005
  Given an alumnus opts out of contact
  Then no further outreach reaches them through any channel
  Until they explicitly opt back in

AC-PPL-06-006
  Given an alumnus's portal token
  When it requests any current learner's data
  Then it is refused
```

---

# ACA-08 · Online Assignments & E-Learning (LMS)

### 1. Scope

**In scope.** Per-subject course spaces, content repository with offline-aware delivery, assignment creation and submission with deadline enforcement, marking with inline feedback, discussion threads, non-submission chasing, gradebook sync into `ACA-05`.

**Out of scope.** The mark aggregation itself (`ACA-05`, which this module feeds a coursework-category assessment into). File storage mechanics (`CORE-10`, which this module's content and submissions are stored through).

### 2. Data model

```sql
course_spaces
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id           BIGINT       FK
term_id                       BIGINT       FK
subject_id                       BIGINT       FK   -- ACA-01
teaching_group_id                   BIGINT       FK   -- ACA-02
teacher_staff_id                       BIGINT       FK
banner_image_file_id                      BIGINT       NULL FK
is_active                                    TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, term_id, teaching_group_id)

content_items
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
course_space_id            BIGINT       FK INDEX
content_type                  VARCHAR(20)  NOT NULL   -- note|worksheet|
                                                       -- past_paper|audio|
                                                       -- video|link|folder
title                            VARCHAR(200) NOT NULL
file_id                             BIGINT       NULL FK   -- CORE-10
external_url                           VARCHAR(500) NULL
file_size_bytes                            BIGINT       NULL
is_downloadable_offline                       TINYINT(1)   NOT NULL DEFAULT 1
                                                                     -- ⭐
                                                                     -- data-cost
                                                                     -- aware
published_at                                     TIMESTAMP    NULL
view_count                                          INT          NOT NULL DEFAULT 0
sort_order                                             SMALLINT

assignments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
course_space_id            BIGINT       FK INDEX
title                          VARCHAR(200) NOT NULL
instructions                     TEXT         NOT NULL
attachment_file_ids                  JSON         NULL
max_mark                                DECIMAL(6,2) NULL
rubric_id                                  BIGINT       NULL FK   -- reuses
                                                                  -- ACA-06's
                                                                  -- project_rubrics
                                                                  -- shape
assessment_type_id                            BIGINT       NULL FK   -- ACA-05,
                                                                      -- if it
                                                                      -- feeds
                                                                      -- the
                                                                      -- gradebook
opens_at                                         TIMESTAMP    NOT NULL
due_at                                              TIMESTAMP    NOT NULL
late_policy                                            VARCHAR(20)  NOT NULL   -- block|
                                                                                -- accept_penalised|
                                                                                -- accept_flagged
late_penalty_percent_per_day                              DECIMAL(5,2) NULL
allows_resubmission                                          TINYINT(1)   NOT NULL DEFAULT 0
submission_type                                                 VARCHAR(20)  NOT NULL   -- file|
                                                                                        -- text|
                                                                                        -- link|
                                                                                        -- both
status                                                             VARCHAR(20)  NOT NULL   -- draft|
                                                                                            -- published|
                                                                                            -- closed
  INDEX (school_id, course_space_id, due_at)

assignment_submissions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
assignment_id               BIGINT       FK INDEX
student_id                     BIGINT       FK INDEX
attempt_number                    TINYINT      NOT NULL DEFAULT 1
submitted_text                       TEXT         NULL
submitted_link                          VARCHAR(500) NULL
file_ids                                   JSON         NULL
submitted_at                                  TIMESTAMP    NULL
is_late                                          TINYINT(1)   NOT NULL DEFAULT 0
minutes_late                                        INT          NULL
similarity_flag                                        TINYINT(1)   NOT NULL DEFAULT 0
similarity_matches                                        JSON         NULL   -- other
                                                                              -- submission
                                                                              -- ids in
                                                                              -- the same
                                                                              -- class
raw_mark                                                     DECIMAL(6,2) NULL
penalty_applied_percent                                         DECIMAL(5,2) NULL
final_mark                                                          DECIMAL(6,2) NULL
feedback                                                               TEXT         NULL
marked_by                                                                 BIGINT       NULL FK
marked_at                                                                    TIMESTAMP    NULL
status                                                                          VARCHAR(20)  NOT NULL   -- not_submitted|
                                                                                                        -- draft|
                                                                                                        -- submitted|
                                                                                                        -- marked|
                                                                                                        -- returned
  UNIQUE (assignment_id, student_id, attempt_number)
  INDEX  (school_id, student_id, status)
  INDEX  (school_id, assignment_id, status)

discussion_threads
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
course_space_id            BIGINT       FK INDEX
title                          VARCHAR(200) NOT NULL
created_by                        BIGINT       FK → users.id
is_locked                            TINYINT(1)   NOT NULL DEFAULT 0
is_pinned                               TINYINT(1)   NOT NULL DEFAULT 0

discussion_posts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
thread_id                  BIGINT       FK INDEX
posted_by_type                 VARCHAR(20)  NOT NULL   -- staff|student
posted_by_id                       BIGINT       NOT NULL
content                                TEXT         NOT NULL
is_hidden                                 TINYINT(1)   NOT NULL DEFAULT 0
                                                                 -- moderation
hidden_by                                    BIGINT       NULL FK
posted_at                                       TIMESTAMP    NOT NULL

content_download_cache                -- ⭐ what's queued for offline on a device
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
user_id                 BIGINT       FK INDEX
content_item_id             BIGINT       FK
downloaded_at                   TIMESTAMP    NULL
device_id                          VARCHAR(120) NULL
  UNIQUE (user_id, content_item_id, device_id)
```

### 3. ⭐ Data-cost-aware content delivery

Every content item declares its size before a learner on a bundle decides whether to open it.

```
Content list screen shows, per item, BEFORE download:
   📄 Form 3 Physics — Momentum Notes.pdf     340 KB
   🎥 Practical Demo — Circular Motion.mp4    18 MB   ⚠ Large file — Wi-Fi recommended

is_downloadable_offline = false is used deliberately for large video content a
school wants viewable but not hoarding a low-end phone's storage — it streams
on request rather than queuing into the offline cache automatically.
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-08-001` | A course space exists per teaching group per term, mapped 1:1 from `ACA-02`'s teaching groups — there is no separate LMS-only class list to maintain. |
| `BR-ACA-08-002` | Content and assignments are visible to a learner only while they hold an active enrolment in that teaching group, consistent with every other module's enrolment-gated visibility. |
| `BR-ACA-08-003` | File size is always shown before download, and large files default to stream-on-request rather than automatic offline queueing, per `content_item.is_downloadable_offline`. |
| `BR-ACA-08-004` | Submission after `due_at` follows the assignment's declared `late_policy` — blocked outright, accepted with a computed percentage penalty, or accepted and simply flagged for the teacher's judgement. There is no fourth, undeclared behaviour. |
| `BR-ACA-08-005` | `final_mark = raw_mark × (1 − penalty_applied_percent)`, computed once at marking time and never silently recalculated if the late policy changes afterward. |
| `BR-ACA-08-006` | Similarity logging compares a submission against others in the **same class, same assignment**, flags matches above the configured threshold, and never auto-penalises — it surfaces a flag for the teacher to judge, exactly as `INT-03`'s risk indicators are advisory. |
| `BR-ACA-08-007` | Resubmission is only possible where `allows_resubmission = 1`, and each attempt is retained, not overwritten. |
| `BR-ACA-08-008` ⭐ | An assignment linked to an `ACA-05` `assessment_type_id` writes its final marks into that assessment's `assessment_marks` on marking — this is the sync point, and it is the same mark-entry path `ACA-05` already validates, not a parallel one. |
| `BR-ACA-08-009` | A teacher sees a live non-submission list for any assignment past its `due_at`, sortable, ready for one-tap chasing through `CORE-09`. |
| `BR-ACA-08-010` | Discussion posts are moderable; a hidden post remains in the database for audit but is invisible to learners, and hiding requires a reason logged against the moderator. |
| `BR-ACA-08-011` | Content and submission files respect `CORE-10`'s virus scanning and access-control rules unchanged — this module supplies the category and the visibility rule, not a second storage layer. |

### 5. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Course space | `Academic\Lms\CourseSpace` | teacher: manage; learner: view |
| **Assignment creation** | `Academic\Lms\AssignmentCreate` | `lms.assignment.create` — rubric attach, late policy, gradebook link |
| Marking | `Academic\Lms\Marking` | `lms.assignment.mark` — submission viewer, similarity flags, inline feedback |
| Non-submission chase | `Academic\Lms\NonSubmission` | `lms.assignment.mark` — one-tap reminder |
| Discussion | `Academic\Lms\Discussion` | course-space members |

```
GET  /api/v1/lms/my-courses               learner and teacher
GET  /api/v1/lms/courses/{ulid}/content
POST /api/v1/lms/assignments/{ulid}/submit    Idempotency-Key; offline queue
GET  /api/v1/lms/assignments/{ulid}/submissions   teacher
POST /api/v1/lms/submissions/{ulid}/mark
```

### 6. Acceptance criteria

```gherkin
AC-ACA-08-001
  Given a large video file is marked stream-only
  Then it does not auto-queue into offline cache
  And its file size is shown before the learner opens it

AC-ACA-08-002
  Given an assignment's late policy is accept_penalised at 10% per day
  And a submission arrives 2 days late with a raw mark of 80
  Then final_mark is 64

AC-ACA-08-003
  Given two submissions in the same class score above the similarity threshold
  Then both are flagged for teacher review
  And neither is automatically penalised

AC-ACA-08-004
  Given an assignment is linked to an ACA-05 assessment type
  When marking completes
  Then the final marks appear in that assessment through the normal
       ACA-05 mark-entry path, not a separate table

AC-ACA-08-005
  Given a learner's enrolment in a teaching group ends
  Then that course space's content becomes inaccessible to them from that date
```

---

# ACA-09 · Computer-Based Testing (CBT)

### 1. Scope

**In scope.** Question bank, paper assembly, randomisation, scheduled delivery with access windows, browser-focus monitoring, auto-save and resumption, automatic marking of objective items, manual marking queue for written items, item analysis, gradebook transfer.

**Out of scope.** Public examination administration (`ACA-07`) — CBT is internal assessment delivered on-screen; it is not a replacement for the invigilated, paper-based national examination process.

### 2. Data model

```sql
question_bank
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
subject_id                 BIGINT       FK INDEX
topic                         VARCHAR(150) NULL
syllabus_objective_ref            VARCHAR(80)  NULL
item_type                            VARCHAR(20)  NOT NULL   -- mcq|true_false|
                                                              -- matching|
                                                              -- fill_in|
                                                              -- short_answer|
                                                              -- essay|
                                                              -- file_upload
difficulty                              VARCHAR(20)  NOT NULL   -- easy|medium|hard
prompt                                     TEXT         NOT NULL
prompt_image_file_id                          BIGINT       NULL FK
options                                          JSON         NULL   -- for mcq/matching
correct_answer                                      JSON         NULL   -- null for
                                                                        -- essay/file
max_mark                                               DECIMAL(6,2) NOT NULL
is_auto_markable                                          TINYINT(1)   NOT NULL
difficulty_index                                             DECIMAL(5,2) NULL   -- ⭐
                                                                                  -- from
                                                                                  -- item
                                                                                  -- analysis
discrimination_index                                            DECIMAL(5,2) NULL
usage_count                                                        INT          NOT NULL DEFAULT 0
created_by                                                            BIGINT       FK → users.id
is_active                                                                TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, subject_id, topic, difficulty)

cbt_tests
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
title                     VARCHAR(200) NOT NULL
subject_id                   BIGINT       FK
assessment_type_id              BIGINT       NULL FK   -- ACA-05 gradebook link
assembly_method                    VARCHAR(20)  NOT NULL   -- manual|rule_based
assembly_rules                        JSON         NULL   -- {count:20, mix:
                                                           -- {easy:0.3,
                                                           -- medium:0.5,
                                                           -- hard:0.2},
                                                           -- topics:[...]}
question_ids                             JSON         NULL   -- for manual assembly
randomise_question_order                    TINYINT(1)   NOT NULL DEFAULT 1
randomise_option_order                         TINYINT(1)   NOT NULL DEFAULT 1
duration_minutes                                  SMALLINT     NOT NULL
opens_at                                             TIMESTAMP    NOT NULL
closes_at                                               TIMESTAMP    NOT NULL
browser_focus_monitoring                                   TINYINT(1)   NOT NULL DEFAULT 0
max_tab_switches                                              SMALLINT     NULL
status                                                           VARCHAR(20)  NOT NULL   -- draft|
                                                                                          -- scheduled|
                                                                                          -- open|
                                                                                          -- closed|
                                                                                          -- results_released

cbt_candidate_attempts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
test_id                     BIGINT       FK INDEX
student_id                     BIGINT       FK INDEX
seeded_question_order              JSON         NOT NULL   -- ⭐ fixed per
                                                            -- candidate at
                                                            -- start, for
                                                            -- reproducibility
started_at                                 TIMESTAMP    NULL
last_autosave_at                              TIMESTAMP    NULL   -- ⭐
extra_time_minutes                               SMALLINT     NOT NULL DEFAULT 0
                                                                  -- from
                                                                  -- ACA-07's
                                                                  -- special_arrangements
submitted_at                                        TIMESTAMP    NULL
auto_submitted                                         TINYINT(1)   NOT NULL DEFAULT 0
tab_switch_count                                          SMALLINT     NOT NULL DEFAULT 0
focus_events                                                 JSON         NULL   -- log,
                                                                                  -- not
                                                                                  -- auto-penalty
raw_mark                                                        DECIMAL(6,2) NULL
percent                                                            DECIMAL(5,2) NULL
status                                                                VARCHAR(20)  NOT NULL   -- not_started|
                                                                                                -- in_progress|
                                                                                                -- submitted|
                                                                                                -- auto_marked|
                                                                                                -- manual_marking_pending|
                                                                                                -- fully_marked|
                                                                                                -- flagged
  UNIQUE (test_id, student_id)
  INDEX  (school_id, test_id, status)

cbt_responses                         -- one per question per attempt, autosaved
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
attempt_id                 BIGINT       FK INDEX
question_id                   BIGINT       FK
response_value                   JSON         NULL
is_flagged_by_candidate              TINYINT(1)   NOT NULL DEFAULT 0
                                                            -- "review later"
auto_mark_correct                       TINYINT(1)   NULL
mark_awarded                               DECIMAL(6,2) NULL
manual_feedback                               TEXT         NULL
marked_by                                        BIGINT       NULL FK
last_saved_at                                       TIMESTAMP(3) NOT NULL
  UNIQUE (attempt_id, question_id)
```

### 3. ⭐ Auto-save and resumption — mandatory given load shedding

The single hardest constraint on this module. A candidate mid-test when power fails must resume exactly where they were, not restart.

```javascript
// client, fires on every answer change AND every 15 seconds regardless
async function autosaveResponse(questionId, value) {
  const payload = { question_id: questionId, response_value: value,
                     client_timestamp: Date.now() };
  try {
    await api.post(`/cbt/attempts/${attemptId}/responses`, payload,
                    { idempotencyKey: `${attemptId}:${questionId}:${payload.client_timestamp}` });
  } catch {
    queueLocally(payload);   // IndexedDB — survives a tab close, a crash, a reboot
  }
}

// on any reconnect or app restart:
async function resumeAttempt(attemptId) {
  const server = await api.get(`/cbt/attempts/${attemptId}`);
  const local = readLocalQueue(attemptId);
  const merged = mergeByLatestTimestamp(server.responses, local);   // never lose
                                                                      // the more
                                                                      // recent answer
  await syncQueued(local.filter(r => !server.responses[r.question_id]));
  return merged;
}
```

| # | Rule |
|---|---|
| The remaining time is computed **server-side** from `started_at + duration_minutes + extra_time_minutes`, never trusted from the client clock — a candidate's device clock is not authoritative for how long they have left. |
| A candidate reopening a test after a disconnection resumes with every previously saved response intact and the correct remaining time, not a fresh timer. |
| A test reaching its time limit auto-submits with whatever was saved — a candidate is never penalised beyond losing the time itself for a power interruption. |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-09-001` ⭐ | Every response autosaves within 15 seconds of a change and on every navigation, both to the server when reachable and to a local queue when not. No response is lost to a disconnection. |
| `BR-ACA-09-002` ⭐ | Remaining time is computed server-side from `started_at`, `duration_minutes`, and any `extra_time_minutes`. The client displays it; it never controls it. |
| `BR-ACA-09-003` | `extra_time_minutes` reads from `ACA-07`'s `special_arrangements` where an approved accommodation exists for that learner — this module does not maintain a second accommodations register. |
| `BR-ACA-09-004` | Question and option order randomise per candidate but are **seeded and stored** at attempt start (`seeded_question_order`), so a resumed attempt shows the identical order, not a re-shuffled one. |
| `BR-ACA-09-005` | Rule-based assembly draws from the question bank matching the configured difficulty mix and topic coverage; a bank with insufficient items for the rule fails assembly explicitly rather than silently repeating questions. |
| `BR-ACA-09-006` | Browser-focus monitoring **logs** tab switches and window-blur events; it does not itself penalise or auto-flag a script disqualification. A count beyond `max_tab_switches` sets `status = flagged` for human review, exactly as `ACA-08`'s similarity detection is advisory. |
| `BR-ACA-09-007` | Objective item types mark automatically and instantly on submission. Written item types queue to a manual marking screen. |
| `BR-ACA-09-008` | A test's final mark is not release-visible to the candidate until every response — auto and manual — is marked and the test is explicitly published, consistent with `ACA-05`'s publication discipline. |
| `BR-ACA-09-009` | Item analysis (`difficulty_index`, `discrimination_index`) computes after a test closes, from actual candidate performance, and updates the question bank so future assembly can weight toward well-discriminating items. |
| `BR-ACA-09-010` | A test linked to an `ACA-05` `assessment_type_id` transfers final marks through the same mark-entry path `ACA-08` uses — one sync mechanism for both LMS assignments and CBT tests feeding the gradebook, not two. |
| `BR-ACA-09-011` | A test in progress cannot be edited (added or removed questions) without invalidating attempts already in flight; editing after `opens_at` requires explicit confirmation naming the affected candidates. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Question bank | `Academic\Cbt\Bank` | `cbt.bank.manage` — tagging, difficulty, usage stats |
| **Test builder** | `Academic\Cbt\Builder` | `cbt.test.manage` — manual or rule-based assembly, live preview |
| **Candidate delivery** | `Academic\Cbt\Delivery` | learner — the actual test-taking screen; timer, autosave indicator, flag-for-review |
| Monitoring | `Academic\Cbt\Monitor` | `cbt.test.monitor` — live progress, focus-event flags, in-progress count |
| Manual marking | `Academic\Cbt\ManualMarking` | `cbt.mark` — written responses queued |
| Item analysis | `Academic\Cbt\ItemAnalysis` | `cbt.bank.manage` — difficulty/discrimination per question |

```
GET  /api/v1/cbt/my-tests                  learner
POST /api/v1/cbt/attempts/{ulid}/start
GET  /api/v1/cbt/attempts/{ulid}           resume state
POST /api/v1/cbt/attempts/{ulid}/responses    Idempotency-Key; autosave
POST /api/v1/cbt/attempts/{ulid}/submit
GET  /api/v1/cbt/attempts/{ulid}/remaining-time    server-authoritative
```

| Setting | Type | Default |
|---|---|---|
| `cbt.autosave_interval_seconds` | int | `15` |
| `cbt.default_max_tab_switches` | int | `3` |
| `cbt.auto_submit_on_time_expiry` | bool | `true` (**locked**) |

### 6. Acceptance criteria

```gherkin
AC-ACA-09-001
  Given a candidate loses power mid-test after answering 12 of 20 questions
  When they resume on reconnection
  Then all 12 answers are intact
  And the remaining time reflects only the time actually elapsed

AC-ACA-09-002
  Given a candidate's device clock is set incorrectly
  Then the displayed and enforced remaining time is computed server-side
  And is unaffected by the device clock

AC-ACA-09-003
  Given a candidate has an approved 25% extra time arrangement in ACA-07
  Then their CBT duration extends accordingly without separate configuration

AC-ACA-09-004
  Given a candidate resumes an interrupted attempt
  Then their question and option order is identical to their original attempt

AC-ACA-09-005
  Given a candidate switches tabs 5 times against a limit of 3
  Then the attempt is flagged for review
  And is not automatically disqualified or zero-scored

AC-ACA-09-006
  Given a test's time limit is reached
  Then it auto-submits with whatever was saved
  And no response entered before expiry is lost
```

---

# ACA-10 · Library & Textbook Management

### 1. Scope

**In scope.** Catalogue, barcode/QR labelling, circulation, bulk class-level textbook issue and return, loan limits, overdue tracking, fine and lost-book charging into the fee account, stock-take, acquisition requests, digital resource links.

**Out of scope.** Purchasing mechanics (`FIN-08`, which acquisition requests route through). Fee charging mechanics (`FIN-02`, which fines post through as ad hoc charges).

### 2. Data model

```sql
library_items
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
isbn                     VARCHAR(20)  NULL
title                       VARCHAR(300) NOT NULL
author                        VARCHAR(200) NULL
publisher                       VARCHAR(150) NULL
edition                            VARCHAR(40)  NULL
classification                        VARCHAR(30)  NULL   -- Dewey or local scheme
item_category                            VARCHAR(20)  NOT NULL   -- textbook|
                                                                  -- reference|
                                                                  -- fiction|
                                                                  -- non_fiction|
                                                                  -- periodical
subject_id                                  BIGINT       NULL FK   -- ACA-01,
                                                                    -- for
                                                                    -- textbooks
grade_level_id                                 BIGINT       NULL FK
replacement_cost_minor                            BIGINT       NULL
currency                                             CHAR(3)      NULL
cover_image_file_id                                     BIGINT       NULL FK
digital_resource_url                                       VARCHAR(500) NULL
                                                                        -- links
                                                                        -- into
                                                                        -- ACA-08
is_active                                                     TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, isbn)   -- where isbn not null
  INDEX  (school_id, subject_id, grade_level_id)
  FULLTEXT (title, author)

library_copies
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
item_id                    BIGINT       FK INDEX
accession_number               VARCHAR(30)  NOT NULL   -- gapless, CORE-06
barcode                           VARCHAR(60)  NULL
condition                            VARCHAR(20)  NOT NULL   -- new|good|fair|
                                                              -- poor|
                                                              -- withdrawn
acquired_on                             DATE         NULL
purchase_order_id                          BIGINT       NULL FK   -- FIN-08
status                                        VARCHAR(20)  NOT NULL   -- available|
                                                                      -- on_loan|
                                                                      -- reserved|
                                                                      -- lost|
                                                                      -- withdrawn
  UNIQUE (school_id, accession_number)
  INDEX  (school_id, item_id, status)

borrower_categories                   -- loan policy by role
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
category                  VARCHAR(20)  NOT NULL   -- primary|secondary|staff
max_concurrent_loans          SMALLINT     NOT NULL
loan_period_days                 SMALLINT     NOT NULL
max_renewals                        SMALLINT     NOT NULL DEFAULT 1
  UNIQUE (school_id, category)

loans
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
copy_id                    BIGINT       FK INDEX
borrower_type                 VARCHAR(20)  NOT NULL   -- student|staff
borrower_id                      BIGINT       NOT NULL
issued_on                           DATE         NOT NULL
due_on                                  DATE         NOT NULL
renewal_count                              SMALLINT     NOT NULL DEFAULT 0
returned_on                                   DATE         NULL
condition_at_return                              VARCHAR(20)  NULL
status                                               VARCHAR(20)  NOT NULL   -- active|
                                                                              -- returned|
                                                                              -- overdue|
                                                                              -- lost|
                                                                              -- fined
fine_charge_id                                          BIGINT       NULL FK
  INDEX (school_id, borrower_type, borrower_id, status)
  INDEX (school_id, due_on, status)

bulk_textbook_issues                  -- ⭐ the highest-volume library operation
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
class_id                    BIGINT       FK   -- ACA-02 school_classes
issue_type                     VARCHAR(20)  NOT NULL   -- term_start_issue|
                                                        -- term_end_return
item_ids                          JSON         NOT NULL   -- the standard set
                                                           -- for this class
total_learners                       SMALLINT     NOT NULL
completed_count                         SMALLINT     NOT NULL DEFAULT 0
exception_count                            SMALLINT     NOT NULL DEFAULT 0
                                                                 -- absent,
                                                                 -- item
                                                                 -- damaged,
                                                                 -- not
                                                                 -- returned
status                                                     VARCHAR(20)  NOT NULL   -- in_progress|
                                                                                    -- completed
  INDEX (school_id, class_id, term_id)

acquisition_requests
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
requested_title             VARCHAR(300) NOT NULL
requested_by                   BIGINT       FK → users.id
copies_requested                   SMALLINT     NOT NULL DEFAULT 1
estimated_cost_minor                  BIGINT       NULL
purchase_requisition_id                  BIGINT       NULL FK   -- FIN-08
status                                       VARCHAR(20)  NOT NULL   -- requested|
                                                                     -- ordered|
                                                                     -- received|
                                                                     -- rejected

stock_takes_library
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
conducted_on                DATE         NOT NULL
expected_count                  INT          NOT NULL
scanned_count                      INT          NOT NULL DEFAULT 0
missing_count                         INT          NOT NULL DEFAULT 0
status                                    VARCHAR(20)  NOT NULL   -- in_progress|
                                                                  -- completed
```

### 3. ⭐ Bulk issue — the operation that actually matters at scale

A single learner borrowing a novel is the textbook-model library operation everyone designs for first. In a Zimbabwean school, the operation that consumes an entire week at the start and end of every term is **issuing the full textbook set to 1,400 learners at once and getting it all back**.

```
Term start: bulk_textbook_issues for "Form 3 Blue"
   item_ids = [Maths F3, Combined Science F3, English F3, ...]  (the standard set)

FOR EACH learner in the class:
   FOR EACH item in the set:
      find an available copy → create a loan → mark copy on_loan
   exceptions (no available copy, learner absent) recorded, not silently skipped

Progress bar: "38 of 40 learners issued, 2 exceptions"
   → librarian resolves exceptions individually, the bulk operation
     does not block on them

Term end: bulk_textbook_issues, issue_type = term_end_return
   Scan each learner's returned set against what they were issued
   Missing or damaged → ad hoc charge raised automatically, same
   pattern as BRD-05's linen clearance
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-10-001` | Accession numbers are gapless per school, allocated through `CORE-06`. |
| `BR-ACA-10-002` | Loan limits and periods are configured per borrower category, checked at issue — a learner at their concurrent-loan limit cannot check out another item until one is returned or the limit is raised for that case with a reason. |
| `BR-ACA-10-003` | Renewal is permitted up to `max_renewals` and only where no reservation is waiting on that title. |
| `BR-ACA-10-004` | Overdue items generate reminders through `CORE-09` on a configured schedule, escalating in tone, before any fine is charged. |
| `BR-ACA-10-005` ⭐ | A fine or lost-book charge posts as an ad hoc charge through `FIN-02` directly to the learner's fee account, exactly as `BRD-05`'s linen loss does — the same charge mechanism, not a separate library billing path. |
| `BR-ACA-10-006` | A lost-book charge is the item's `replacement_cost_minor`; a returned-late fine is the school's configured daily rate, capped at the replacement cost. |
| `BR-ACA-10-007` ⭐ | Bulk issue and bulk return process the whole class as one operation, record exceptions individually without blocking the rest of the batch, and produce a completion report naming every unresolved case. |
| `BR-ACA-10-008` | Term-end unreturned bulk-issued items block that learner's own general clearance, feeding the same clearance gate `BRD-05`'s linen return contributes to for transfer-out. |
| `BR-ACA-10-009` | Stock-take reconciles scanned copies against the catalogue; items unscanned beyond a second confirmatory pass are marked lost and, where currently on an active loan, charge that borrower. |
| `BR-ACA-10-010` | Acquisition requests route into `FIN-08`'s ordinary procurement pipeline once approved — this module does not run a parallel purchasing process. |
| `BR-ACA-10-011` | A digital resource link on a catalogue item is simply a pointer into `ACA-08`'s content repository or an external URL; this module does not host digital content itself. |

### 5. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Catalogue | `Academic\Library\Catalogue` | `library.view` — search, availability |
| Circulation desk | `Academic\Library\Circulation` | `library.circulate` — scan-based issue/return/renew |
| **Bulk textbook issue** | `Academic\Library\BulkIssue` | `library.bulk_issue` ⭐ — by class, progress bar, exception queue |
| Overdue report | `Academic\Library\Overdue` | `library.view` |
| Stock-take | `Academic\Library\StockTake` | `library.stocktake` — scan-based |
| Acquisitions | `Academic\Library\Acquisitions` | `library.acquisition.request` |

```
GET  /api/v1/library/catalogue             ?search=&subject=
GET  /api/v1/library/my-loans               learner and staff
POST /api/v1/library/loans/{ulid}/renew
```

### 6. Acceptance criteria

```gherkin
AC-ACA-10-001
  Given Form 3 Blue's bulk textbook issue processes 40 learners
  And 2 have no available copy of Combined Science
  Then 38 complete successfully
  And the 2 exceptions are listed individually without blocking the batch

AC-ACA-10-002
  Given a learner does not return a bulk-issued textbook at term end
  Then a replacement charge posts to their fee account through FIN-02
  And their general clearance is blocked alongside any outstanding linen

AC-ACA-10-003
  Given a learner is at their concurrent loan limit
  When they attempt to borrow another item
  Then it is refused until a held item is returned

AC-ACA-10-004
  Given a stock-take finds a copy unscanned after a confirmatory second pass
  And it was on an active loan
  Then it is marked lost
  And the borrower is charged
```

---

# ACA-11 · Teaching Quality, Lesson Planning & Supervision

### 1. Scope

**In scope.** Scheme of work submission and HOD approval, lesson plan submission and review, syllabus coverage tracking, lesson observation with configurable rubrics, departmental meeting minutes, the teacher performance dashboard.

**Out of scope.** Marking of learner work (`ACA-05`, `ACA-08`). Staff appraisal cycles generally (`PPL-04`, which this module's observation and coverage data feeds into as one input among several).

### 2. Data model

```sql
schemes_of_work
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id           BIGINT       FK
term_id                       BIGINT       FK
subject_id                       BIGINT       FK
grade_level_id                      BIGINT       FK
teacher_staff_id                       BIGINT       FK
planned_topics                            JSON         NOT NULL   -- [{week,
                                                                   -- topic,
                                                                   -- objectives,
                                                                   -- resources}]
document_file_id                                 BIGINT       NULL FK
status                                               VARCHAR(20)  NOT NULL   -- draft|
                                                                              -- submitted|
                                                                              -- approved|
                                                                              -- returned
reviewed_by                                             BIGINT       NULL FK
review_comments                                            TEXT         NULL
  UNIQUE (school_id, term_id, subject_id, grade_level_id, teacher_staff_id)

lesson_plans
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
scheme_of_work_id           BIGINT       NULL FK
timetable_slot_id              BIGINT       NULL FK   -- ACA-03, if linked
teacher_staff_id                  BIGINT       FK
lesson_date                          DATE         NOT NULL
topic                                   VARCHAR(200) NOT NULL
objectives                                 TEXT         NULL
activities                                    TEXT         NULL
resources_needed                                 VARCHAR(500) NULL
differentiation_notes                               TEXT         NULL
status                                                 VARCHAR(20)  NOT NULL   -- draft|
                                                                                -- submitted|
                                                                                -- reviewed
hod_comments                                                TEXT         NULL
  INDEX (school_id, teacher_staff_id, lesson_date)

syllabus_coverage_records
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
scheme_of_work_id           BIGINT       FK INDEX
planned_topic_index             SMALLINT     NOT NULL
actual_delivered_on                DATE         NULL
variance_note                         VARCHAR(255) NULL   -- 'delayed —
                                                           -- public
                                                           -- holiday'
  UNIQUE (scheme_of_work_id, planned_topic_index)

observation_rubrics
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
name                     VARCHAR(150) NOT NULL
criteria                    JSON         NOT NULL   -- [{criterion,
                                                     -- descriptor_levels}]
                                                     -- same shape as
                                                     -- ACA-06's
                                                     -- project_rubrics

lesson_observations
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
observed_staff_id           BIGINT       FK INDEX
observer_staff_id              BIGINT       FK
rubric_id                         BIGINT       FK
observed_at                          TIMESTAMP    NOT NULL
class_observed                          VARCHAR(80)  NULL
subject_id                                 BIGINT       NULL FK
scores                                        JSON         NOT NULL   -- {criterion:
                                                                       -- level}
strengths_noted                                  TEXT         NULL
areas_for_development                               TEXT         NULL
overall_rating                                         VARCHAR(30)  NULL
teacher_acknowledged                                      TINYINT(1)   NOT NULL DEFAULT 0
teacher_comments                                             TEXT         NULL
follow_up_observation_id                                        BIGINT       NULL FK
  INDEX (school_id, observed_staff_id, observed_at)

department_meetings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
department_id               BIGINT       FK   -- PPL-04
meeting_date                    DATE         NOT NULL
attendee_staff_ids                  JSON         NOT NULL
agenda                                 TEXT         NULL
minutes                                   TEXT         NOT NULL
action_items                                 JSON         NULL   -- [{action,
                                                                  -- owner,
                                                                  -- due_date,
                                                                  -- status}]
chaired_by                                      BIGINT       FK
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-ACA-11-001` | A scheme of work is submitted per subject, per grade level, per teacher, per term, and requires HOD approval before a linked lesson plan can be marked `submitted`. |
| `BR-ACA-11-002` | Coverage tracking compares `syllabus_coverage_records.actual_delivered_on` against the scheme's planned week, producing a live on-track/behind indicator per teacher per subject — never a judgement, just a fact for the HOD to act on. |
| `BR-ACA-11-003` | A lesson plan may link to an `ACA-03` timetable slot, in which case its date is validated against that slot's actual scheduled occurrence rather than freely entered. |
| `BR-ACA-11-004` | Observation rubrics use the identical criterion/level structure as `ACA-06`'s project rubrics — one rubric shape, two applications, not two data models. |
| `BR-ACA-11-005` | An observed teacher may add comments to their own observation record but cannot edit the observer's scores; the record is the observer's professional judgement, commentable, not co-authored. |
| `BR-ACA-11-006` | A follow-up observation links to the one it follows, so a development trajectory is visible across a term rather than each observation standing alone. |
| `BR-ACA-11-007` | Observation records, coverage tracking, and department meeting minutes feed `PPL-04`'s appraisal cycle as inputs; this module does not conduct the appraisal itself. |
| `BR-ACA-11-008` | Department meeting action items carry an owner and due date and are trackable independently, so a meeting produces accountability, not just an archived transcript. |
| `BR-ACA-11-009` | The teacher performance dashboard combines coverage percentage, lesson plan submission timeliness, observation ratings, and — where enabled — `ACA-04` marking-compliance and `ACA-05` results outcomes, reading each from its owning module. |

### 4. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Scheme of work | `Academic\Supervision\SchemeOfWork` | teacher: submit; HOD: `supervision.scheme.approve` |
| Lesson plans | `Academic\Supervision\LessonPlans` | teacher: submit; HOD: review |
| Coverage tracker | `Academic\Supervision\Coverage` | `supervision.view` — traffic-light per teacher per subject |
| **Observation** | `Academic\Supervision\Observe` | `supervision.observe` — rubric entry, mobile-friendly for in-classroom use |
| Observation history | `Academic\Supervision\ObservationHistory` | own record; HOD/head: department view |
| Department meetings | `Academic\Supervision\Meetings` | `supervision.meeting.manage` |
| Teacher dashboard | `Academic\Supervision\TeacherDashboard` | own; HOD: department-scoped |

```
GET  /api/v1/supervision/my-coverage        teacher
POST /api/v1/supervision/schemes            submit
GET  /api/v1/supervision/my-observations    teacher: own history
```

### 5. Acceptance criteria

```gherkin
AC-ACA-11-001
  Given a scheme of work is not yet HOD-approved
  When the teacher attempts to submit a linked lesson plan
  Then submission is blocked until the scheme is approved

AC-ACA-11-002
  Given a scheme plans Topic 4 for week 5 and it was actually delivered
       in week 7
  Then the coverage tracker shows behind-schedule for that topic
  With the variance visible to the HOD

AC-ACA-11-003
  Given an observer scores a lesson observation
  Then the observed teacher can add comments
  But cannot alter the observer's scores

AC-ACA-11-004
  Given a follow-up observation is recorded
  Then it links to the original
  And the teacher's history shows the development trajectory across both

AC-ACA-11-005
  Given a department meeting records an action item with an owner and
       due date
  Then it is independently trackable and does not require reopening the
       full minutes to check its status
```

---

## Part 3 — Book K Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **K1** | `FIN-07` schemes, budget envelopes, `DiscountResolver` ⭐ | **`FIN-02` retro-fitted; `AC-FIN-07-001` to `-003` green** |
| **K2** | `FIN-07` applications, committee review, sponsor awards | Sponsor path invoices the sponsor, never writes off income |
| **K3** | `ACA-08` course spaces, content, data-cost-aware delivery | Large files stream rather than auto-cache |
| **K4** | `ACA-08` assignments, submission, late policy, similarity | Gradebook sync through `ACA-05`'s own mark-entry path |
| **K5** | `ACA-09` question bank, test assembly, randomisation | Seeded order survives resumption |
| **K6** | `ACA-09` delivery, autosave, server-authoritative timer ⭐ | **`AC-ACA-09-001/002` green under a simulated power loss** |
| **K7** | `ACA-10` catalogue, circulation, loan policy | |
| **K8** | `ACA-10` bulk issue/return ⭐ | **`AC-ACA-10-001` green at class scale; exceptions never block the batch** |
| **K9** | `ACA-11` schemes of work, lesson plans, coverage | |
| **K10** | `ACA-11` observation, department meetings, teacher dashboard | Observer/observed edit boundary enforced |
| **K11** | `PPL-06` alumni creation on graduation, frozen snapshot | Snapshot immune to later grading-scale changes |
| **K12** | `PPL-06` events, campaigns, pledges, endowments | Endowment shortfall triggers `FIN-07`'s existing refusal, unchanged |

`ACA-08`, `ACA-09`, `ACA-10`, `ACA-11` have no dependency on each other and should run in parallel across four developers once `FIN-07` (K1–K2) is done.

---

## Part 4 — Book K Acceptance Gate

### The `FIN-02` retro-fit

- [ ] `FIN-02`'s billing pipeline resolves every discount through `DiscountResolver` and no other path
- [ ] Sibling discounts re-evaluate live against current household composition, not a one-time grant
- [ ] A budget-capped scheme refuses an over-limit award rather than silently over-committing
- [ ] A sponsor-funded award invoices the sponsor and never touches the discount contra account
- [ ] The cost-of-generosity report shows gross, discount, and net separately for every scheme

### Resilience under real conditions

- [ ] A CBT attempt survives a simulated power loss with zero answer loss and a correct server-computed remaining time
- [ ] Bulk textbook issue for a full class processes with individual, non-blocking exception handling
- [ ] Large LMS content defaults to stream-on-request, never automatic offline hoarding

### Historical integrity

- [ ] An alumni academic snapshot is immune to a later change in grading scale or template
- [ ] A graduated learner's original `PPL-01` record becomes read-only per the existing exit-status pattern

### Boundary discipline

- [ ] `ACA-08` and `ACA-09` both write gradebook results through `ACA-05`'s existing mark-entry path — no second gradebook table exists
- [ ] `ACA-10` fines post through `FIN-02`'s existing ad hoc charge mechanism, matching `BRD-05`'s pattern exactly
- [ ] `ACA-11`'s observation rubric reuses `ACA-06`'s rubric shape rather than a parallel structure
- [ ] `PPL-06` alumni events and campaigns build on `COM-06`'s calendar infrastructure without a parallel one

### Quality

- [ ] Coverage ≥ 90% for `FIN-07` (it retro-fits a financial module) and `ACA-09`'s autosave/resume logic
- [ ] Tenancy isolation suite passes for every model in this book
- [ ] Every business rule has a named test referencing its rule ID

---

## Appendix A — The Complete 78-Module Catalogue

This table is the final accounting. Every module from Volume 1's Part 6 is listed against the book that specifies it.

| Domain | Modules | Book(s) |
|---|---|---|
| A · Platform & Foundation | `CORE-01`–`CORE-13` (13) | A |
| B · People & Organisation | `PPL-01`–`PPL-04` (4), `PPL-05` (1), `PPL-06` (1) | C, H3, **K** |
| C · Academic | `ACA-01`, `ACA-02`, `ACA-04`, `ACA-05` (4); `ACA-03`, `ACA-06`, `ACA-07` (3); `ACA-08`–`ACA-11` (4) | D, E, **K** |
| D · Finance & Accounting | `FIN-01`–`FIN-06` (6); `FIN-08`–`FIN-11` (4); `FIN-12`–`FIN-14` (3); `FIN-07` (1) | B, H1, H3, **K** |
| E · Boarding & Welfare | `BRD-01`–`BRD-08` (8) | F, G |
| F · Operations & Estates | `OPS-01`–`OPS-07` (7) | H2 |
| G · Communication & Engagement | `COM-01`–`COM-08` (8) | I |
| H · Compliance & Statutory | `CMP-01`–`CMP-04` (4) | H3 |
| I · Intelligence & Integration | `INT-01`–`INT-04` (4) | J |
| J · Commercial / SaaS Control | `SAA-01`–`SAA-03` (3) | J |
| **Total** | **78 modules** | **11 books, Volumes 1 & 2** |

Every module is accounted for. No module in Volume 1's Part 6 catalogue is unspecified.

---

## Appendix B — What Building From This Specification Now Looks Like

Eleven books, roughly 1,000 business rules, and several hundred acceptance criteria later, the specification is complete. Three things are worth saying plainly before anyone opens an IDE.

**This is a map, not the territory.** Volume 2 tells a developer exactly what to build. It does not replace the judgement they will need when a real school's data does something the specification did not anticipate — a guardian relationship stranger than any example here, a fee arrangement no rule quite covers. When that happens, the right response is the one this document has modelled throughout: find the closest existing pattern, extend it consistently, and update the specification rather than quietly diverging from it in code.

**The acceptance gates are not decoration.** Every book ended with one for a reason: Domain B does not start until Domain A's gate is green, and Book K does not get to call `FIN-07` done until `AC-FIN-07-001` through `007` actually pass against real data. A team that skips a gate to hit a date is borrowing against a debt this specification worked hard to make visible early, in Volume 1 Part 7's Financial Integrity Charter and in every hard-block, append-only table, and confirmation banner since.

**The two research corrections earlier in this project are the model for everything after handover.** Zimbabwean statutory and curriculum figures will keep changing after this document is finished — that is what `requires_confirmation` flags and effective-dated configuration tables are *for*. The discipline that resolved the NSSA rate and the A-Level subject count with real sources, rather than picking a plausible number and moving on, is the same discipline the system asks of the schools that will run it every year they file a return or open a term.

Build in the order the books were written. Test against the acceptance criteria as written, not as remembered. And where this specification is silent, be as careful as it tried to be.

---

*End of Volume 2. End of the specification.*

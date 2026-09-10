# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book H3 — Payroll, Fiscalisation & Compliance (`PPL-05`, `FIN-12`–`FIN-14`, `CMP-01`–`CMP-04`)

| Field | Value |
|---|---|
| Document | Volume 2, Book H3 of 10 |
| Covers | Payroll & Statutory Deductions 🇿🇼 · Financial Reporting & Period Close · ZIMRA Fiscalisation 🇿🇼 · Student Wallet & Tuckshop · ZIMSEC, MoPSE, Data Protection, Policy Register |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A–D, F, G, H1, H2 complete.** `FIN-13` fiscalises receipts raised across the whole platform. |
| Next book | Book I — Communication & Portals (`COM-01` → `COM-08`) |

---

## Part 0 — Read This First

### 0.1 ⭐ Every statutory number in this book is configuration

This book encodes more external regulation than any other, and every rate, band, ceiling and deadline in it changes on somebody else's schedule.

**Design rule, without exception:** no tax rate, contribution percentage, earnings ceiling, filing deadline or return format appears as a constant anywhere in the codebase. All of it lives in versioned, effective-dated configuration tables that a school administrator or the vendor can update without a deployment.

A payroll module that hard-codes a PAYE band will be wrong within a year. One that stores bands as effective-dated data will still be running in 2035.

### 0.2 🇿🇼 What the research establishes, and what it does not

**Established and stable enough to seed:**

| Item | Position |
|---|---|
| PAYE | Progressive, administered by ZIMRA, **separate band tables for USD and ZiG earners**, top marginal rate 40% |
| AIDS Levy | **3% of the PAYE due**, not of gross pay |
| ZIMDEF | Manpower Development Levy, **1% of total payroll**, employer-borne |
| NSSA — POBS | Pension and Other Benefits Scheme: **9% of insurable earnings, split 4.5% employer / 4.5% employee**, capped at an **insurable earnings ceiling of USD 700/month** (or ZWG equivalent), reviewed periodically |
| NSSA — APWCS | Accident Prevention and Workers Compensation Scheme: **employer-only, uncapped**, rate set by industry risk classification (an office/low-risk employer typically sits in the low single digits of a percent; the exact figure is gazetted annually per industrial classification code) |
| NEC | Sector-specific Collective Bargaining Agreement dues; there is no single national minimum wage |
| Monthly filing | **PAYE (P2), NSSA, NEC, ZIMDEF and AIDS Levy are all due by the 10th of the following month** |
| Annual filing | **ITF16 PAYE reconciliation due 31 January**; ITF12C due 30 April with audited financial statements |
| Corporate QPDs | 25 March (10%), 25 June (25%), 25 September (30%), 20 December (35%), cumulative |
| Withholding | 10% on payments to suppliers without a valid ITF263 |

**What an initial pass reported inconsistently, and what a second pass resolves:**

The first research pass found the NSSA pension split reported two ways — 9% total (4.5%/4.5%) in some sources, 3.5% each (7% total) in one. A targeted second pass resolves this decisively: **NSSA's own published schedule states 4.5% employee plus 4.5% employer, 9% total**, and that figure is independently corroborated by every current Zimbabwean payroll and tax-advisory source found — none of them agrees with the 3.5% figure, which appears to be an outlier. The USD 700 monthly ceiling, introduced in 2024, is confirmed as still current through 2025 and into 2026 by multiple sources, with the explicit caveat from more than one of them that **NSSA reviews the rate and ceiling periodically and notifies employers in writing** — which is precisely why the architecture below stores it as effective-dated configuration rather than a constant, regardless of how settled today's figure is.

**The engineering response accordingly shifts from Book D's "genuinely contested, pick with caution" to "well-established, but reviewed on someone else's schedule."** The system seeds the corroborated POBS rate (9%, split 4.5/4.5, USD 700 ceiling) as the default. `requires_confirmation` remains set — not because the current figure is in doubt, but because NSSA revises it periodically without a fixed cycle, and a payroll module that silently carries a stale rate for a year is a worse failure than one that asks once a year for thirty seconds of confirmation. APWCS is seeded as employer-only and uncapped, with the actual percentage left blank by industrial classification, because that rate is genuinely school-specific (it depends on the school's own risk classification with NSSA) and cannot be defaulted at all.

> **⚠ Confirm statutory rates before your first payroll run.** NSSA's pension rate (9%, split 4.5/4.5) and its USD 700 ceiling are well-corroborated defaults, current as of the most recent sources checked — but NSSA reviews both periodically. Confirm you're on the current figures, and enter your school's own APWCS industrial classification rate, before running payroll for the first time each year.

That is more useful to a Zimbabwean bursar than either a confident number that quietly goes stale, or a system that refuses to seed anything at all. The banner now asks for an annual thirty-second check, not a research project.

### 0.3 Build order

```
PPL-05  Payroll & Statutory          ← largest; independent of the rest
FIN-13  ZIMRA Fiscalisation          ← ⭐ retro-fits every commercial receipt
FIN-14  Student Wallet & Tuckshop    ← consumes FIN-13 and FIN-09
FIN-12  Reporting & Period Close     ← needs everything else posting correctly
CMP-01  ZIMSEC                       ← closes the Book E interface
CMP-02  MoPSE / EMIS
CMP-03  Data Protection              ← retro-fits retention across all modules
CMP-04  Policy & Document Register
```

`FIN-13` and `CMP-03` both **retro-fit across earlier modules**. Budget for that: fiscalisation touches every receipt path in `FIN-04`, `FIN-14`, `OPS-03` and `OPS-05`; data protection attaches retention schedules to every table holding personal data.

---

# PPL-05 · Payroll & Statutory Deductions 🇿🇼

### 1. Scope

Pay grades and scales, earnings and deductions catalogue, multi-currency salary splits, the statutory calculation engine, loans and third-party deductions, payroll run with preview and approval, payslips, bank payment files, statutory return preparation, full GL posting.

**Out of scope.** Staff records and contracts (`PPL-04`). Leave (`PPL-04`, consumed here for unpaid leave). Filing submission itself — the system prepares returns; a human files them.

### 2. Data model

```sql
statutory_configurations              -- ⭐ effective-dated, versioned
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       NULL FK    -- null = system default
config_type             VARCHAR(40)  NOT NULL   -- paye_bands|aids_levy|
                                                -- nssa_pension|nssa_apwcs|
                                                -- zimdef|nec_dues|
                                                -- withholding|credits
currency                CHAR(3)      NULL       -- ⭐ PAYE differs by currency
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
configuration           JSON         NOT NULL   -- bands, rates, ceilings
source_reference        VARCHAR(200) NULL       -- 'Finance Act 2026'
requires_confirmation   TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐
confirmed_by            BIGINT       NULL FK
confirmed_at            TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL   -- draft|active|superseded
created_by, created_at
  INDEX (school_id, config_type, currency, effective_from)
  INDEX (school_id, requires_confirmation, status)

pay_grades
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
category                VARCHAR(30)  NOT NULL   -- teaching|administration|
                                                -- support|management|ancillary
min_salary_minor        BIGINT       NULL
max_salary_minor        BIGINT       NULL
currency                CHAR(3)      NOT NULL
nec_grade_reference     VARCHAR(40)  NULL       -- 🇿🇼 CBA alignment
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

pay_grade_notches
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
grade_id                BIGINT       FK INDEX
notch                   VARCHAR(20)  NOT NULL
basic_salary_minor      BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
effective_from          DATE         NOT NULL
  UNIQUE (grade_id, notch, effective_from)

pay_components
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL   -- 'BASIC','HOUSING','TRANSPORT'
name                    VARCHAR(120) NOT NULL
component_type          VARCHAR(20)  NOT NULL   -- earning|deduction|
                                                -- employer_contribution
category                VARCHAR(30)  NOT NULL   -- basic|allowance|overtime|
                                                -- bonus|statutory|loan|
                                                -- third_party|benefit_in_kind
calculation_method      VARCHAR(30)  NOT NULL   -- fixed|percentage_of_basic|
                                                -- percentage_of_gross|
                                                -- formula|hourly|per_unit
default_amount_minor    BIGINT       NULL
default_percent         DECIMAL(6,3) NULL
formula                 VARCHAR(500) NULL
currency                CHAR(3)      NULL       -- null = staff's own currency
-- ⭐ tax treatment
is_taxable              TINYINT(1)   NOT NULL DEFAULT 1
is_pensionable          TINYINT(1)   NOT NULL DEFAULT 1   -- NSSA base
is_nec_applicable       TINYINT(1)   NOT NULL DEFAULT 1
is_zimdef_applicable    TINYINT(1)   NOT NULL DEFAULT 1
taxable_percent         DECIMAL(5,2) NOT NULL DEFAULT 100  -- partial taxation
-- accounting
expense_account_id      BIGINT       NULL FK
liability_account_id    BIGINT       NULL FK
cost_centre_source      VARCHAR(20)  NOT NULL DEFAULT 'staff'  -- staff|fixed
appears_on_payslip      TINYINT(1)   NOT NULL DEFAULT 1
sort_order              SMALLINT
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

staff_pay_structures                  -- per staff member, dated
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
staff_id                BIGINT       FK INDEX
contract_id             BIGINT       NULL FK
grade_id                BIGINT       NULL FK
notch                   VARCHAR(20)  NULL
-- ⭐ multi-currency split
primary_currency        CHAR(3)      NOT NULL
usd_portion_percent     DECIMAL(5,2) NULL       -- split-currency salaries
zwg_portion_percent     DECIMAL(5,2) NULL
payment_currency        CHAR(3)      NOT NULL
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
status                  VARCHAR(20)  NOT NULL   -- draft|active|superseded
approved_by             BIGINT       NULL FK
  INDEX (school_id, staff_id, effective_from)

staff_pay_components
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
pay_structure_id        BIGINT       FK INDEX
component_id            BIGINT       FK
amount_minor            BIGINT       NULL
percent                 DECIMAL(6,3) NULL
currency                CHAR(3)      NOT NULL
quantity                DECIMAL(10,2) NULL      -- hours, units
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
notes                   VARCHAR(255) NULL

staff_loans
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
staff_id                BIGINT       FK INDEX
loan_type               VARCHAR(30)  NOT NULL   -- salary_advance|
                                                -- staff_loan|
                                                -- fee_offset|equipment
principal_minor         BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
interest_rate_percent   DECIMAL(5,2) NOT NULL DEFAULT 0
instalment_minor        BIGINT       NOT NULL
instalment_count        SMALLINT     NOT NULL
starts_on               DATE         NOT NULL
outstanding_minor       BIGINT       NOT NULL
paid_minor              BIGINT       NOT NULL DEFAULT 0
-- ⭐ staff-child fee offset
offset_student_ids      JSON         NULL
approval_request_id     BIGINT       NULL FK
approved_by             BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- pending|active|
                                                -- completed|written_off|
                                                -- suspended
  INDEX (school_id, staff_id, status)

payroll_runs
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
period_month            CHAR(7)      NOT NULL   -- '2026-09'
run_number              VARCHAR(40)  NOT NULL
run_type                VARCHAR(20)  NOT NULL   -- regular|supplementary|
                                                -- bonus|terminal
pay_date                DATE         NOT NULL
period_start            DATE         NOT NULL
period_end              DATE         NOT NULL
staff_count             SMALLINT     NOT NULL DEFAULT 0
gross_minor             BIGINT       NOT NULL DEFAULT 0
deductions_minor        BIGINT       NOT NULL DEFAULT 0
net_minor               BIGINT       NOT NULL DEFAULT 0
employer_cost_minor     BIGINT       NOT NULL DEFAULT 0
currency_totals         JSON         NULL       -- per currency
-- 🇿🇼 statutory totals
paye_minor              BIGINT       NOT NULL DEFAULT 0
aids_levy_minor         BIGINT       NOT NULL DEFAULT 0
nssa_employee_minor     BIGINT       NOT NULL DEFAULT 0
nssa_employer_minor     BIGINT       NOT NULL DEFAULT 0
apwcs_minor             BIGINT       NOT NULL DEFAULT 0
zimdef_minor            BIGINT       NOT NULL DEFAULT 0
nec_employee_minor      BIGINT       NOT NULL DEFAULT 0
nec_employer_minor      BIGINT       NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL   -- draft|computing|preview|
                                                -- approved|posted|paid|
                                                -- reversed|cancelled
variance_report         JSON         NULL       -- ⭐ vs prior month
exception_report        JSON         NULL
approval_request_id     BIGINT       NULL FK
computed_by             BIGINT       FK → users.id
approved_by             BIGINT       NULL FK
journal_id              BIGINT       NULL FK
bank_file_id            BIGINT       NULL FK
  UNIQUE (school_id, period_month, run_type, run_number)
  INDEX  (school_id, status, pay_date)

payslips
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
payroll_run_id          BIGINT       FK INDEX
staff_id                BIGINT       FK INDEX
payslip_number          VARCHAR(40)  NOT NULL
-- earnings
basic_minor             BIGINT       NOT NULL
allowances_minor        BIGINT       NOT NULL DEFAULT 0
overtime_minor          BIGINT       NOT NULL DEFAULT 0
bonus_minor             BIGINT       NOT NULL DEFAULT 0
gross_minor             BIGINT       NOT NULL
taxable_gross_minor     BIGINT       NOT NULL
pensionable_gross_minor BIGINT       NOT NULL
-- 🇿🇼 statutory deductions
paye_minor              BIGINT       NOT NULL DEFAULT 0
aids_levy_minor         BIGINT       NOT NULL DEFAULT 0
nssa_employee_minor     BIGINT       NOT NULL DEFAULT 0
nec_employee_minor      BIGINT       NOT NULL DEFAULT 0
-- other deductions
loan_deduction_minor    BIGINT       NOT NULL DEFAULT 0
third_party_minor       BIGINT       NOT NULL DEFAULT 0
fee_offset_minor        BIGINT       NOT NULL DEFAULT 0
other_deductions_minor  BIGINT       NOT NULL DEFAULT 0
total_deductions_minor  BIGINT       NOT NULL
net_pay_minor           BIGINT       NOT NULL
-- employer contributions (cost, not deduction)
nssa_employer_minor     BIGINT       NOT NULL DEFAULT 0
apwcs_minor             BIGINT       NOT NULL DEFAULT 0
zimdef_minor            BIGINT       NOT NULL DEFAULT 0
nec_employer_minor      BIGINT       NOT NULL DEFAULT 0
employer_cost_minor     BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
-- multi-currency
usd_net_minor           BIGINT       NULL
zwg_net_minor           BIGINT       NULL
exchange_rate_id        BIGINT       NULL FK
-- year to date
ytd_gross_minor         BIGINT       NOT NULL
ytd_paye_minor          BIGINT       NOT NULL
ytd_nssa_minor          BIGINT       NOT NULL
days_worked             DECIMAL(5,2) NULL
unpaid_leave_days       DECIMAL(5,2) NOT NULL DEFAULT 0
calculation_trace       JSON         NULL       -- ⭐ how PAYE was derived
document_id             BIGINT       NULL FK
distributed_at          TIMESTAMP    NULL
  UNIQUE (payroll_run_id, staff_id)
  INDEX  (school_id, staff_id, payroll_run_id)

payslip_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
payslip_id              BIGINT       FK INDEX
component_id            BIGINT       FK
component_type          VARCHAR(20)  NOT NULL
description             VARCHAR(150) NOT NULL
quantity                DECIMAL(10,2) NULL
rate_minor              BIGINT       NULL
amount_minor            BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
is_taxable              TINYINT(1)   NOT NULL
sort_order              SMALLINT

statutory_returns                     -- 🇿🇼 ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
return_type             VARCHAR(30)  NOT NULL   -- p2_paye|nssa_monthly|
                                                -- nec_monthly|zimdef|
                                                -- itf16_annual|itf12c|
                                                -- qpd|vat_return
period_type             VARCHAR(20)  NOT NULL   -- monthly|quarterly|annual
period_reference        VARCHAR(20)  NOT NULL   -- '2026-09','2026-Q3','2026'
due_date                DATE         NOT NULL   -- ⭐ computed from calendar
amount_due_minor        BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
supporting_data         JSON         NOT NULL   -- per-employee detail
export_file_id          BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- pending|prepared|
                                                -- reviewed|submitted|
                                                -- acknowledged|paid|
                                                -- overdue|disputed
prepared_by             BIGINT       NULL FK
prepared_at             TIMESTAMP    NULL
reviewed_by             BIGINT       NULL FK
submitted_at            TIMESTAMP    NULL
submission_reference    VARCHAR(80)  NULL
paid_at                 TIMESTAMP    NULL
payment_reference       VARCHAR(80)  NULL
journal_id              BIGINT       NULL FK
  UNIQUE (school_id, return_type, period_reference)
  INDEX  (school_id, due_date, status)
```

### 3. ⭐ The statutory calculation engine

```php
final class ComputePayslipAction extends Action
{
    public function execute(ComputePayslipData $d): Payslip
    {
        $trace = new CalculationTrace();

        // 1 — EARNINGS
        $components = $this->resolveComponents($d->staff, $d->periodStart, $d->periodEnd);
        $gross      = $components->earnings()->sum();
        $taxable    = $components->earnings()->taxablePortion();
        $pensionable= $components->earnings()->pensionablePortion();

        // Pro-rate for part-month, unpaid leave (PPL-04)
        $factor = $this->prorationFactor($d->staff, $d->periodStart, $d->periodEnd);
        if ($factor < 1.0) {
            $gross = $gross->multiplyBy((string) $factor);
            $trace->note("Pro-rated at {$factor} for unpaid leave / part month");
        }

        // 2 — 🇿🇼 NSSA PENSION (before PAYE; contribution is deductible)
        $nssaConfig  = $this->config('nssa_pension', $d->payDate, $d->currency);
        $insurable   = Money::min($pensionable, $nssaConfig->ceiling($d->currency));
        $nssaEmp     = $insurable->multiplyBy($nssaConfig->employeeRate);
        $nssaEmployer= $insurable->multiplyBy($nssaConfig->employerRate);
        $trace->nssa($pensionable, $nssaConfig->ceiling($d->currency), $insurable,
                     $nssaConfig->employeeRate, $nssaEmp);

        // 3 — 🇿🇼 PAYE, on the correct currency's band table
        $bands       = $this->config('paye_bands', $d->payDate, $d->currency);
        $payeBase    = $taxable->minus($nssaEmp)->minus($this->deductibleCredits($d->staff));
        $paye        = $bands->applyProgressive($payeBase, $trace);

        // 4 — 🇿🇼 AIDS LEVY: 3% OF THE PAYE, not of gross
        $aidsRate    = $this->config('aids_levy', $d->payDate)->rate;
        $aidsLevy    = $paye->multiplyBy($aidsRate);
        $trace->aidsLevy($paye, $aidsRate, $aidsLevy);

        // 5 — 🇿🇼 EMPLOYER-BORNE LEVIES
        $apwcs       = $this->config('nssa_apwcs', $d->payDate)->applyTo($insurable);
        $zimdef      = $this->config('zimdef', $d->payDate)->applyTo($components->zimdefBase());
        $trace->zimdef($components->zimdefBase(), $zimdef);

        // 6 — 🇿🇼 NEC, per the school's applicable CBA
        $nec         = $this->config('nec_dues', $d->payDate)->applyTo($components->necBase());

        // 7 — OTHER DEDUCTIONS
        $loans       = $this->loanDeductions($d->staff, $d->payDate);
        $feeOffset   = $this->staffChildFeeOffset($d->staff, $d->payDate);   // → FIN-02
        $thirdParty  = $components->thirdPartyDeductions();

        // 8 — NET
        $totalDeductions = $paye->plus($aidsLevy)->plus($nssaEmp)->plus($nec)
                                ->plus($loans)->plus($feeOffset)->plus($thirdParty);
        $net             = $gross->minus($totalDeductions);

        if ($net->isNegative()) {
            throw new NegativeNetPayException($d->staff, $gross, $totalDeductions);
        }

        return $this->buildPayslip($d, compact(/* … */), $trace);
    }
}
```

**Three things this ordering gets right.** The NSSA pension contribution reduces the PAYE base, so it must be computed first. The AIDS Levy is 3% *of the PAYE due*, not of gross — a distinction that costs a school money in both directions if reversed. And the PAYE band table is selected by the **staff member's earning currency**, because USD and ZiG earners face different tables.

**The `calculation_trace`** stores the full derivation on every payslip: which band table was used, each band applied, the NSSA ceiling and whether it bit, the AIDS Levy base. When a teacher queries their deduction, the bursar reads the answer off the screen.

### 4. ⭐ The payroll run

```
1  COMPUTE   — every active staff member; exceptions collected, not fatal
2  PREVIEW   — ⭐ nothing posted; per-staff figures with variance vs prior month
3  EXCEPTIONS — negative net pay, variance beyond threshold, missing bank
                details, expired contract, missing NSSA/BP number,
                unconfirmed statutory config
4  APPROVE   — CORE-07 chain; Head + Bursar minimum
5  POST      — one journal through FIN-01
6  DISTRIBUTE — payslips generated and released to staff self-service
7  PAY       — bank file exported; payment recorded
8  RETURNS   — statutory returns auto-prepared with due dates
```

**The GL posting:**

```
Dr  Salaries & Wages (by cost centre)      gross
Dr  Employer NSSA                         nssa_employer
Dr  Employer APWCS                        apwcs
Dr  ZIMDEF Levy                           zimdef
Dr  Employer NEC                          nec_employer
    Cr  Net Salaries Payable                          net
    Cr  PAYE Payable                                  paye
    Cr  AIDS Levy Payable                             aids_levy
    Cr  NSSA Payable                       nssa_employee + nssa_employer
    Cr  APWCS Payable                                 apwcs
    Cr  ZIMDEF Payable                                zimdef
    Cr  NEC Payable                        nec_employee + nec_employer
    Cr  Staff Loans Receivable                        loan_deductions
    Cr  Fee Debtors (staff children)                  fee_offset    ⭐
    Cr  Third Party Payables                          third_party
```

The `fee_offset` line is worth noting: a staff member's children's fees deducted at source credit the learner's fee account directly, so `FIN-03` shows the payment without a separate receipting step.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-PPL-05-001` ⭐ | No statutory rate, band, ceiling or deadline is hard-coded. All live in effective-dated `statutory_configurations`. |
| `BR-PPL-05-002` ⭐ | A payroll run is **blocked** while any applicable configuration has `requires_confirmation = 1` and is unconfirmed for the current period. |
| `BR-PPL-05-003` | Configuration is selected by pay date and by the staff member's earning currency. |
| `BR-PPL-05-004` | Superseding a configuration never alters historical payslips. A 2025 payslip recomputes identically forever. |
| `BR-PPL-05-005` | PAYE applies the progressive band table for the staff member's currency, on taxable gross less the NSSA employee contribution and any deductible credits. |
| `BR-PPL-05-006` ⭐ | AIDS Levy is computed as a percentage **of the PAYE due**, never of gross pay. |
| `BR-PPL-05-007` | NSSA POBS pension contributions apply to pensionable earnings capped at the insurable earnings ceiling for the period, split between employer and employee per the effective-dated configuration. |
| `BR-PPL-05-007B` ⭐ | NSSA APWCS is **employer-only** — no employee deduction exists for it — and is **uncapped**, calculated on the full insurable wage bill rather than against the POBS ceiling. Its rate is set per the school's own NSSA industrial classification and cannot carry a system-wide default; the school enters its gazetted rate directly. |
| `BR-PPL-05-008` | ZIMDEF applies to total payroll at the configured rate and is entirely employer-borne. |
| `BR-PPL-05-009` | NEC dues apply per the school's configured Collective Bargaining Agreement, with employer and employee portions tracked separately. |
| `BR-PPL-05-010` | Split-currency salaries compute statutory obligations on the combined value, converted at the rate effective on the pay date, and record the rate on the payslip. |
| `BR-PPL-05-011` | Unpaid leave from `PPL-04` pro-rates gross and every percentage-based component. |
| `BR-PPL-05-012` | Negative net pay is an exception, never a posting. The run continues; the staff member is reported. |
| `BR-PPL-05-013` ⭐ | **No payroll run posts without approval of the preview.** Compute, preview, approve and post are four distinct states. |
| `BR-PPL-05-014` | The variance report compares every staff member's gross and net against the prior month; deviation beyond the configured percentage appears on the exception report. |
| `BR-PPL-05-015` | Missing bank details, expired contract, missing BP or NSSA number, or a staff member marked `exited` before the pay date all raise exceptions. |
| `BR-PPL-05-016` | A posted run cannot be edited. Correction is a reversal plus a supplementary run, both retained. |
| `BR-PPL-05-017` | Payslips are encrypted, released only to the staff member and payroll staff, and every access is logged. |
| `BR-PPL-05-018` | Staff-child fee offsets credit the learner's fee account through `FIN-02`, visible on the guardian's statement. |
| `BR-PPL-05-019` | Loan deductions reduce the outstanding balance; a loan is never over-recovered. |
| `BR-PPL-05-020` ⭐ | Statutory returns are auto-prepared on posting, with due dates computed from the filing calendar: **P2, NSSA, NEC, ZIMDEF and AIDS Levy by the 10th of the following month; ITF16 by 31 January**. |
| `BR-PPL-05-021` | Returns approaching their due date alert the bursar at 7, 3 and 1 days. An overdue return alerts the head. |
| `BR-PPL-05-022` | The system prepares and exports returns. **It does not file them.** Submission is recorded manually with the reference. |
| `BR-PPL-05-023` | ITF16 aggregates every payslip for the tax year per employee, and is reconcilable to the twelve monthly P2 returns. A mismatch blocks preparation. |
| `BR-PPL-05-024` | Terminal pay computes leave encashment, notice pay and outstanding loan recovery, and requires clearance from `PPL-04` before release. |
| `BR-PPL-05-025` | Salary, banking and payslip data are restricted to `staff.view_compensation` holders and are absent from every unauthorised export. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| **Statutory configuration** | `Payroll\Statutory\Config` | `payroll.statutory.manage` ⚠⚠ 🇿🇼 — band editor, effective dates, **confirmation banner** |
| Pay grades | `Payroll\Grades\Index` | `payroll.manage` |
| Pay components | `Payroll\Components\Index` | `payroll.manage` — tax treatment flags |
| Staff pay structure | `Payroll\Staff\Structure` | `payroll.staff.manage` ⚠ — dated, currency split |
| Loans | `Payroll\Loans\Index` | `payroll.loan.manage` |
| **Payroll run** | `Payroll\Run\Wizard` | `payroll.run` ⭐ — compute → preview → exceptions → approve → post |
| **Preview** | `Payroll\Run\Preview` | `payroll.run` — per staff, variance column, exception tab, drill to trace |
| Payslip | `Payroll\Payslips\Show` | `payroll.view` — with the calculation trace visible to payroll staff |
| Bank file | `Payroll\Run\BankFile` | `payroll.pay` ⚠ |
| **Statutory returns** | `Payroll\Returns\Index` | `payroll.returns.manage` 🇿🇼 — due dates, prepare, export, record submission |
| ITF16 | `Payroll\Returns\Itf16` | `payroll.returns.manage` 🇿🇼 — annual reconciliation against P2s |
| Payroll reports | `Payroll\Reports\Index` | `payroll.report.view` — cost by cost centre, headcount, statutory summary |

### 7. API endpoints

```
GET  /api/v1/me/payslips                 staff self-service
GET  /api/v1/me/payslips/{ulid}/download  encrypted, access logged
GET  /api/v1/me/loans                    outstanding balances
GET  /api/v1/me/tax-certificate          ?year=   annual certificate
```

Payroll is otherwise Livewire-only. There is no API path to compute, approve or post a run.

### 8. Permissions · Settings · Events

```
payroll.view                    payroll.manage ⚠
payroll.staff.manage ⚠          payroll.statutory.manage ⚠⚠
payroll.run ⚠                   payroll.approve ⚠⚠
payroll.post ⚠⚠                 payroll.pay ⚠⚠
payroll.loan.manage             payroll.returns.manage 🇿🇼
payroll.report.view             payroll.reverse ⚠⚠
```

| Setting | Type | Default |
|---|---|---|
| `payroll.pay_day_of_month` | int | `25` |
| `payroll.variance_alert_percent` | int | `15` |
| `payroll.block_on_unconfirmed_statutory` | bool | `true` (**locked**) |
| `payroll.require_separate_approver` | bool | `true` (**locked**) |
| `payroll.statutory_due_day` | int | `10` 🇿🇼 |
| `payroll.itf16_due_date` | string | `01-31` 🇿🇼 |
| `payroll.return_alert_days` | array | `[7,3,1]` |
| `payroll.allow_negative_net` | bool | `false` |

Events: `StatutoryConfigActivated` 🇿🇼 · `UnconfirmedStatutoryConfigBlocking` ⚠ · `PayrollComputed` · `PayrollException` ⚠ · `PayrollApproved` · `PayrollPosted` · `PayslipsDistributed` · `StatutoryReturnPrepared` 🇿🇼 · `StatutoryReturnDue` ⚠ · `StatutoryReturnOverdue` ⚠⚠ · `LoanFullyRecovered` · `NegativeNetPayDetected` ⚠

### 9. Acceptance criteria

```gherkin
AC-PPL-05-001
  Given the NSSA configuration is flagged requires_confirmation and unconfirmed
  When a payroll run is attempted
  Then it is blocked
  And the setup screen shows which configuration needs confirmation

AC-PPL-05-002
  Given a staff member earns in USD
  Then the USD PAYE band table is applied
  And a ZiG earner in the same run uses the ZiG table

AC-PPL-05-003
  Given PAYE due is USD 240.00 and the AIDS Levy rate is 3%
  Then the AIDS Levy is USD 7.20
  And it is not computed on gross pay

AC-PPL-05-004
  Given a staff member's pensionable earnings are USD 1,000 against a
       configured POBS ceiling of USD 700 at 4.5%/4.5%
  Then employee and employer NSSA pension each compute as 4.5% of USD 700,
       i.e. USD 31.50 each, not 4.5% of USD 1,000
  And the payslip trace states the ceiling and that it applied

AC-PPL-05-004B
  Given the same staff member's APWCS rate is configured at the school's
       gazetted industrial classification percentage
  Then the APWCS contribution computes on the full USD 1,000, not on the
       USD 700 POBS ceiling
  And no employee deduction is made for it
  And it appears only as an employer cost line, never on the employee's
       deductions total

AC-PPL-05-005
  Given a payroll run is computed
  When no approval has been recorded
  Then no journal exists and no payslip is released

AC-PPL-05-006
  Given a staff member's net pay would be negative
  Then they appear on the exception report
  And the run completes for everyone else

AC-PPL-05-007
  Given a payroll run is posted
  Then statutory returns are prepared with due dates
  And P2, NSSA, NEC, ZIMDEF and AIDS Levy are all dated the 10th of next month

AC-PPL-05-008
  Given a staff member's children's fees are deducted at source
  Then the learner's fee account is credited through FIN-02
  And the guardian statement shows the payment

AC-PPL-05-009
  Given statutory rates change mid-year
  When a 2025 payslip is regenerated
  Then it uses the rates in force at that pay date

AC-PPL-05-010
  Given a user lacks staff.view_compensation
  When they view any staff record or run any export
  Then salary, banking and payslip data are absent

AC-PPL-05-011
  Given the twelve monthly P2 returns for a year
  When ITF16 is prepared
  Then the totals reconcile
  And a mismatch blocks preparation naming the discrepancy
```

---

# FIN-13 · ZIMRA Fiscalisation (FDMS) 🇿🇼

> Retro-fits across every commercial receipt path already built. Treat the ZIMRA integration as an isolated adapter behind an interface, and **never let it block a receipt** — money is taken first, fiscalised asynchronously, reconciled after.

### 1. Scope

Device registration and certificate lifecycle, fiscal day management, receipt submission, per-currency counters, tax type handling, the fiscalisation routing engine, offline queue and resubmission, QR and verification code rendering, Z-report submission, failure retry and audit.

### 2. Regulatory context

ZIMRA's Fiscalisation Data Management System permits **virtual fiscalisation by direct server-to-server API integration** between a taxpayer's accounting, point-of-sale or invoicing system and ZIMRA — the route explicitly intended for medium-to-large taxpayers running centralised systems. The obligation derives from Section 90 of the Income Tax Act as read with Sections 80D and 80DD, and SI 104 of 2010; the current requirements are set out in Public Notice 50 of 2023 and Public Notice 26 of 2024. API documentation is obtained from ZIMRA's downloads portal.

**The routing question matters more than the integration.** Core tuition is generally not a commercial supply. Tuckshop sales, uniform sales, textbook sales, hall and bus hire, farm produce sales and certain non-exempt levies are. Getting the routing wrong either under-reports (a compliance exposure) or over-reports (fiscalising exempt tuition, which is worse — it creates a VAT liability that does not exist).

### 3. Data model

```sql
fiscal_devices
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
device_id               VARCHAR(40)  NOT NULL   -- ZIMRA-assigned
device_serial           VARCHAR(60)  NOT NULL
device_branch_id        VARCHAR(40)  NULL
taxpayer_name           VARCHAR(200) NOT NULL
taxpayer_tin            VARCHAR(30)  NOT NULL   -- ENCRYPTED
vat_number              VARCHAR(30)  NULL       -- ENCRYPTED
-- certificate lifecycle
csr_generated_at        TIMESTAMP    NULL
certificate_pem         TEXT         NULL       -- ENCRYPTED
private_key_ref         VARCHAR(200) NULL       -- key store reference, never inline
certificate_issued_at   TIMESTAMP    NULL
certificate_expires_at  TIMESTAMP    NULL       -- ⭐ alerting
-- environment
environment             VARCHAR(20)  NOT NULL   -- sandbox|production
api_base_url            VARCHAR(255) NOT NULL
-- operational
operating_mode          VARCHAR(20)  NULL       -- from FDMS configuration
taxpayer_day_max_hours  SMALLINT     NULL
applicable_taxes        JSON         NULL       -- synced from FDMS
last_config_sync_at     TIMESTAMP    NULL
last_ping_at            TIMESTAMP    NULL
last_ping_status        VARCHAR(20)  NULL       -- online|offline|error
status                  VARCHAR(20)  NOT NULL   -- registering|active|
                                                -- suspended|blocked|retired
is_active               TINYINT(1)   NOT NULL DEFAULT 0
  UNIQUE (school_id, device_id)
  INDEX  (school_id, is_active)
  INDEX  (certificate_expires_at)

fiscal_days
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
device_id               BIGINT       FK INDEX
fiscal_day_number       INT          NOT NULL   -- ⭐ sequential per device
opened_at               TIMESTAMP    NOT NULL
opened_by               BIGINT       FK → users.id
closed_at               TIMESTAMP    NULL
closed_by               BIGINT       NULL FK
-- local state
local_status            VARCHAR(20)  NOT NULL   -- open|close_pending|
                                                -- close_failed|closed
-- FDMS state, tracked separately ⭐
fdms_status             VARCHAR(30)  NULL       -- FiscalDayOpened|
                                                -- FiscalDayCloseInitiated|
                                                -- FiscalDayClosed|
                                                -- FiscalDayCloseFailed
receipt_count           INT          NOT NULL DEFAULT 0
counters                JSON         NULL       -- per currency, per tax type
day_hash                VARCHAR(120) NULL
day_signature           TEXT         NULL
close_attempts          SMALLINT     NOT NULL DEFAULT 0
close_error             TEXT         NULL
  UNIQUE (school_id, device_id, fiscal_day_number)
  INDEX  (school_id, local_status)

fiscal_receipts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
device_id               BIGINT       FK INDEX
fiscal_day_id           BIGINT       FK INDEX
-- source: ANY commercial receipt in the platform
source_type             VARCHAR(60)  NOT NULL   -- receipt|invoice|credit_note|
                                                -- wallet_sale|farm_sale|
                                                -- facility_hire
source_id               BIGINT       NOT NULL
receipt_type            VARCHAR(20)  NOT NULL   -- fiscal_invoice|
                                                -- credit_note|debit_note
receipt_currency        CHAR(3)      NOT NULL   -- ⭐ separate counters
receipt_counter         INT          NOT NULL   -- per device per currency
global_counter          INT          NOT NULL   -- per device, all currencies
invoice_number          VARCHAR(60)  NOT NULL
receipt_date            TIMESTAMP    NOT NULL
-- amounts
total_minor             BIGINT       NOT NULL
tax_breakdown           JSON         NOT NULL   -- per tax type: base, rate, amount
payment_methods         JSON         NOT NULL   -- cash|card|mobile_wallet|
                                                -- bank_transfer|coupon|credit
-- buyer, optional
buyer_name              VARCHAR(200) NULL
buyer_tin               VARCHAR(30)  NULL       -- ENCRYPTED
buyer_registration      VARCHAR(40)  NULL
buyer_address           VARCHAR(255) NULL
-- credit/debit note linkage
credited_receipt_id     BIGINT       NULL FK
credit_reason           VARCHAR(255) NULL
-- fiscal artefacts
receipt_hash            VARCHAR(120) NULL
receipt_signature       TEXT         NULL
previous_receipt_hash   VARCHAR(120) NULL       -- chained
verification_code       VARCHAR(80)  NULL       -- ⭐ printed
qr_url                  VARCHAR(500) NULL       -- ⭐ printed as QR
fdms_receipt_id         VARCHAR(60)  NULL
-- submission
status                  VARCHAR(20)  NOT NULL   -- queued|submitting|
                                                -- submitted|accepted|
                                                -- rejected|failed|
                                                -- offline_queued
submitted_at            TIMESTAMP    NULL
accepted_at             TIMESTAMP    NULL
attempt_count           SMALLINT     NOT NULL DEFAULT 0
last_attempt_at         TIMESTAMP    NULL
error_code              VARCHAR(60)  NULL
error_message           TEXT         NULL
payload                 JSON         NOT NULL   -- exact submitted payload
response                JSON         NULL
  UNIQUE (school_id, device_id, receipt_currency, receipt_counter)
  UNIQUE (source_type, source_id)
  INDEX  (school_id, status, receipt_date)
  INDEX  (school_id, fiscal_day_id)

fiscalisation_rules                   -- ⭐ the routing engine
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
rule_name               VARCHAR(150) NOT NULL
source_type             VARCHAR(60)  NOT NULL   -- fee_component|
                                                -- wallet_product|
                                                -- farm_sale|facility_hire|
                                                -- uniform_sale
source_identifier       VARCHAR(60)  NULL       -- component code, category
is_fiscalisable         TINYINT(1)   NOT NULL
tax_type                VARCHAR(20)  NOT NULL   -- standard|zero_rated|
                                                -- exempt|withholding
tax_rate_percent        DECIMAL(5,2) NOT NULL DEFAULT 0
tax_code                VARCHAR(20)  NULL       -- FDMS tax code
rationale               VARCHAR(255) NULL       -- ⭐ why, for audit
priority                SMALLINT     NOT NULL DEFAULT 100
is_active               TINYINT(1)   NOT NULL DEFAULT 1
reviewed_by             BIGINT       NULL FK    -- accountant sign-off
reviewed_at             TIMESTAMP    NULL
  INDEX (school_id, source_type, is_active, priority)

fiscal_z_reports
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
device_id               BIGINT       FK
fiscal_day_id           BIGINT       FK UNIQUE
report_date             DATE         NOT NULL
receipt_count           INT          NOT NULL
totals_by_currency      JSON         NOT NULL
totals_by_tax_type      JSON         NOT NULL
totals_by_payment       JSON         NOT NULL
submitted_at            TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL   -- pending|submitted|
                                                -- accepted|failed
document_id             BIGINT       NULL FK

fiscal_audit_log                      -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
device_id               BIGINT       NULL FK
event_type              VARCHAR(40)  NOT NULL   -- device_registered|
                                                -- certificate_renewed|
                                                -- day_opened|day_closed|
                                                -- receipt_submitted|
                                                -- receipt_rejected|
                                                -- config_synced|ping|
                                                -- offline_entered|
                                                -- offline_drained
reference               VARCHAR(80)  NULL
request_payload         JSON         NULL
response_payload        JSON         NULL
http_status             SMALLINT     NULL
duration_ms             INT          NULL
occurred_at             TIMESTAMP(6) NOT NULL
  INDEX (school_id, event_type, occurred_at)
```

### 4. ⭐ The cardinal rule: fiscalisation never blocks a receipt

```
CASHIER TAKES MONEY  (FIN-04, FIN-14, OPS-03, OPS-05)
        │
        ├─ Receipt created, journal posted, learner credited   ← ALWAYS SUCCEEDS
        │
        ▼
   Routing engine: is any line fiscalisable?
        │
        ├─ No  → fiscalisation_status = not_required.  Done.
        │
        └─ Yes → fiscal_receipt row created, status = queued
                 JOB-SubmitFiscalReceipt dispatched (priority queue)
                        │
                        ├─ FDMS reachable → submit → accepted
                        │                   → verification code + QR stored
                        │                   → receipt PDF regenerated with them
                        │
                        └─ FDMS unreachable → status = offline_queued
                                              → retry with backoff
                                              → queue depth on health dashboard
                                              → drains automatically on reconnect
```

**Why this ordering is non-negotiable.** A Zimbabwean school's internet drops. If fiscalisation is synchronous and blocking, the bursary counter stops on fee-deadline day with two hundred parents queuing. Money is taken first. Compliance follows within minutes or hours. The queue depth is monitored, and a persistent backlog alerts.

**The printed receipt.** Where the verification code is not yet available, the receipt prints with a clear note that the fiscal code will follow, and the parent can retrieve the fiscalised version from the portal once accepted. This is honest and auditable; printing a blank space is neither.

### 5. Fiscal day lifecycle

```
OPEN DAY
  Called at first fiscalisable transaction of the day, or on schedule.
  fiscal_day_number increments per device.
  Counters reset per currency.

TRANSACT
  Each receipt increments:
     receipt_counter  (per device, per CURRENCY)  ⭐
     global_counter   (per device, all currencies)
  Receipt hash chains to the previous receipt.

CLOSE DAY
  local_status → close_pending
  Day hash and signature computed over the day's counters
  Submitted to FDMS
  ⭐ The day is NOT marked closed locally until FDMS confirms FiscalDayClosed.
     A close_pending day that fails retries; it never silently reopens.

  IF the taxpayer day maximum is exceeded without a close
     → alert; FDMS may reject subsequent receipts
```

Local status and FDMS status are tracked as **separate columns** because they legitimately diverge — a close can be initiated and not yet confirmed, and treating that as closed produces counter mismatches that are painful to unwind.

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-13-001` ⭐ | Fiscalisation **never** blocks receipting. A receipt is created and its journal posted regardless of FDMS availability. |
| `BR-FIN-13-002` | The routing engine determines fiscalisability from `fiscalisation_rules`, evaluated by priority. Every rule carries a rationale and requires accountant review before activation. |
| `BR-FIN-13-003` | Tuition and exempt levies are not fiscalised by default. Tuckshop, uniform, textbook, hall hire, bus hire and farm produce sales are. Every default is a rule row a school can change with its accountant. |
| `BR-FIN-13-004` | Receipt counters are maintained **separately per currency** per device, alongside a global counter. |
| `BR-FIN-13-005` | Receipt hashes chain to the previous receipt on the same device, making sequence gaps detectable. |
| `BR-FIN-13-006` | A fiscal day opens before the first fiscalisable receipt and must close within the configured taxpayer day maximum. Approaching that limit alerts. |
| `BR-FIN-13-007` | Local and FDMS day status are tracked separately. A day is closed locally only on FDMS confirmation. |
| `BR-FIN-13-008` | Failed day closes retry with backoff and alert after the configured attempts. A day never silently reopens. |
| `BR-FIN-13-009` | Offline receipts queue locally and drain automatically on reconnect, in counter order. |
| `BR-FIN-13-010` | Queue depth appears on the system health dashboard. A backlog beyond threshold raises a critical alert. |
| `BR-FIN-13-011` | Rejected receipts are retained with the full error, surfaced on the retry workbench, and never silently discarded. |
| `BR-FIN-13-012` | Every request and response is logged verbatim in `fiscal_audit_log`, append-only. When ZIMRA disputes what was sent, the payload settles it. |
| `BR-FIN-13-013` | A voided source receipt raises a **credit note** to FDMS referencing the original fiscal receipt. The original is never deleted or amended. |
| `BR-FIN-13-014` | Device certificates alert at 60, 30 and 7 days before expiry. Renewal generates a new CSR through the device lifecycle. |
| `BR-FIN-13-015` | Private keys are held in a key store and referenced, never stored inline in the database. |
| `BR-FIN-13-016` | Sandbox and production devices are strictly separated and visibly labelled. A sandbox device cannot fiscalise a production receipt. |
| `BR-FIN-13-017` | Configuration is synced from FDMS on a schedule and on demand; applicable tax types come from FDMS, not from local assumption. |
| `BR-FIN-13-018` | Z-reports compile on day close and submit automatically. |
| `BR-FIN-13-019` | Accepted receipts render the verification code and QR onto the receipt PDF, which is regenerated at that point. |
| `BR-FIN-13-020` | A multi-entity group operates one device per registered taxpayer entity; receipts route to the device for the school's own registration. |
| `BR-FIN-13-021` | Fiscalisation status is reconciled daily against source receipts. Any fiscalisable receipt with no accepted fiscal receipt after the configured window appears on an exception report and blocks period close. |

### 7. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| **Device configuration** | `Fiscal\Devices\Index` | `fiscal.device.manage` ⚠⚠ — registration, CSR, certificate, environment |
| Certificate lifecycle | `Fiscal\Devices\Certificate` | `fiscal.device.manage` ⚠⚠ — expiry, renewal |
| **Routing rules** | `Fiscal\Rules\Index` | `fiscal.rules.manage` ⚠ — what is fiscalised, rationale, accountant review |
| Fiscal day control | `Fiscal\Days\Index` | `fiscal.day.manage` — open, close, status, counters |
| Receipt monitor | `Fiscal\Receipts\Index` | `fiscal.view` — status, counters, verification codes |
| **Retry workbench** | `Fiscal\Receipts\Retry` | `fiscal.retry` ⭐ — failed and rejected, with errors, bulk retry |
| Offline queue | `Fiscal\Queue\Status` | `fiscal.view` — depth, oldest item, drain progress |
| Z-reports | `Fiscal\Reports\ZReports` | `fiscal.view` |
| Reconciliation | `Fiscal\Reconciliation\Index` | `fiscal.view` — fiscalisable receipts vs accepted fiscal receipts |
| Audit log | `Fiscal\Audit\Index` | `fiscal.audit.view` — raw payloads |

```
GET /api/v1/fiscal/receipts/{ulid}       verification code and QR for the payer
GET /api/v1/fiscal/status                health: device, day, queue depth
```

| Setting | Type | Default |
|---|---|---|
| `fiscal.enabled` | bool | `false` |
| `fiscal.environment` | enum | `sandbox` |
| `fiscal.auto_open_day` | bool | `true` |
| `fiscal.auto_close_day_time` | time | `23:00` |
| `fiscal.submission_retry_attempts` | int | `10` |
| `fiscal.offline_queue_alert_depth` | int | `50` |
| `fiscal.certificate_alert_days` | array | `[60,30,7]` |
| `fiscal.reconciliation_window_hours` | int | `24` |
| `fiscal.block_period_close_on_unfiscalised` | bool | `true` |

Events: `FiscalDeviceRegistered` · `CertificateExpiring` ⚠ · `FiscalDayOpened` · `FiscalDayClosed` · `FiscalDayCloseFailed` ⚠ · `ReceiptFiscalised` · `ReceiptRejected` ⚠ · `OfflineQueueBacklog` ⚠ · `FiscalReconciliationException` ⚠ · `ZReportSubmitted`

### 8. Acceptance criteria

```gherkin
AC-FIN-13-001
  Given the FDMS endpoint is unreachable
  When a tuckshop sale is receipted
  Then the receipt is created and its journal posted successfully
  And a fiscal receipt is queued offline
  And the queue depth appears on the health dashboard

AC-FIN-13-002
  Given queued fiscal receipts exist and FDMS becomes reachable
  Then they submit automatically in counter order

AC-FIN-13-003
  Given a tuition fee receipt
  Then it is not fiscalised
  And fiscalisation_status is 'not_required'

AC-FIN-13-004
  Given a receipt contains both tuition and a uniform sale
  Then only the uniform line is fiscalised
  And the tax breakdown reflects only that line

AC-FIN-13-005
  Given receipts are issued in both USD and ZWG on the same device
  Then each currency maintains its own receipt counter
  And the global counter increments for both

AC-FIN-13-006
  Given a day close is submitted and FDMS has not confirmed
  Then local_status is close_pending
  And the day is not marked closed
  And retry continues

AC-FIN-13-007
  Given a fiscalised receipt is voided
  Then a credit note is submitted to FDMS referencing the original
  And the original fiscal receipt is unchanged

AC-FIN-13-008
  Given a receipt is accepted by FDMS
  Then the verification code and QR are stored
  And the receipt PDF is regenerated carrying them

AC-FIN-13-009
  Given a fiscalisable receipt has no accepted fiscal receipt after 24 hours
  Then it appears on the reconciliation exception report
  And it blocks financial period close

AC-FIN-13-010
  Given a device is configured for sandbox
  Then it cannot fiscalise a production receipt
  And the environment is labelled on every fiscal screen
```

---

# FIN-14 · Student Wallet & Tuckshop

### 1. Scope

Prepaid learner wallet, parent-set spending controls, point-of-sale terminal with offline capability, product catalogue linked to stores, multiple spend points, wallet-to-fee transfer, term-end balance handling, fiscalisation, float and reconciliation.

### 2. Data model

```sql
student_wallets
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
student_id              BIGINT       FK UNIQUE_PER_SCHOOL
balance_minor           BIGINT       NOT NULL DEFAULT 0   -- CACHE
currency                CHAR(3)      NOT NULL
liability_account_id    BIGINT       FK → accounts.id     -- ⭐ it's a liability
status                  VARCHAR(20)  NOT NULL   -- active|suspended|closed
-- ⭐ parent-set controls
daily_limit_minor       BIGINT       NULL
weekly_limit_minor      BIGINT       NULL
per_transaction_limit_minor BIGINT   NULL
blocked_categories      JSON         NULL       -- ['confectionery','energy_drinks']
low_balance_threshold_minor BIGINT   NULL
auto_topup_enabled      TINYINT(1)   NOT NULL DEFAULT 0
auto_topup_amount_minor BIGINT       NULL
controls_set_by         BIGINT       NULL FK    -- guardian
controls_updated_at     TIMESTAMP    NULL
last_transaction_at     TIMESTAMP    NULL
  UNIQUE (school_id, student_id)

wallet_transactions                   -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
wallet_id               BIGINT       FK INDEX
transaction_type        VARCHAR(30)  NOT NULL   -- topup|purchase|refund|
                                                -- transfer_to_fees|
                                                -- transfer_from_fees|
                                                -- adjustment|term_end_carry|
                                                -- term_end_refund
direction               CHAR(3)      NOT NULL   -- in | out
amount_minor            BIGINT       NOT NULL
balance_after_minor     BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
spend_point_id          BIGINT       NULL FK
sale_id                 BIGINT       NULL FK
receipt_id              BIGINT       NULL FK    -- FIN-04, for topups
journal_id              BIGINT       NULL FK
reference               VARCHAR(120) NULL
performed_by            BIGINT       NULL FK
occurred_at             TIMESTAMP(6) NOT NULL
  INDEX (school_id, wallet_id, occurred_at)
  INDEX (school_id, term_id, transaction_type)
  -- DB grants: INSERT, SELECT only.

spend_points
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
point_type              VARCHAR(30)  NOT NULL   -- tuckshop|canteen|
                                                -- stationery|printing|
                                                -- laundry|vending
store_id                BIGINT       NULL FK    -- FIN-09 stock source
till_id                 BIGINT       NULL FK    -- FIN-04 cash also accepted
income_account_id       BIGINT       FK
cost_centre_id          BIGINT       FK
is_fiscalisable         TINYINT(1)   NOT NULL DEFAULT 1   -- 🇿🇼
operating_hours         JSON         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

wallet_products
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
spend_point_id          BIGINT       FK INDEX
item_id                 BIGINT       NULL FK    -- FIN-09 inventory
code                    VARCHAR(30)  NOT NULL
name                    VARCHAR(150) NOT NULL
category                VARCHAR(40)  NOT NULL   -- ⭐ parent blocking key
price_minor             BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
tax_type                VARCHAR(20)  NOT NULL   -- 🇿🇼 FIN-13
barcode                 VARCHAR(60)  NULL
image_file_id           BIGINT       NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
sort_order              SMALLINT
  UNIQUE (school_id, spend_point_id, code)

wallet_sales
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
spend_point_id          BIGINT       FK INDEX
sale_number             VARCHAR(40)  NOT NULL
student_id              BIGINT       NULL FK    -- null for cash walk-up
wallet_id               BIGINT       NULL FK
sold_at                 TIMESTAMP    NOT NULL
subtotal_minor          BIGINT       NOT NULL
tax_minor               BIGINT       NOT NULL DEFAULT 0
total_minor             BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
payment_method          VARCHAR(20)  NOT NULL   -- wallet|cash|card
identification_method   VARCHAR(20)  NULL       -- rfid|qr|biometric|manual
cost_of_sales_minor     BIGINT       NULL       -- FIN-09
operator_id             BIGINT       FK → users.id
till_session_id         BIGINT       NULL FK    -- cash sales
journal_id              BIGINT       NULL FK
fiscal_receipt_id       BIGINT       NULL FK    -- 🇿🇼
device_source           VARCHAR(20)  NOT NULL   -- pos|mobile|offline_sync
offline_reference       CHAR(36)     NULL       -- idempotency
status                  VARCHAR(20)  NOT NULL   -- completed|voided|
                                                -- pending_sync
  UNIQUE (school_id, sale_number)
  UNIQUE (school_id, offline_reference)
  INDEX  (school_id, spend_point_id, sold_at)
  INDEX  (school_id, student_id, sold_at)

wallet_sale_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
sale_id                 BIGINT       FK INDEX
product_id              BIGINT       FK
quantity                DECIMAL(10,2) NOT NULL
unit_price_minor        BIGINT       NOT NULL
line_total_minor        BIGINT       NOT NULL
tax_type                VARCHAR(20)  NOT NULL
tax_minor               BIGINT       NOT NULL DEFAULT 0
stock_movement_id       BIGINT       NULL FK    -- FIN-09 depletion
```

### 3. ⭐ Wallet balance is a liability

A learner's wallet balance is **the school holding the parent's money**. It is a liability on the balance sheet, not income, until it is spent.

```
TOP-UP  (parent pays USD 50 through the portal)
    Dr  Bank / Gateway Clearing        50.00
      Cr  Student Wallet Liability             50.00
    ⭐ No income recognised.

PURCHASE  (learner buys USD 3.50 of tuckshop items)
    Dr  Student Wallet Liability        3.50
      Cr  Tuckshop Income                       3.50
    Dr  Cost of Sales                   2.10
      Cr  Inventory (FIN-09)                    2.10
    🇿🇼 Routed to FIN-13 for fiscalisation

TERM END, balance USD 12.30, policy = transfer to fees
    Dr  Student Wallet Liability       12.30
      Cr  Fee Debtors                          12.30
    ⭐ Never written to income.
```

**Unspent balances are never absorbed.** A school that quietly takes the residue of every learner's wallet at year end is holding money that is not theirs, and the system must make that impossible rather than merely discouraged.

### 4. Offline point-of-sale

A tuckshop queue at break must not stop because the network did.

```
POS caches:  wallet balances, spending controls, product catalogue, prices
             refreshed every 5 minutes while online

OFFLINE SALE
  1  Learner identified by RFID or QR from the local cache
  2  Balance and controls checked against the CACHED figures
  3  Sale recorded locally with a client-generated offline_reference
  4  Local balance decremented optimistically
  5  Queue depth shown to the operator

ON RECONNECT
  6  Sales sync in order, idempotent on offline_reference
  7  Server revalidates: if the true balance was insufficient,
       the sale still stands but the wallet goes negative
       and the parent is notified          ⭐
  8  Negative balances block further offline sales for that learner
```

**Step 7 is a deliberate trade-off.** Refusing to honour a sale a learner has already eaten is not workable. The system permits a limited negative balance, notifies the parent immediately, and prevents recurrence.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-14-001` ⭐ | Wallet balances are a liability. Top-ups never recognise income. |
| `BR-FIN-14-002` | `wallet_transactions` is append-only; the cached balance is verified nightly against the transaction sum. |
| `BR-FIN-14-003` | Every wallet movement posts a journal through `FIN-01`. |
| `BR-FIN-14-004` ⭐ | Spending controls are set by a guardian holding `is_fee_responsible` or an explicit wallet-control right, and are enforced server-side at the point of sale. |
| `BR-FIN-14-005` | Category blocks are enforced per line. A blocked item is refused with the reason shown to the operator, not silently omitted. |
| `BR-FIN-14-006` | Daily and weekly limits reset on the school's configured boundaries, not on rolling windows, so the rule is comprehensible to a parent. |
| `BR-FIN-14-007` | Low balance alerts notify the guardian at the configured threshold. |
| `BR-FIN-14-008` | Auto top-up, where enabled, initiates a `FIN-05` payment intent on the parent's stored method. It never charges without the parent's prior authorisation. |
| `BR-FIN-14-009` | Purchases deplete stock through `FIN-09` and post cost of sales separately from income. |
| `BR-FIN-14-010` | 🇿🇼 Sales at fiscalisable spend points route to `FIN-13`. Fiscalisation never blocks the sale. |
| `BR-FIN-14-011` | Offline sales are idempotent on `offline_reference` and sync in order. |
| `BR-FIN-14-012` | A sale syncing against an insufficient true balance is honoured, takes the wallet negative up to the configured limit, and notifies the guardian immediately. |
| `BR-FIN-14-013` | A negative wallet blocks further offline sales for that learner until settled. |
| `BR-FIN-14-014` | Parents see transaction history in near real time, itemised. |
| `BR-FIN-14-015` ⭐ | Term-end balances are handled per the school's configured policy — carry forward, refund, or transfer to fees. **Absorbing them into income is not an available option.** |
| `BR-FIN-14-016` | On withdrawal or graduation, any wallet balance is refunded or transferred to the fee account, and the wallet closes. |
| `BR-FIN-14-017` | Cash sales at a spend point require an open `FIN-04` till session and follow normal cash controls. |
| `BR-FIN-14-018` | Voiding a sale reverses the wallet movement, the stock movement and the journal, and raises a fiscal credit note where the sale was fiscalised. |
| `BR-FIN-14-019` | Wallet liability reconciles nightly: sum of wallet balances must equal the liability account balance. |

### 6. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| **POS terminal** | `Wallet\Pos\Terminal` | `wallet.sell` ⭐ — touch grid, scan learner, photo shown, balance, controls enforced, offline indicator |
| Products | `Wallet\Products\Index` | `wallet.manage` — price, category, stock linkage, tax type |
| Spend points | `Wallet\SpendPoints\Index` | `wallet.manage` |
| Wallet accounts | `Wallet\Accounts\Index` | `wallet.view` — balances, negative flagged |
| Learner wallet | `Wallet\Accounts\Show` | `wallet.view` — transactions, controls, top-up |
| Term-end processing | `Wallet\TermEnd\Process` | `wallet.manage` ⚠ — policy applied per learner, preview then commit |
| Reconciliation | `Wallet\Reports\Reconciliation` | `wallet.report.view` — balances vs liability account |
| Sales analytics | `Wallet\Reports\Sales` | `wallet.report.view` — by point, product, time of day |

```
GET  /api/v1/wallet/balance              learner or guardian
GET  /api/v1/wallet/transactions         paginated, itemised
POST /api/v1/wallet/topup                Idempotency-Key; → FIN-05
GET  /api/v1/wallet/controls             guardian
PUT  /api/v1/wallet/controls             guardian sets limits and blocks
POST /api/v1/wallet/pos/sale             Idempotency-Key; offline sync
GET  /api/v1/wallet/pos/cache            balances, controls, catalogue
```

| Setting | Type | Default |
|---|---|---|
| `wallet.enabled` | bool | `false` |
| `wallet.allow_negative_minor` | int | `500` (USD 5) |
| `wallet.pos_cache_refresh_minutes` | int | `5` |
| `wallet.term_end_policy` | enum | `carry_forward` |
| `wallet.low_balance_default_minor` | int | `500` |
| `wallet.daily_limit_default_minor` | int | `0` (unlimited) |
| `wallet.limit_reset_boundary` | enum | `calendar_day` |

Events: `WalletToppedUp` · `WalletPurchase` · `SpendingLimitReached` · `BlockedCategoryAttempt` · `WalletLowBalance` · `WalletNegative` ⚠ · `OfflineSalesSynced` · `TermEndBalanceProcessed` · `WalletReconciliationVariance` ⚠

### 7. Acceptance criteria

```gherkin
AC-FIN-14-001
  Given a parent tops up USD 50
  Then the journal credits Student Wallet Liability
  And no income is recognised

AC-FIN-14-002
  Given a guardian has blocked the 'confectionery' category
  When a learner attempts to buy a chocolate bar
  Then the line is refused with the reason shown to the operator

AC-FIN-14-003
  Given a daily limit of USD 5 and USD 4.20 already spent today
  When a USD 1.50 purchase is attempted
  Then it is refused with the remaining allowance shown

AC-FIN-14-004
  Given the POS is offline
  Then sales complete against cached balances and controls
  And sync idempotently on reconnect

AC-FIN-14-005
  Given an offline sale syncs against an insufficient true balance
  Then the sale stands
  And the wallet goes negative within the configured limit
  And the guardian is notified immediately
  And further offline sales for that learner are blocked

AC-FIN-14-006
  Given a term ends with a USD 12.30 wallet balance
  Then it carries forward, refunds, or transfers to fees per policy
  And no option exists to recognise it as income

AC-FIN-14-007
  Given a tuckshop sale at a fiscalisable spend point
  Then it routes to FIN-13
  And FDMS unavailability does not prevent the sale

AC-FIN-14-008
  Given nightly reconciliation runs
  When the sum of wallet balances differs from the liability account
  Then a variance alert is raised
```

---

# FIN-12 · Financial Reporting & Period Close ⭐

> The module that makes the Financial Integrity Charter visible. Every statement is a projection over immutable journal lines, which is what makes historical reproducibility structural rather than promised.

### 1. Scope

Statement generation from the general ledger, fee collection and debtor analysis, departmental profitability, the period close checklist, prior-period adjustment reporting, point-in-time reporting, scheduled delivery, accounting package export.

### 2. Data model

```sql
report_definitions                    -- statement structure, configurable
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL   -- 'INCOME_STATEMENT'
name                    VARCHAR(150) NOT NULL
report_type             VARCHAR(30)  NOT NULL   -- trial_balance|
                                                -- income_statement|
                                                -- balance_sheet|cash_flow|
                                                -- departmental|custom
structure               JSON         NOT NULL   -- sections, account groupings
comparative_periods     SMALLINT     NOT NULL DEFAULT 1
show_variance           TINYINT(1)   NOT NULL DEFAULT 1
show_budget             TINYINT(1)   NOT NULL DEFAULT 0
is_system               TINYINT(1)   NOT NULL DEFAULT 0
  UNIQUE (school_id, code)

period_close_checklists
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK INDEX
period_type             VARCHAR(10)  NOT NULL   -- academic|financial
run_at                  TIMESTAMP    NOT NULL
run_by                  BIGINT       FK → users.id
overall_status          VARCHAR(20)  NOT NULL   -- passed|failed|
                                                -- passed_with_acknowledgements
blocking_failures       SMALLINT     NOT NULL DEFAULT 0
warnings                SMALLINT     NOT NULL DEFAULT 0
results                 JSON         NOT NULL   -- per check
report_document_id      BIGINT       NULL FK
  INDEX (school_id, term_id, run_at)

close_check_acknowledgements
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
checklist_id            BIGINT       FK INDEX
check_key               VARCHAR(60)  NOT NULL
reason                  TEXT         NOT NULL   -- ⭐ mandatory
acknowledged_by         BIGINT       FK → users.id
acknowledged_at         TIMESTAMP    NOT NULL
approved_by             BIGINT       NULL FK

report_schedules
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
report_definition_id    BIGINT       FK
name                    VARCHAR(150) NOT NULL
frequency               VARCHAR(20)  NOT NULL   -- daily|weekly|monthly|termly
parameters              JSON         NULL
recipients              JSON         NOT NULL   -- user ids and emails
format                  VARCHAR(20)  NOT NULL   -- pdf|excel|both
next_run_at             TIMESTAMP    NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1

accounting_exports
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
target_system           VARCHAR(30)  NOT NULL   -- quickbooks|sage|pastel|
                                                -- generic_csv
period_from             DATE         NOT NULL
period_to               DATE         NOT NULL
journal_count           INT          NOT NULL
export_file_id          BIGINT       FK
exported_by             BIGINT       FK → users.id
exported_at             TIMESTAMP    NOT NULL
```

### 3. ⭐ Point-in-time reporting

The feature that defeats a retrospective tampering claim.

```
STANDARD REPORT
   "Income statement for 2026 Term 1"
   → journal lines WHERE effective_at BETWEEN term start AND term end

POINT-IN-TIME REPORT
   "Income statement for 2026 Term 1, AS KNOWN ON 2026-05-05"
   → journal lines WHERE effective_at BETWEEN term start AND term end
                     AND posted_at <= '2026-05-05'

The difference between the two is exactly the set of prior-period
adjustments posted after the reporting date, listed separately.
```

Because `posted_at` and `effective_at` are separate columns on every journal (`BR-FIN-01-028`), this is a filter, not a reconstruction. A bursar challenged on why the figure they reported in May differs from today's can produce both, with the reconciling items itemised.

### 4. The financial close checklist

Every check is registered by its owning module. `FIN-12` orchestrates; it knows nothing about fiscalisation or suspense.

| Check | Owner | Blocking | Failure meaning |
|---|---|---|---|
| Trial balance balances, per currency | `FIN-01` | ✅ | A posting bug. Stop everything. |
| Cached balances match derived | `FIN-01` | ✅ | Cache corruption |
| All till sessions closed | `FIN-04` | ✅ | Cash unaccounted |
| Suspense balance zero or acknowledged | `FIN-04` | ✅ | Unidentified money |
| Gateway reconciliation exceptions cleared | `FIN-05` | ✅ | Unproven receipts |
| Bank reconciliation complete | `FIN-05` | ✅ | |
| FX revaluation posted | `FIN-06` | ✅ | Balance sheet overstated |
| No draft invoices | `FIN-03` | ✅ | Unbilled income |
| No unposted billing runs | `FIN-02` | ⚠ | Income not raised |
| Fiscalisation reconciled | `FIN-13` | ✅ | 🇿🇼 Compliance exposure |
| Wallet liability reconciles | `FIN-14` | ✅ | |
| Stock balances match movements | `FIN-09` | ✅ | |
| Asset register matches ledger | `FIN-10` | ✅ | |
| Depreciation posted for every month | `FIN-10` | ✅ | |
| Payroll posted and returns prepared | `PPL-05` | ✅ | 🇿🇼 |
| Supplier accruals cleared | `FIN-08` | ⚠ | |
| Open commitments reviewed | `FIN-11` | ⚠ | |
| Carry-forward invariant holds | `CORE-03` | ✅ | ⭐ The core promise |

**A blocking failure cannot be overridden.** An acknowledgeable warning requires a written reason and appears on the close report. The distinction is deliberate: a trial balance that does not balance is not a judgement call.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-12-001` ⭐ | Every statement is generated from `journal_lines`. No report reads a cached balance for a figure a school will act on financially. |
| `BR-FIN-12-002` | Every report figure drills to journals and then to the source document. |
| `BR-FIN-12-003` | Reports are producible for any date range, including fully closed and archived periods, and reproduce identically. |
| `BR-FIN-12-004` ⭐ | Point-in-time reporting filters on `posted_at` and lists the reconciling prior-period adjustments separately. |
| `BR-FIN-12-005` | Prior-period adjustments appear on their own line in every affected report, never blended. |
| `BR-FIN-12-006` | Statements are producible per currency and consolidated in base currency, with the rate basis stated. |
| `BR-FIN-12-007` | Departmental profitability aggregates by cost centre: boarding, farm, transport, tuckshop, academic, each showing income, direct cost, allocated overhead and net. |
| `BR-FIN-12-008` | Fee collection analysis reports billed, collected, discounted, written off and outstanding, by component, class, section and residency, per currency. |
| `BR-FIN-12-009` | The close checklist runs on demand and is re-runnable. Its result is stored with a timestamp. |
| `BR-FIN-12-010` ⭐ | Blocking failures cannot be overridden by any user. Warnings require a written reason to acknowledge. |
| `BR-FIN-12-011` | A period cannot transition to `LOCKED` without a checklist run showing zero blocking failures. |
| `BR-FIN-12-012` | The close produces a signed PDF pack: statements, checklist result, acknowledgements, and who authorised the close. |
| `BR-FIN-12-013` | Scheduled reports deliver on their frequency to named recipients, and delivery is logged. |
| `BR-FIN-12-014` | Accounting exports produce journal-level detail in the target system's format, with the exported range recorded so double-export is detectable. |
| `BR-FIN-12-015` | Board reporting packs assemble the standard statements plus enrolment, collection rate and key ratios in one document. |

### 6. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Trial balance | `Reports\Financial\TrialBalance` | `finance.report.trial_balance` — per currency, as-at, verify from source |
| Income statement | `Reports\Financial\IncomeStatement` | `finance.report.view` — comparatives, budget column |
| Balance sheet | `Reports\Financial\BalanceSheet` | `finance.report.view` |
| Cash flow | `Reports\Financial\CashFlow` | `finance.report.view` |
| **Point-in-time** | `Reports\Financial\PointInTime` | `finance.report.view` ⭐ — as-at and as-known-on, reconciling items |
| Departmental P&L | `Reports\Financial\Departmental` | `finance.report.view` |
| Fee collection | `Reports\Financial\Collection` | `finance.report.view` |
| **Close checklist** | `Reports\Close\Checklist` | `finance.period.close` ⭐ — live pass/fail, drill to offending records |
| Close pack | `Reports\Close\Pack` | `finance.period.close` |
| Prior-period adjustments | `Reports\Financial\PriorPeriod` | `finance.report.view` |
| Scheduled reports | `Reports\Schedules\Index` | `finance.report.schedule` |
| Accounting export | `Reports\Export\Accounting` | `finance.report.export` ⚠ |
| Board pack | `Reports\Board\Pack` | `finance.report.board` |

```
GET /api/v1/finance/dashboard            executive summary for mobile
GET /api/v1/finance/collection-summary   ?term=
```

| Setting | Type | Default |
|---|---|---|
| `reporting.default_comparative_periods` | int | `1` |
| `reporting.show_budget_column` | bool | `true` |
| `reporting.consolidate_currency` | string | school base |
| `reporting.close_pack_recipients` | array | head, bursar |
| `reporting.overhead_allocation_basis` | enum | `learner_count` |

Events: `CloseChecklistRun` · `CloseCheckFailed` ⚠ · `CloseCheckAcknowledged` · `ClosePackGenerated` · `AccountingExported` · `ScheduledReportDelivered`

### 7. Acceptance criteria

```gherkin
AC-FIN-12-001
  Given a 2024 Term 2 income statement printed in 2024
  When it is regenerated in 2027
  Then it is identical

AC-FIN-12-002
  Given a receipt was posted into 2025 Term 3 after that period closed
  When the Term 3 income statement is produced
  Then the receipt appears on a prior-period adjustment line
  And the report states the period was reopened, by whom, and when

AC-FIN-12-003
  Given a point-in-time report for 2026 Term 1 as known on 2026-05-05
  Then only journals posted on or before that date are included
  And the difference against the current view is itemised

AC-FIN-12-004
  Given the trial balance does not balance
  When the close checklist runs
  Then it fails as blocking
  And no user can override it
  And the period cannot lock

AC-FIN-12-005
  Given the suspense balance is USD 1,200
  Then the check fails
  Unless acknowledged with a written reason recorded on the close pack

AC-FIN-12-006
  Given a period closes successfully
  Then a signed close pack is produced containing statements,
       checklist result, acknowledgements and the authorising user

AC-FIN-12-007
  Given the farm cost centre
  Then departmental P&L shows its production cost, kitchen transfer value,
       external sales and net position
```

---

# CMP-01 · ZIMSEC Candidate Registration & Results 🇿🇼

> Closes the `ExaminationCandidateSet` interface opened in Book E. `ACA-07` produces the confirmed candidate set; this module is everything that crosses the school boundary.

### 1. Scope

Candidate identification and eligibility, bio-data validation against ZIMSEC requirements, subject entry validation, export in the required format, entry fee billing and remittance reconciliation, statement of entry distribution, results import and analysis, verification requests.

**Design note.** ZIMSEC operates an Online Candidate Registration System through which authorised school personnel capture and submit candidate details, and a results distribution portal keyed on centre number. This module **prepares and validates**; a human submits through the ZIMSEC portal. The system does not attempt an unofficial integration.

### 2. Data model

```sql
zimsec_registrations
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
examination_session_id  BIGINT       NULL FK    -- ACA-07
exam_level              VARCHAR(20)  NOT NULL   -- grade_7|o_level|a_level
exam_series             VARCHAR(20)  NOT NULL   -- 'November 2026'
centre_number           VARCHAR(20)  NOT NULL
registration_opens_on   DATE         NULL
registration_closes_on  DATE         NOT NULL   -- ⭐ hard external deadline
candidate_count         SMALLINT     NOT NULL DEFAULT 0
validated_count         SMALLINT     NOT NULL DEFAULT 0
error_count             SMALLINT     NOT NULL DEFAULT 0
total_fees_minor        BIGINT       NOT NULL DEFAULT 0
collected_minor         BIGINT       NOT NULL DEFAULT 0
remitted_minor          BIGINT       NOT NULL DEFAULT 0
currency                CHAR(3)      NOT NULL
export_file_id          BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- preparing|validating|
                                                -- validated|exported|
                                                -- submitted|confirmed|closed
submitted_at            TIMESTAMP    NULL
submitted_by            BIGINT       NULL FK
zimsec_reference        VARCHAR(80)  NULL
  UNIQUE (school_id, exam_level, exam_series)

zimsec_candidates
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
registration_id         BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
candidate_number        VARCHAR(40)  NULL       -- assigned by ZIMSEC
-- ⭐ bio-data as ZIMSEC requires it
surname                 VARCHAR(80)  NOT NULL
forenames               VARCHAR(200) NOT NULL
date_of_birth           DATE         NOT NULL
gender                  VARCHAR(10)  NOT NULL
national_registration_no VARCHAR(30) NULL       -- ENCRYPTED
birth_certificate_no    VARCHAR(40)  NULL       -- ENCRYPTED
-- entries
subject_entries         JSON         NOT NULL   -- [{code, name, is_resit}]
subject_count           TINYINT      NOT NULL
is_repeat_candidate     TINYINT(1)   NOT NULL DEFAULT 0
previous_candidate_no   VARCHAR(40)  NULL
special_arrangements    JSON         NULL       -- from ACA-07
-- fees
entry_fee_minor         BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
invoice_id              BIGINT       NULL FK    -- FIN-03
fee_paid                TINYINT(1)   NOT NULL DEFAULT 0
-- validation
validation_status       VARCHAR(20)  NOT NULL   -- pending|valid|errors|warnings
validation_errors       JSON         NULL       -- ⭐ per-field
statement_of_entry_id   BIGINT       NULL FK
statement_confirmed     TINYINT(1)   NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL   -- draft|validated|
                                                -- submitted|confirmed|withdrawn
  UNIQUE (registration_id, student_id)
  INDEX  (school_id, validation_status)

zimsec_validation_rules               -- ⭐ configurable
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       NULL FK    -- null = system
exam_level              VARCHAR(20)  NULL
field                   VARCHAR(60)  NOT NULL   -- surname|date_of_birth|
                                                -- national_registration_no|
                                                -- subject_count
rule_type               VARCHAR(30)  NOT NULL   -- required|format|
                                                -- min_count|max_count|
                                                -- allowed_values
rule_value              VARCHAR(255) NULL
severity                VARCHAR(10)  NOT NULL   -- error | warning
message                 VARCHAR(255) NOT NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1

zimsec_results
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
registration_id         BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
candidate_number        VARCHAR(40)  NOT NULL
subject_code            VARCHAR(20)  NOT NULL
subject_name            VARCHAR(150) NOT NULL
grade                   VARCHAR(10)  NOT NULL
points                  DECIMAL(5,2) NULL
is_provisional          TINYINT(1)   NOT NULL DEFAULT 0
imported_at             TIMESTAMP    NOT NULL
imported_by             BIGINT       FK → users.id
source_file_id          BIGINT       NULL FK
  UNIQUE (registration_id, student_id, subject_code)
  INDEX  (school_id, student_id)
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-CMP-01-001` ⭐ | Candidates derive from `ACA-07` confirmed entries, which derive from `ACA-02` subject enrolments. Bio-data is never re-keyed. |
| `BR-CMP-01-002` ⭐ | Validation runs against configurable rules before export, and reports errors per candidate per field. The most common rejections — missing national registration number, name mismatch, out-of-range date of birth, subject count outside limits — are caught here rather than by ZIMSEC. |
| `BR-CMP-01-003` | Validation rules are data, not code, because ZIMSEC's requirements change between series. |
| `BR-CMP-01-004` | Export is refused while any candidate has a validation error. Warnings may be exported after acknowledgement. |
| `BR-CMP-01-005` | Subject entries validate against the pathway and subject-count rules in `ACA-01`. |
| `BR-CMP-01-006` | Entry fees raise per-candidate ad hoc charges through `FIN-02` and appear on the guardian's invoice. |
| `BR-CMP-01-007` ⭐ | Collections against entry fees reconcile to the amount remitted to ZIMSEC. A shortfall blocks registration closure and is reported — this is a recurring source of unexplained deficit. |
| `BR-CMP-01-008` | Registration deadline alerts fire at 30, 14, 7 and 1 days. An unsubmitted registration past its deadline alerts the head daily. |
| `BR-CMP-01-009` | Statements of entry are distributed to learners and guardians, and confirmation of receipt is tracked. |
| `BR-CMP-01-010` | Results import maps candidate numbers back to learner records, flagging any that do not match. |
| `BR-CMP-01-011` | Imported results write to `student_prior_results` and are available on transcripts. |
| `BR-CMP-01-012` | Pass-rate analysis reports by subject, teacher, class and year, with historical comparison. |
| `BR-CMP-01-013` | Centre number is validated as present and unique before any export. |

### 4. Screens · Acceptance criteria

| Screen | Component | Permission |
|---|---|---|
| Registrations | `Compliance\Zimsec\Registrations` | `zimsec.manage` — by level and series, deadline countdown |
| **Candidate validation** | `Compliance\Zimsec\Validation` | `zimsec.manage` ⭐ — errors per candidate per field, fix-in-place |
| Entry fees | `Compliance\Zimsec\Fees` | `zimsec.manage` — billed, collected, remitted, shortfall |
| Export | `Compliance\Zimsec\Export` | `zimsec.export` ⚠ |
| Statements of entry | `Compliance\Zimsec\Statements` | `zimsec.manage` |
| Results import | `Compliance\Zimsec\ResultsImport` | `zimsec.results.import` |
| Pass-rate analysis | `Compliance\Zimsec\Analysis` | `zimsec.view` |

```gherkin
AC-CMP-01-001
  Given a candidate has no national registration number
  Then validation raises an error naming the field
  And export is blocked until it is resolved

AC-CMP-01-002
  Given entry fees billed total USD 8,400 and collected total USD 7,950
  Then the shortfall of USD 450 is reported
  And registration closure is blocked until reconciled

AC-CMP-01-003
  Given candidates are confirmed in ACA-07
  Then their bio-data and subject entries populate automatically
  And no field is re-keyed

AC-CMP-01-004
  Given an A-Level candidate is entered for five subjects
  And the ACA-01 maximum is four
  Then validation flags it per the configured severity

AC-CMP-01-005
  Given results are imported
  Then they map to learner records by candidate number
  And unmatched candidates are reported, not silently dropped
```

---

# CMP-02 · MoPSE Returns & EMIS Reporting 🇿🇼

### 1. Scope

Annual Schools Census and EMIS data pack generation, term enrolment returns, staff establishment returns, inspection readiness pack, submission tracking, data quality validation.

### 2. Data model

```sql
statutory_school_returns
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
return_type             VARCHAR(40)  NOT NULL   -- annual_schools_census|
                                                -- term_enrolment|
                                                -- staff_establishment|
                                                -- infrastructure|
                                                -- inspection_pack
period_reference        VARCHAR(20)  NOT NULL
due_date                DATE         NOT NULL
authority               VARCHAR(80)  NOT NULL   -- 'MoPSE District','Province'
data_snapshot           JSON         NOT NULL   -- ⭐ frozen at generation
validation_result       JSON         NULL
quality_issues          SMALLINT     NOT NULL DEFAULT 0
export_file_id          BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- pending|generating|
                                                -- validated|submitted|
                                                -- acknowledged|overdue
generated_by            BIGINT       NULL FK
submitted_at            TIMESTAMP    NULL
submitted_by            BIGINT       NULL FK
acknowledgement_ref     VARCHAR(80)  NULL
  UNIQUE (school_id, return_type, period_reference)
  INDEX  (school_id, due_date, status)

data_quality_checks
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
check_key               VARCHAR(60)  NOT NULL   -- missing_national_reg|
                                                -- missing_dob|
                                                -- unallocated_class|
                                                -- staff_missing_qualification
entity_type             VARCHAR(40)  NOT NULL
affected_count          INT          NOT NULL
affected_ids            JSON         NULL
severity                VARCHAR(20)  NOT NULL   -- error|warning|info
last_checked_at         TIMESTAMP    NOT NULL
```

### 3. Business rules & acceptance

| ID | Rule |
|---|---|
| `BR-CMP-02-001` | Returns generate from live data across `PPL-01`, `PPL-04`, `CORE-02` and `ACA-04`, and **snapshot the data at generation** so a resubmitted return matches what was originally sent. _(Correction made during CMP-02 implementation: the module actually holding attendance registers is `ACA-04`, not `OPS-02` — Book H2's `OPS-02` is Maintenance & Works Management. `ACA-04`'s `AttendanceSession`/`AttendanceRecord` are what BR-CMP-02-005's inspection pack actually draws on.)_ |
| `BR-CMP-02-002` | Data quality validation runs before generation and reports missing national registration numbers, missing dates of birth, unallocated learners and incomplete staff records — the fields that cause Ministry rejection. |
| `BR-CMP-02-003` | Enrolment counts break down by level, gender and residency as the census requires. |
| `BR-CMP-02-004` | Staff establishment reports approved posts, filled posts, qualifications and vacancies. _(Scope note: `PPL-04`'s own `Staff` model documents `staff_qualifications` as deferred — no qualification records exist to report yet. CMP-02 reports posts/filled/vacancies from the real `EstablishmentPost` data and each staff member's `teacher_registration_no` as the closest real proxy; a `qualifications_tracked: false` flag accompanies the return so this gap is visible, not silently blank.)_ |
| `BR-CMP-02-005` | The inspection pack assembles attendance registers, staff records, statutory documents and policy acknowledgements on demand. |
| `BR-CMP-02-006` | Deadlines alert at 30, 14 and 7 days; overdue returns alert the head daily. |
| `BR-CMP-02-007` | Submission is recorded manually with a reference. The system prepares; a human submits. |

```gherkin
AC-CMP-02-001
  Given 34 learners have no national registration number
  When the Annual Schools Census is generated
  Then data quality validation reports them before export

AC-CMP-02-002
  Given a return was generated and submitted
  When it is regenerated later
  Then the original snapshot is preserved
  And any divergence from current data is shown

AC-CMP-02-003
  Given an inspection is announced
  When the inspection pack is generated
  Then it assembles registers, staff records, statutory documents
       and policy acknowledgements in one document
```

---

# CMP-03 · Data Protection, Consent & Privacy 🇿🇼

> Retro-fits across every module. The Cyber and Data Protection Act [Chapter 12:07] applies to every table in this specification, and the data subjects are largely children.

### 1. Scope

Consent registry, data subject access and correction requests, retention schedules and automated disposal, field-level encryption registry, breach logging and notification, processing register, third-party processor register, privacy notice versioning, special protections for minors.

### 2. Data model

```sql
consent_types
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(40)  NOT NULL   -- photography|publication|
                                                -- medical_treatment|
                                                -- trip_participation|
                                                -- data_sharing|marketing|
                                                -- biometric|transport
name                    VARCHAR(150) NOT NULL
description             TEXT         NOT NULL   -- plain language
lawful_basis            VARCHAR(40)  NOT NULL   -- consent|contract|
                                                -- legal_obligation|
                                                -- vital_interest|
                                                -- legitimate_interest
is_withdrawable         TINYINT(1)   NOT NULL DEFAULT 1
required_for_enrolment  TINYINT(1)   NOT NULL DEFAULT 0
applies_to              VARCHAR(20)  NOT NULL   -- student|guardian|staff
renewal_frequency_months SMALLINT    NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

consents                              -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
consent_type_id         BIGINT       FK
subject_type            VARCHAR(20)  NOT NULL   -- student|guardian|staff
subject_id              BIGINT       NOT NULL
granted_by_type         VARCHAR(20)  NOT NULL   -- guardian|self
granted_by_id           BIGINT       NOT NULL
granted                 TINYINT(1)   NOT NULL
granted_at              TIMESTAMP    NOT NULL
method                  VARCHAR(30)  NOT NULL   -- portal|paper_form|
                                                -- verbal_witnessed
notice_version          VARCHAR(20)  NOT NULL   -- ⭐ what they agreed to
document_file_id        BIGINT       NULL FK
witness_staff_id        BIGINT       NULL FK
ip_address              VARCHAR(45)  NULL
expires_on              DATE         NULL
withdrawn_at            TIMESTAMP    NULL
withdrawn_by            BIGINT       NULL FK
withdrawal_reason       VARCHAR(255) NULL
  INDEX (school_id, subject_type, subject_id, consent_type_id)
  INDEX (school_id, expires_on)

retention_schedules
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
record_class            VARCHAR(60)  NOT NULL   -- academic_record|
                                                -- financial_record|
                                                -- medical_record|
                                                -- safeguarding_record|
                                                -- cctv|application_unsuccessful|
                                                -- marketing_contact
table_names             JSON         NOT NULL   -- ⭐ what it governs
retention_years         DECIMAL(5,2) NOT NULL
retention_trigger       VARCHAR(40)  NOT NULL   -- record_created|
                                                -- learner_exit|
                                                -- staff_exit|case_closed
disposal_method         VARCHAR(30)  NOT NULL   -- delete|anonymise|archive
legal_basis             VARCHAR(255) NOT NULL
requires_review         TINYINT(1)   NOT NULL DEFAULT 1
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, record_class)

disposal_queue
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
schedule_id             BIGINT       FK
record_type             VARCHAR(60)  NOT NULL
record_id               BIGINT       NOT NULL
eligible_on             DATE         NOT NULL
review_status           VARCHAR(20)  NOT NULL   -- pending_review|approved|
                                                -- deferred|disposed
deferred_until          DATE         NULL
deferral_reason         VARCHAR(255) NULL
reviewed_by             BIGINT       NULL FK
disposed_at             TIMESTAMP    NULL
disposal_method         VARCHAR(30)  NULL
  INDEX (school_id, eligible_on, review_status)

subject_access_requests
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
request_type            VARCHAR(30)  NOT NULL   -- access|correction|
                                                -- erasure|portability|
                                                -- objection|restriction
subject_type            VARCHAR(20)  NOT NULL
subject_id              BIGINT       NULL
requester_name          VARCHAR(200) NOT NULL
requester_relationship  VARCHAR(60)  NULL
identity_verified       TINYINT(1)   NOT NULL DEFAULT 0
verification_method     VARCHAR(60)  NULL
verified_by             BIGINT       NULL FK
received_at             TIMESTAMP    NOT NULL
due_by                  DATE         NOT NULL   -- ⭐ statutory window
scope_description       TEXT         NOT NULL
status                  VARCHAR(20)  NOT NULL   -- received|verifying|
                                                -- compiling|under_review|
                                                -- fulfilled|refused|
                                                -- partially_fulfilled
refusal_grounds         TEXT         NULL
response_file_id        BIGINT       NULL FK
fulfilled_at            TIMESTAMP    NULL
handled_by              BIGINT       NULL FK
  INDEX (school_id, status, due_by)

data_breaches
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
detected_at             TIMESTAMP    NOT NULL
occurred_at             TIMESTAMP    NULL
breach_type             VARCHAR(40)  NOT NULL   -- unauthorised_access|
                                                -- disclosure|loss|
                                                -- theft|system_compromise|
                                                -- misdirected_communication
description             TEXT         NOT NULL
data_categories         JSON         NOT NULL
records_affected        INT          NULL
subjects_affected       INT          NULL
includes_minors         TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ escalates
severity                VARCHAR(20)  NOT NULL   -- low|medium|high|critical
containment_actions     TEXT         NULL
contained_at            TIMESTAMP    NULL
authority_notified      TINYINT(1)   NOT NULL DEFAULT 0
authority_notified_at   TIMESTAMP    NULL
subjects_notified       TINYINT(1)   NOT NULL DEFAULT 0
subjects_notified_at    TIMESTAMP    NULL
root_cause              TEXT         NULL
remedial_actions        TEXT         NULL
status                  VARCHAR(20)  NOT NULL   -- detected|investigating|
                                                -- contained|notified|closed
reported_by             BIGINT       FK → users.id
  INDEX (school_id, status, severity)

processing_register
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
activity_name           VARCHAR(200) NOT NULL
purpose                 TEXT         NOT NULL
lawful_basis            VARCHAR(40)  NOT NULL
data_categories         JSON         NOT NULL
subject_categories      JSON         NOT NULL
recipients              JSON         NULL
retention_schedule_id   BIGINT       NULL FK
involves_minors         TINYINT(1)   NOT NULL DEFAULT 0
is_special_category     TINYINT(1)   NOT NULL DEFAULT 0   -- medical, biometric
security_measures       TEXT         NULL
owning_module           VARCHAR(20)  NULL
last_reviewed_on        DATE         NULL

third_party_processors
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
name                    VARCHAR(200) NOT NULL
processor_type          VARCHAR(40)  NOT NULL   -- payment_gateway|sms|
                                                -- whatsapp|cloud_storage|
                                                -- email|analytics|backup
data_shared             JSON         NOT NULL
purpose                 TEXT         NOT NULL
country                 CHAR(2)      NULL       -- cross-border transfer
agreement_file_id       BIGINT       NULL FK
agreement_expires_on    DATE         NULL
status                  VARCHAR(20)  NOT NULL
last_reviewed_on        DATE         NULL

privacy_notices                       -- ⚠ added during implementation, see below
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
version                 VARCHAR(20)  NOT NULL
title                   VARCHAR(200) NOT NULL
content                 LONGTEXT     NOT NULL
effective_from          DATE         NOT NULL
requires_reconsent      TINYINT(1)   NOT NULL DEFAULT 0
created_by              BIGINT       FK → users.id
  UNIQUE (school_id, version)
```

**Correction made during CMP-03 implementation**: this section's original SQL block never defined a `privacy_notices` table, despite `consents.notice_version` needing a real source of truth for "what version was in force" (BR-CMP-03-002) and the "Privacy notices" screen this book's own §4 names. Added above, minimally — a plain versioned record. `requires_reconsent` is what BR-CMP-03-015 acts on.

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-CMP-03-001` | Consents are append-only. Withdrawal is a new state on the record, never a deletion. |
| `BR-CMP-03-002` ⭐ | Every consent records the privacy notice version in force at the time. A parent who consented under version 3 did not consent to version 7. |
| `BR-CMP-03-003` | Withdrawing consent takes effect immediately across every module. Photography withdrawal removes the learner from publication workflows the same day. |
| `BR-CMP-03-004` | Consents based on legal obligation or vital interest are recorded but not withdrawable, and the basis is displayed to the subject. |
| `BR-CMP-03-005` | Every table holding personal data is governed by a retention schedule. A table absent from every schedule fails a nightly registry check. |
| `BR-CMP-03-006` | Records reaching their retention date enter the disposal queue for review. **Disposal is never automatic without review** where the schedule requires it. |
| `BR-CMP-03-007` | Safeguarding, medical and financial records carry longer retention and are excluded from routine disposal. |
| `BR-CMP-03-008` ⭐ | Subject access requests carry a statutory due date, require identity verification before disclosure, and compile from every module. |
| `BR-CMP-03-009` | A subject access request never discloses safeguarding records, third-party personal data, or information that would identify another data subject, without separate assessment. |
| `BR-CMP-03-010` | Erasure requests are assessed against retention obligations. Refusal states the lawful ground. |
| `BR-CMP-03-011` ⭐ | Breaches involving minors' data escalate automatically to the head and the safeguarding lead, and are flagged for authority notification assessment. |
| `BR-CMP-03-012` | Breach notification decisions and timings are recorded whether or not notification occurs. |
| `BR-CMP-03-013` | The processing register documents every activity, and each module registers its own entries. |
| `BR-CMP-03-014` | Third-party processors are registered with what is shared and under what agreement. Cross-border transfers are flagged. |
| `BR-CMP-03-015` | Privacy notices are versioned; a material change requires re-consent for consent-based processing. |
| `BR-CMP-03-016` ⭐ | Learner portal data exposure is age-gated, and marketing processing of minors' data is not permitted. |

### 4. Screens · Acceptance criteria

| Screen | Component | Permission |
|---|---|---|
| Consent types | `Compliance\Privacy\ConsentTypes` | `privacy.manage` |
| Consent register | `Compliance\Privacy\Consents` | `privacy.view` — by subject, type, status |
| **Retention schedules** | `Compliance\Privacy\Retention` | `privacy.manage` ⚠ — record class, trigger, method |
| Disposal queue | `Compliance\Privacy\Disposal` | `privacy.dispose` ⚠⚠ — review before disposal |
| Subject requests | `Compliance\Privacy\Requests` | `privacy.request.handle` ⚠ — due date, verification, compilation |
| Breach register | `Compliance\Privacy\Breaches` | `privacy.breach.manage` ⚠⚠ |
| Processing register | `Compliance\Privacy\Processing` | `privacy.view` |
| Processors | `Compliance\Privacy\Processors` | `privacy.manage` |
| Privacy notices | `Compliance\Privacy\Notices` | `privacy.manage` — versioned, acceptance tracked |

```gherkin
AC-CMP-03-001
  Given a guardian withdraws photography consent
  Then the learner is excluded from publication workflows immediately

AC-CMP-03-002
  Given a subject access request is received
  Then a statutory due date is set
  And identity must be verified before any disclosure
  And the response compiles from every module

AC-CMP-03-003
  Given a subject access request for a learner
  Then safeguarding records are excluded
  And third-party personal data is redacted

AC-CMP-03-004
  Given a breach involves 40 learners' medical data
  Then it escalates to the head and safeguarding lead automatically
  And is flagged for authority notification assessment

AC-CMP-03-005
  Given records reach their retention date
  Then they enter the disposal queue for review
  And are not deleted automatically where review is required

AC-CMP-03-006
  Given a table holds personal data and is not covered by any retention schedule
  Then the nightly registry check fails and reports it
```

---

# CMP-04 · Policy, Document Register & Retention

### 1. Scope

Policy repository with versioning and acknowledgement tracking, statutory document register with expiry, board and committee minutes, contract register, consolidated incident register.

### 2. Data model (abridged)

```sql
policies
──────────────────────────────────────────────────────────────────
id, ulid, school_id, code, title, category, version,
content LONGTEXT | document_file_id, effective_from, review_due_on,
approved_by, board_approved_on,
requires_acknowledgement TINYINT(1),
acknowledgement_audiences JSON,        -- staff|guardians|learners
status VARCHAR(20),                    -- draft|active|superseded|withdrawn
supersedes_policy_id

policy_acknowledgements               -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id, school_id, policy_id, policy_version,
acknowledged_by_type, acknowledged_by_id,
acknowledged_at, ip_address, method

statutory_documents
──────────────────────────────────────────────────────────────────
id, ulid, school_id, document_type,     -- registration_certificate|
                                        -- operating_licence|insurance|
                                        -- tax_clearance|health_inspection|
                                        -- fire_certificate|water_quality
reference_number, issuing_authority,
issued_on, expires_on, file_id,
renewal_lead_days, responsible_staff_id,
status                                  -- valid|expiring|expired|renewing

governance_minutes
──────────────────────────────────────────────────────────────────
id, ulid, school_id, body,              -- board|finance_committee|
                                        -- disciplinary|academic_board
meeting_date, attendees JSON,
minutes_file_id, resolutions JSON,
confidentiality VARCHAR(20),            -- open|restricted|confidential
access_role_ids JSON

contracts                              -- ⚠ added during implementation, see below
──────────────────────────────────────────────────────────────────
id, ulid, school_id, counterparty_name, contract_type,
description, starts_on, expires_on, document_file_id,
responsible_staff_id, status
```

**Correction made during CMP-04 implementation**: this module's own scope line names "contract register" but the abridged data model above never defined a table for it, and no business rule references one. Added minimally above — general school contracts (vendors, service providers, leases), distinct from `staff_contracts` (Book C PPL-04, employment contracts).

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-CMP-04-001` | Policies are versioned. Acknowledgement records the version acknowledged. |
| `BR-CMP-04-002` | A new version requires re-acknowledgement from every configured audience. |
| `BR-CMP-04-003` | Acknowledgement tracking reports who has and has not read each policy version. |
| `BR-CMP-04-004` | Policies past their review date alert the owner and the head. |
| `BR-CMP-04-005` | Statutory documents alert on their renewal lead time; expiry alerts the head daily. |
| `BR-CMP-04-006` | An expired operating licence, insurance or fire certificate raises a critical alert. |
| `BR-CMP-04-007` | Confidential minutes are restricted to named roles, and access is logged. |
| `BR-CMP-04-008` | The register consolidates incidents across health, discipline, security, transport and data protection for board reporting, with safeguarding excluded. |

```gherkin
AC-CMP-04-001
  Given a child protection policy is updated to version 4
  Then all staff must acknowledge the new version
  And prior acknowledgements of version 3 remain on record

AC-CMP-04-002
  Given the school's fire certificate expires in 14 days
  Then the responsible staff member and head are alerted

AC-CMP-04-003
  Given the consolidated incident register is generated for the board
  Then it includes health, discipline, security, transport and data protection
  And excludes safeguarding cases
```

---

## Part 3 — Book H3 Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **H3-1** | `PPL-05` statutory configuration, pay structures 🇿🇼 | **Confirmation banner blocks runs on unconfirmed config** |
| **H3-2** | `PPL-05` calculation engine | AIDS Levy on PAYE, NSSA before PAYE, per-currency bands |
| **H3-3** | `PPL-05` run, preview, approval, posting | No posting without approval |
| **H3-4** | `PPL-05` payslips, bank file, loans, fee offset | Staff-child offset credits `FIN-02` |
| **H3-5** | `PPL-05` statutory returns, ITF16 🇿🇼 | Due dates computed; ITF16 reconciles to P2s |
| **H3-6** | `FIN-13` device, certificate, fiscal day 🇿🇼 | Local and FDMS status tracked separately |
| **H3-7** | `FIN-13` routing rules, submission, counters | Per-currency counters correct |
| **H3-8** | `FIN-13` offline queue, retry, Z-reports ⭐ | **Receipting never blocked. `AC-FIN-13-001` green.** |
| **H3-9** | `FIN-13` retro-fit across `FIN-04`, `OPS-03`, `OPS-05` | Every commercial receipt path routed |
| **H3-10** | `FIN-14` wallet, controls, POS | Liability accounting; controls server-enforced |
| **H3-11** | `FIN-14` offline POS, term-end, reconciliation | Negative-on-sync handled and notified |
| **H3-12** | `FIN-12` statements, point-in-time ⭐ | Historical reproducibility proven |
| **H3-13** | `FIN-12` close checklist, close pack | Blocking failures cannot be overridden |
| **H3-14** | `CMP-01` ZIMSEC 🇿🇼 | Book E interface closed; fee reconciliation blocks closure |
| **H3-15** | `CMP-02` MoPSE returns 🇿🇼 | Snapshots frozen at generation |
| **H3-16** | `CMP-03` consent, retention, requests, breaches | Retro-fit across all modules; registry check passes |
| **H3-17** | `CMP-04` policies, documents, minutes | Acknowledgement tracking live |

---

## Part 4 — Book H3 Acceptance Gate

### 🇿🇼 Statutory correctness

- [ ] No tax rate, band, ceiling or deadline is hard-coded anywhere — verified by code search
- [ ] Unconfirmed statutory configuration blocks payroll runs
- [ ] AIDS Levy computes on PAYE due, not on gross
- [ ] NSSA POBS computes on capped insurable earnings (default 9%, split 4.5/4.5, USD 700 ceiling) and reduces the PAYE base
- [ ] NSSA APWCS computes uncapped, employer-only, at the school's own gazetted rate — never against the POBS ceiling, never with an employee deduction
- [ ] USD and ZiG earners use different band tables in the same run
- [ ] Monthly returns date to the 10th; ITF16 to 31 January
- [ ] ITF16 reconciles to the twelve monthly P2 returns; mismatch blocks preparation
- [ ] Historical payslips regenerate under the rates in force at their pay date

### Fiscalisation

- [ ] **Receipting succeeds with FDMS unreachable**, across every commercial receipt path
- [ ] Offline queue drains in counter order on reconnect
- [ ] Per-currency counters maintained separately alongside a global counter
- [ ] Local and FDMS fiscal day status tracked as separate columns
- [ ] Tuition not fiscalised; tuckshop, uniform, hire and farm sales fiscalised
- [ ] Voided receipts raise credit notes referencing the original
- [ ] Unfiscalised receipts beyond the window block period close
- [ ] Sandbox devices cannot fiscalise production receipts

### Wallet

- [ ] Top-ups credit a liability account; no income recognised
- [ ] Spending controls enforced server-side, including offline against cached values
- [ ] Term-end policy offers carry forward, refund or fee transfer — **never income**
- [ ] Wallet liability reconciles nightly to the sum of balances

### Reporting and close

- [ ] Any historical statement regenerates identically
- [ ] Point-in-time reporting filters on `posted_at` and itemises reconciling adjustments
- [ ] Prior-period adjustments appear on their own line
- [ ] Blocking close checks cannot be overridden; warnings require a written reason
- [ ] A period cannot lock without a clean checklist run
- [ ] The close pack is signed and contains statements, results, acknowledgements and authoriser

### Compliance

- [ ] ZIMSEC candidate data derives from `ACA-07`; nothing re-keyed
- [ ] Entry fee collection reconciles to remittance; shortfall blocks closure
- [ ] MoPSE returns snapshot at generation
- [ ] Every personal-data table is covered by a retention schedule — nightly registry check passes
- [ ] Consent withdrawal takes effect immediately across modules
- [ ] Subject access requests exclude safeguarding and redact third-party data
- [ ] Breaches involving minors escalate automatically

### Quality

- [ ] Coverage ≥ 90% for `PPL-05`, `FIN-12`, `FIN-13`, `FIN-14`
- [ ] Every business rule has a named test referencing its rule ID
- [ ] Tenancy isolation suite passes for every model in this book

---

## Appendix A — Interfaces Closed

| Interface | Owner | Consumer | Status |
|---|---|---|---|
| `ExaminationCandidateSet` | `ACA-07` | `CMP-01` | ✅ closed |
| Fiscalisation routing | `FIN-13` | `FIN-04`, `FIN-14`, `OPS-03`, `OPS-05` | ✅ |
| Withholding remittance schedule | `FIN-08` | `PPL-05` returns calendar | ✅ |
| Staff-child fee offset | `PPL-05` | `FIN-02` | ✅ |
| Close checklist registration | all `FIN`, `CORE-03` | `FIN-12` | ✅ |
| Retention schedules | `CMP-03` | every module | ⚠ partial — see note |

**Every interface opened in Books A through H2 is now closed**, with one caveat: the `PersonalDataTableRegistry`/`CheckRetentionScheduleCoverageAction` machinery (BR-CMP-03-005/006) is fully built and working, but only 5 tables are pre-registered as a documented starting set (`students`, `staff`, `guardians`, `zimsec_candidates`, `consents`) — not every one of this codebase's ~17 other modules' own personal-data tables. Retrofitting registration into every module's own `ServiceProvider::boot()` (the way `TenantModelRegistry` genuinely is retrofitted everywhere) is real, valuable follow-up work, flagged separately rather than fabricated here. The nightly coverage check itself is real and will correctly report every table that hasn't been registered yet, including the ones this pass didn't get to.

---

## Appendix B — Remaining Books

| Book | Domain | Modules |
|---|---|---|
| **I** | Communication & Portals | `COM-01` gateways · `COM-02` automation rules · `COM-03`–`COM-05` portal services · `COM-06` calendar · `COM-07` virtual meetings · `COM-08` feedback |
| **J** | Intelligence & SaaS Control | `INT-01`–`INT-04` reporting, dashboards, early warning, public API · `SAA-01`–`SAA-03` licensing, vendor console, onboarding |

Book I is what parents actually experience. Book J is how you get paid.

---

*End of Volume 2, Book H3.*

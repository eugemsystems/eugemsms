# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book B — Domain D: Financial Core (`FIN-01` → `FIN-06`)

| Field | Value |
|---|---|
| Document | Volume 2, Book B of 10 |
| Covers | General Ledger · Multi-Currency & FX · Fee & Billing Engine · Invoicing & Debtors · Receipting & Till Control · Gateways & Reconciliation |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisite | **Book A complete and its acceptance gate green.** This book assumes the Action pattern, `school_id` scoping, the period engine, the `Money` object, numbering, approvals, and the immutable audit stream all exist and work. |
| Next book | Book C — Domain B People (`PPL-01` → `PPL-04`) |

---

## Part 0 — Read This First

### 0.1 Why this book is different

Every other module in the platform can be rebuilt if it is wrong. A school will forgive a clumsy timetable screen. It will not forgive a fee balance that does not match what the bursar counted, and it will never forgive a term's takings going missing.

This book carries three consequences of that:

1. **Coverage floor is 90%**, not 80%. Every Action in this book has a unit test. Every business rule has a named test.
2. **No shortcuts are negotiable.** If a sprint is running late, cut scope from a later book. Do not cut a control from this one.
3. **The reviewer for every finance pull request must be a second developer**, not the author, and the review checklist includes the Financial Integrity Charter from Volume 1 Part 7.

### 0.2 Build order

The numeric order is not the build order. Build in this sequence:

```
FIN-01  General Ledger              ← nothing financial exists until this does
   ↓
FIN-06  Multi-Currency & FX         ← the GL needs currency semantics before it takes real data
   ↓
FIN-02  Fee Structure & Billing     ← decides what is owed
   ↓
FIN-03  Invoicing & Debtors         ← issues and tracks the bill
   ↓
FIN-04  Receipting & Till Control   ← takes the money
   ↓
FIN-05  Gateways & Reconciliation   ← proves the money arrived
```

Each stage is demonstrable on its own. After `FIN-04` you can run a school's entire fee cycle by hand at the counter. `FIN-05` adds the online channel and the proof.

### 0.3 The five invariants

Everything in this book exists to keep these true. Each is asserted by an automated job, not merely by convention.

| # | Invariant |
|---|---|
| **I-1** | For every school, every currency, every point in time: `Σ debits = Σ credits`. |
| **I-2** | A learner's balance equals the sum of their ledger lines. There is no other definition of a balance anywhere in the system. |
| **I-3** | `Σ(closing balances of term N) = Σ(opening balances of term N+1)`, with FX movement accounted separately. |
| **I-4** | Every cent that enters the school is on the ledger from the moment it arrives, even when nobody yet knows whose it is. |
| **I-5** | Every historical financial statement regenerates identically, forever. |

---

# FIN-01 · Chart of Accounts & General Ledger ⭐

> The single source of financial truth. Build this first, test it hardest, and let nothing else write money to the database by any other route.

### 1. Scope

**In scope.** Account types and the chart of accounts, cost centres, the journal engine, posting rules, balance derivation, the trial balance, reversal mechanics, append-only enforcement, opening balances, period-scoped querying, drill-down.

**Out of scope.** What generates a journal. Fees, receipts, payroll, depreciation and stock issues all post *through* this module but are specified in their own modules. This module knows nothing about learners.

### 2. Data model

```sql
account_types                         -- seeded, not user-editable
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
code                VARCHAR(20)  NOT NULL UNIQUE  -- 'ASSET','LIABILITY','EQUITY',
                                                  -- 'INCOME','EXPENSE'
name                VARCHAR(60)  NOT NULL
normal_balance      CHAR(2)      NOT NULL         -- 'DR' | 'CR'
statement           VARCHAR(20)  NOT NULL         -- balance_sheet | income_statement
sort_order          SMALLINT     NOT NULL

accounts                              -- the chart of accounts
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
parent_id           BIGINT       NULL FK → accounts.id
account_type_id     BIGINT       FK
code                VARCHAR(20)  NOT NULL          -- '1200'
name                VARCHAR(150) NOT NULL          -- 'Fee Debtors Control'
description         VARCHAR(255) NULL
is_postable         TINYINT(1)   NOT NULL DEFAULT 1  -- 0 = header/rollup only
is_control_account  TINYINT(1)   NOT NULL DEFAULT 0  -- reconciles to a subledger
subledger_type      VARCHAR(30)  NULL   -- learner|guardian|supplier|staff|null
is_system           TINYINT(1)   NOT NULL DEFAULT 0  -- cannot be deleted or recoded
system_key          VARCHAR(60)  NULL UNIQUE_PER_SCHOOL
                                 -- 'suspense','fx_realised','fx_unrealised','rounding',
                                 -- 'prior_period_adjustment','retained_earnings',
                                 -- 'fee_debtors','bad_debt','fee_discount_contra'
currency            CHAR(3)      NULL   -- null = multi-currency; set = restricted (bank accounts)
requires_cost_centre TINYINT(1)  NOT NULL DEFAULT 0
is_active           TINYINT(1)   NOT NULL DEFAULT 1
opened_on           DATE         NULL
closed_on           DATE         NULL
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, code)
  UNIQUE (school_id, system_key)     -- where system_key NOT NULL
  INDEX  (school_id, account_type_id, is_active)
  INDEX  (school_id, parent_id)

cost_centres
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
parent_id           BIGINT       NULL FK → cost_centres.id
code                VARCHAR(20)  NOT NULL   -- 'BRD','FARM','TRANS','PRIM','SEC','TUCK'
name                VARCHAR(120) NOT NULL
section_id          BIGINT       NULL FK → school_sections.id
manager_user_id     BIGINT       NULL FK → users.id
is_profit_centre    TINYINT(1)   NOT NULL DEFAULT 0   -- farm, transport, tuckshop
is_active           TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

journals                              -- header. APPEND-ONLY.
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       FK
term_id             BIGINT       NOT NULL FK
journal_number      VARCHAR(80)  NOT NULL      -- from CORE-06
journal_type        VARCHAR(40)  NOT NULL      -- see catalogue §4
narration           VARCHAR(500) NOT NULL
reference           VARCHAR(120) NULL          -- external reference
source_type         VARCHAR(255) NULL          -- polymorphic origin
source_id           BIGINT       NULL
effective_at        DATE         NOT NULL      -- the date it APPLIES to
posted_at           TIMESTAMP(6) NOT NULL      -- the date it was ENTERED
is_prior_period_adjustment TINYINT(1) NOT NULL DEFAULT 0
is_reversal         TINYINT(1)   NOT NULL DEFAULT 0
reverses_journal_id BIGINT       NULL FK → journals.id
reversed_by_journal_id BIGINT    NULL FK → journals.id
reversal_reason     TEXT         NULL
status              VARCHAR(20)  NOT NULL      -- draft|posted|reversed
posted_by           BIGINT       NOT NULL FK → users.id
approved_by         BIGINT       NULL FK → users.id   -- manual journals
batch_uuid          CHAR(36)     NULL          -- groups a billing run
created_at
  UNIQUE (school_id, journal_number)
  INDEX  (school_id, term_id, effective_at)
  INDEX  (school_id, journal_type, effective_at)
  INDEX  (source_type, source_id)
  INDEX  (school_id, batch_uuid)
  -- DB grants: INSERT, SELECT. No UPDATE except the two narrow columns below.
  -- Permitted UPDATE columns: status, reversed_by_journal_id  (enforced by trigger)

journal_lines                         -- APPEND-ONLY, no exceptions
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
journal_id          BIGINT       FK INDEX
line_number         SMALLINT     NOT NULL
account_id          BIGINT       FK INDEX
cost_centre_id      BIGINT       NULL FK
direction           CHAR(2)      NOT NULL      -- 'DR' | 'CR'
amount_minor        BIGINT       NOT NULL      -- always positive; direction carries sign
currency            CHAR(3)      NOT NULL
base_amount_minor   BIGINT       NOT NULL      -- converted to school base currency
base_currency       CHAR(3)      NOT NULL
exchange_rate       DECIMAL(20,10) NOT NULL DEFAULT 1
exchange_rate_id    BIGINT       NULL FK → exchange_rates.id
subledger_type      VARCHAR(30)  NULL          -- learner|guardian|supplier|staff
subledger_id        BIGINT       NULL
narration           VARCHAR(255) NULL          -- line-level detail
effective_at        DATE         NOT NULL      -- denormalised from header for query speed
term_id             BIGINT       NOT NULL      -- denormalised
created_at
  INDEX (school_id, account_id, effective_at)
  INDEX (school_id, subledger_type, subledger_id, effective_at)
  INDEX (school_id, term_id, account_id)
  INDEX (journal_id, line_number)
  -- DB grants: INSERT, SELECT only. No UPDATE. No DELETE.

account_balances                      -- CACHE ONLY. Never authoritative.
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
account_id          BIGINT       FK
term_id             BIGINT       FK
currency            CHAR(3)      NOT NULL
opening_minor       BIGINT       NOT NULL DEFAULT 0
debit_minor         BIGINT       NOT NULL DEFAULT 0
credit_minor        BIGINT       NOT NULL DEFAULT 0
closing_minor       BIGINT       NOT NULL DEFAULT 0
line_count          INT          NOT NULL DEFAULT 0
last_line_id        BIGINT       NULL          -- watermark for incremental rebuild
rebuilt_at          TIMESTAMP    NOT NULL
  UNIQUE (school_id, account_id, term_id, currency)

subledger_balances                    -- CACHE ONLY. Never authoritative.
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
subledger_type      VARCHAR(30)  NOT NULL
subledger_id        BIGINT       NOT NULL
account_id          BIGINT       FK
term_id             BIGINT       FK
currency            CHAR(3)      NOT NULL
opening_minor       BIGINT       NOT NULL DEFAULT 0
debit_minor         BIGINT       NOT NULL DEFAULT 0
credit_minor        BIGINT       NOT NULL DEFAULT 0
closing_minor       BIGINT       NOT NULL DEFAULT 0
rebuilt_at          TIMESTAMP    NOT NULL
  UNIQUE (school_id, subledger_type, subledger_id, account_id, term_id, currency)
  INDEX  (school_id, subledger_type, subledger_id)

posting_rules                         -- maps events to accounts, configurable
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
event_key           VARCHAR(80)  NOT NULL   -- 'fee.tuition.billed','receipt.cash.received'
debit_account_id    BIGINT       NULL FK    -- null = resolved dynamically
credit_account_id   BIGINT       NULL FK
debit_resolver      VARCHAR(80)  NULL       -- 'learner_debtors_control','component_income'
credit_resolver     VARCHAR(80)  NULL
cost_centre_id      BIGINT       NULL FK
is_active           TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, event_key)
```

### 3. Why balances are cached but never authoritative

`account_balances` and `subledger_balances` exist purely for speed. A school with 1,400 learners across three terms and five years accumulates millions of journal lines; recomputing a debtors list from raw lines on every page load is not viable.

**The rules that keep the cache honest:**

- The cache is **only ever** rebuilt from `journal_lines`. Nothing writes to it directly.
- A nightly job recomputes every cached balance from source and asserts equality. Any discrepancy rebuilds the cache and raises a critical alert — a mismatch means a bug, not a rounding quirk.
- Any report that a school will act on financially (statement, trial balance, income statement, aged debt) may be **served** from cache, but a "verify against ledger" action on each of those screens recomputes from source on demand and shows both figures.
- Period close recomputes from source before snapshotting. A snapshot is never taken from cache.

### 4. Journal type catalogue

| Type | Raised by | Typical posting |
|---|---|---|
| `OPENING_BALANCE` | `CORE-11` import | Dr Debtors / Cr Opening Equity |
| `FEE_BILLING` | `FIN-02` | Dr Fee Debtors / Cr Fee Income (per component) |
| `FEE_DISCOUNT` | `FIN-07` | Dr Discount Contra (income) / Cr Fee Debtors |
| `RECEIPT` | `FIN-04` | Dr Cash/Bank / Cr Fee Debtors |
| `RECEIPT_SUSPENSE` | `FIN-04` | Dr Bank / Cr Suspense |
| `SUSPENSE_ALLOCATION` | `FIN-04` | Dr Suspense / Cr Fee Debtors |
| `CREDIT_NOTE` | `FIN-03` | Dr Fee Income / Cr Fee Debtors |
| `WRITE_OFF` | `FIN-03` | Dr Bad Debt Expense / Cr Fee Debtors |
| `REFUND` | `FIN-03` | Dr Fee Debtors / Cr Cash/Bank |
| `FX_REALISED` | `FIN-06` | Dr or Cr Realised FX / contra Debtors or Bank |
| `FX_REVALUATION` | `FIN-06` | Dr or Cr Unrealised FX / contra the revalued account |
| `BALANCE_BROUGHT_FORWARD` | `CORE-03` roll-over | Dr Debtors / Cr Opening Balance Control |
| `SUPPLIER_INVOICE` | `FIN-08` | Dr Expense/Asset / Cr Creditors |
| `SUPPLIER_PAYMENT` | `FIN-08` | Dr Creditors / Cr Bank |
| `STOCK_ISSUE` | `FIN-09` | Dr Department Expense / Cr Inventory |
| `DEPRECIATION` | `FIN-10` | Dr Depreciation Expense / Cr Accumulated Depreciation |
| `PAYROLL` | `PPL-05` | Dr Salaries / Cr Net Pay, PAYE, NSSA, ZIMDEF, NEC |
| `GATEWAY_FEE` | `FIN-05` | Dr Bank Charges / Cr Bank |
| `MANUAL` | Bursar | Anything, with approval |
| `REVERSAL` | Any | Mirror of the reversed journal |
| `ROUNDING` | System | Dr or Cr Rounding Differences |

Every mapping above is a row in `posting_rules` and is editable by a school with a different chart. The code refers to `system_key` and resolvers, never to hard-coded account codes.

### 5. Domain actions

| Action | Input | Output | Notes |
|---|---|---|---|
| `ACT-PostJournal` | `PostJournalData` | `Journal` | **The single write path for all money.** |
| `ACT-PostJournalBatch` | `PostJournalBatchData` | `BatchResult` | Chunked, one `batch_uuid`, atomic per chunk |
| `ACT-ReverseJournal` | `ReverseJournalData` | `Journal` | Creates the mirror; links both ways |
| `ACT-CreateManualJournal` | `ManualJournalData` | `Journal` | Draft → approval → post |
| `ACT-ApproveManualJournal` | `ApproveJournalData` | `Journal` | Via `CORE-07` |
| `ACT-CreateAccount` / `ACT-UpdateAccount` / `ACT-DeactivateAccount` | DTOs | `Account` | |
| `ACT-CreateCostCentre` | `CostCentreData` | `CostCentre` | |
| `ACT-RebuildAccountBalances` | `RebuildData` | `RebuildResult` | Full or incremental from watermark |
| `ACT-CalculateAccountBalance` | `BalanceQuery` | `Money` | **From source lines.** Authoritative. |
| `ACT-CalculateSubledgerBalance` | `SubledgerQuery` | `Money` | From source lines |
| `ACT-GenerateTrialBalance` | `TrialBalanceQuery` | `TrialBalance` | Per currency and consolidated |
| `ACT-AssertLedgerBalanced` | `school`, `?term` | `BalanceAssertion` | Used by close checklist and nightly job |
| `ACT-ImportOpeningBalances` | `OpeningBalanceData` | `ImportResult` | Posts real journals; rejects if unbalanced |

### 6. The posting engine ⭐

```php
final class PostJournalAction extends Action
{
    public function execute(PostJournalData $data): Journal
    {
        // 1. Context and authorisation
        $school = $data->school;
        $term   = $data->term;
        $this->authorise($data);

        // 2. PERIOD GUARD — the gate everything passes through
        PeriodGuard::assertFinancialWritable($term, $data->overrideToken);

        // 3. Structural validation
        $this->assertAtLeastTwoLines($data->lines);
        $this->assertAllAccountsPostable($data->lines);
        $this->assertAllAccountsBelongToSchool($school, $data->lines);
        $this->assertCostCentresPresentWhereRequired($data->lines);

        // 4. BALANCE ASSERTION — per currency, before anything is written
        foreach ($this->groupByCurrency($data->lines) as $currency => $lines) {
            $dr = $this->sum($lines, 'DR');
            $cr = $this->sum($lines, 'CR');
            if ($dr !== $cr) {
                throw new UnbalancedJournalException($currency, $dr, $cr);
            }
        }

        // 5. Base-currency conversion (FIN-06). Every line gets a rate.
        $lines = $this->fx->convertLines($data->lines, $school->base_currency, $data->effectiveAt);

        // 6. Base-currency residual → rounding account, never dropped
        $residual = $this->baseResidual($lines);
        if ($residual !== 0) {
            $lines[] = $this->roundingLine($school, $residual);
        }

        // 7. Allocate the journal number (CORE-06, same transaction)
        $number = $this->numbering->allocate('journal', $school);

        // 8. Persist header + lines
        $journal = Journal::create([...]);
        $journal->lines()->createMany($lines);

        // 9. Financial audit — SYNCHRONOUS, same transaction
        $this->financialAudit->record('journal_posted', $journal);

        // 10. Cache update (incremental)
        $this->balanceCache->applyIncrementally($journal);

        // 11. Events
        event(new JournalPosted(...));

        return $journal;
    }
}
```

Steps 4, 6 and 9 are the ones people cut when they are in a hurry. They are the ones that make the product trustworthy.

### 7. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-01-001` | **Every** movement of money in the system posts a journal through `ACT-PostJournal`. There is no other write path. Static analysis fails the build on any direct insert to `journals` or `journal_lines`. |
| `BR-FIN-01-002` | A journal has at least two lines. |
| `BR-FIN-01-003` | Debits equal credits **within each currency** of the journal. A journal mixing USD and ZWG must balance in both independently. |
| `BR-FIN-01-004` | `amount_minor` is always positive. `direction` carries the sign. A negative amount is a defect, not a credit. |
| `BR-FIN-01-005` | Every line converts to the school base currency, storing the rate and `exchange_rate_id`. Base-currency debits and credits must also balance; any residual posts to the Rounding account. **The residual is never discarded.** |
| `BR-FIN-01-006` | Lines may only post to accounts where `is_postable = 1`. Header accounts are rollups. |
| `BR-FIN-01-007` | Lines may only reference accounts belonging to the journal's school. Cross-school posting is impossible and asserted. |
| `BR-FIN-01-008` | Accounts with `requires_cost_centre = 1` reject lines without one. |
| `BR-FIN-01-009` | Posting to a control account requires a `subledger_type` and `subledger_id`. A debtors control line without a learner is rejected. |
| `BR-FIN-01-010` | Accounts with a fixed `currency` reject lines in any other currency. A USD bank account never carries a ZWG line. |
| `BR-FIN-01-011` | `journal_lines` is append-only: `UPDATE` and `DELETE` revoked at the database level. |
| `BR-FIN-01-012` | `journals` permits `UPDATE` on `status` and `reversed_by_journal_id` only, enforced by a database trigger. Every other column is immutable. |
| `BR-FIN-01-013` | **Correction is by reversal only.** `ACT-ReverseJournal` creates a mirrored journal with `is_reversal = 1`, links both directions, and requires a reason of at least 15 characters. |
| `BR-FIN-01-014` | A reversal's `effective_at` defaults to the original's. Reversing into a different period requires `finance.journal.reverse_cross_period` and flags as a prior-period adjustment. |
| `BR-FIN-01-015` | A journal already reversed cannot be reversed again. |
| `BR-FIN-01-016` | Posting into a `SOFT_CLOSED` period requires an approved override token from `CORE-07`. Posting into `LOCKED` or `ARCHIVED` is refused outright. |
| `BR-FIN-01-017` | Any journal whose `effective_at` falls in a period that was closed at the time of posting is flagged `is_prior_period_adjustment = 1` and reports on its own line. |
| `BR-FIN-01-018` | Manual journals are created as `draft`, require approval through `CORE-07`, and only post on approval. A manual journal is never posted by its author alone. |
| `BR-FIN-01-019` | System accounts (`is_system = 1`) cannot be deleted, recoded, or have their `system_key` changed. |
| `BR-FIN-01-020` | An account cannot be deactivated while its balance is non-zero or while any active subledger references it. |
| `BR-FIN-01-021` | An account cannot be deleted once any line references it. Deactivation only. |
| `BR-FIN-01-022` | Balance derivation is always `Σ(DR) − Σ(CR)` for debit-normal types, and the inverse for credit-normal types, over lines with `effective_at ≤ query date`. |
| `BR-FIN-01-023` | A balance query always takes a currency. "The balance" without a currency is meaningless in this system and the API rejects it. |
| `BR-FIN-01-024` | `account_balances` and `subledger_balances` are caches. Nothing writes to them except the rebuild service. A nightly job verifies every cached figure against source. |
| `BR-FIN-01-025` | Opening balance import posts `OPENING_BALANCE` journals. The whole import is atomic and is rejected if the resulting trial balance does not balance. |
| `BR-FIN-01-026` | The trial balance is computed per currency and consolidated in base currency. Both are shown. |
| `BR-FIN-01-027` | Every report figure drills through to the journals and then to the source document that raised them. A number a bursar cannot trace is a number they will not trust. |
| `BR-FIN-01-028` | `posted_at` is set by the server clock, never accepted from the client. `effective_at` may be supplied but cannot be in the future beyond `finance.max_future_dating_days` (default 0). |

### 8. Screens

| Screen | Component | Permission |
|---|---|---|
| Chart of accounts | `Finance\Accounts\Tree` | `finance.account.view` — hierarchical, with live balances per currency |
| Account editor | `Finance\Accounts\Editor` | `finance.account.manage` |
| Account ledger | `Finance\Accounts\Ledger` | `finance.account.view` — every line, running balance, filters, export |
| Cost centres | `Finance\CostCentres\Index` | `finance.cost_centre.manage` |
| Journal browser | `Finance\Journals\Index` | `finance.journal.view` — filter by type, date, term, source, user, batch |
| Journal detail | `Finance\Journals\Show` | `finance.journal.view` — lines, source document link, reversal link, audit entry |
| Manual journal entry | `Finance\Journals\Create` | `finance.journal.create_manual` — live balance indicator, cannot submit unbalanced |
| Journal reversal | `Finance\Journals\Reverse` | `finance.journal.reverse` — reason mandatory, preview of the mirror |
| Trial balance | `Finance\Reports\TrialBalance` | `finance.report.trial_balance` — per currency, as-at date, verify-from-source button |
| Posting rules | `Finance\PostingRules\Index` | `finance.posting_rule.manage` |
| Balance verification | `Finance\Integrity\Balances` | `finance.integrity.view` — cache vs source, per account, with rebuild |

**Manual journal entry screen behaviour.** The submit button is disabled until debits equal credits in every currency present. The running difference is displayed prominently. This is a small detail that saves an enormous amount of support time.

### 9. API endpoints

The GL is deliberately **not** exposed to portal clients. Parents and teachers never see journals. The only API surface is internal reporting for the executive dashboard.

```
GET /api/v1/finance/accounts                      admin token only
GET /api/v1/finance/accounts/{ulid}/balance       ?currency=&as_at=
GET /api/v1/finance/trial-balance                 ?currency=&as_at=&term=
```

### 10. Permissions

```
finance.account.view              finance.account.manage
finance.cost_centre.view          finance.cost_centre.manage
finance.journal.view              finance.journal.create_manual
finance.journal.approve           finance.journal.reverse ⚠
finance.journal.reverse_cross_period ⚠⚠
finance.posting_rule.view         finance.posting_rule.manage ⚠
finance.report.trial_balance      finance.integrity.view
finance.opening_balance.import ⚠⚠
```

### 11. Settings

| Key | Type | Default |
|---|---|---|
| `finance.max_future_dating_days` | int | `0` |
| `finance.require_approval_for_manual_journal` | bool | `true` (**locked on all tiers**) |
| `finance.manual_journal_approval_threshold_minor` | int | `0` (all manual journals need approval) |
| `finance.balance_cache_rebuild_hour` | int | `2` |
| `finance.allow_negative_bank_balance` | bool | `true` (overdrafts exist) |
| `finance.rounding_tolerance_minor` | int | `5` (above this, refuse rather than round) |

### 12. Events published

`JournalPosted` · `JournalReversed` · `ManualJournalSubmitted` · `ManualJournalApproved` · `AccountCreated` · `AccountDeactivated` · `TrialBalanceImbalanceDetected` ⚠ · `OpeningBalancesImported`

### 13. Jobs

| Job | Schedule | Notes |
|---|---|---|
| `JOB-RebuildBalanceCache` | Nightly 02:00 | Incremental from watermark; full rebuild weekly |
| `JOB-VerifyBalanceCache` | Nightly 03:00 | Cache vs source; rebuild and alert on any mismatch |
| `JOB-AssertTrialBalance` | Nightly 03:30 | Per school, per currency; critical alert on failure |
| `JOB-DetectOrphanSubledgerLines` | Weekly | Control account lines whose subledger record no longer exists |

### 14. Acceptance criteria

```gherkin
AC-FIN-01-001
  Given a journal with lines Dr 500 USD and Cr 400 USD
  When it is posted
  Then it is rejected with UnbalancedJournalException
  And no journal or line rows are created

AC-FIN-01-002
  Given a journal with Dr 100 USD, Cr 100 USD, Dr 5000 ZWG, Cr 4000 ZWG
  When it is posted
  Then it is rejected because ZWG does not balance
  Even though USD does

AC-FIN-01-003
  Given the application database user
  When it attempts UPDATE or DELETE on journal_lines
  Then the database refuses the statement

AC-FIN-01-004
  Given a posted receipt journal
  When it is reversed with a reason
  Then a new journal exists with mirrored lines
  And both journals link to each other
  And the original journal's rows are byte-identical to before

AC-FIN-01-005
  Given 2025 Term 3 financial period is LOCKED
  When any module attempts to post a journal effective in that term
  Then PeriodLockedException is thrown
  And nothing is written

AC-FIN-01-006
  Given a learner has journal lines totalling Dr 1,075.00 and Cr 400.00 USD
  When their subledger balance is calculated from source
  Then it returns 675.00 USD
  And the cached balance matches

AC-FIN-01-007
  Given a cached balance is manually corrupted in the database
  When the nightly verification job runs
  Then the discrepancy is detected
  And the cache is rebuilt from source
  And a critical alert is raised

AC-FIN-01-008
  Given a journal posting a ZWG amount converted to a USD base
  When the converted debits and credits differ by 2 cents
  Then a rounding line of 2 cents is posted to the Rounding account
  And the base-currency journal balances exactly

AC-FIN-01-009
  Given an opening balance file whose debits and credits do not agree
  When it is imported
  Then the entire import is rejected
  And no journals exist

AC-FIN-01-010
  Given a bursar creates a manual journal
  When they attempt to post it themselves
  Then it remains in draft awaiting approval by another user
```

---

# FIN-06 · Multi-Currency & FX Engine ⭐ 🇿🇼

> Built second, before any billing exists, because the GL cannot take real Zimbabwean data without it. Currency mishandling is the single largest cause of unexplained variance in Zimbabwean school books, and it is almost always invisible until a term-end reconciliation fails.

### 1. Scope

**In scope.** Currency registry, exchange rate tables with effective dating and source attribution, conversion service, realised and unrealised FX posting, period-end revaluation, rate change simulation, conversion audit.

**Out of scope.** Which currency a fee component is billed in (`FIN-02`). Gateway settlement currency (`FIN-05`).

### 2. Data model

```sql
currencies                            -- seeded
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
code                CHAR(3)      NOT NULL UNIQUE  -- 'USD','ZWG'
name                VARCHAR(60)  NOT NULL
symbol              VARCHAR(10)  NOT NULL
minor_unit_digits   TINYINT      NOT NULL DEFAULT 2
display_format      VARCHAR(40)  NOT NULL  -- '{symbol}{amount}'
is_active           TINYINT(1)   NOT NULL DEFAULT 1
sort_order          SMALLINT

school_currencies                     -- which currencies a school transacts in
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
currency            CHAR(3)      NOT NULL
is_base             TINYINT(1)   NOT NULL DEFAULT 0
is_accepted_for_payment TINYINT(1) NOT NULL DEFAULT 1
rounding_increment_minor INT     NOT NULL DEFAULT 1   -- ZWG cash rounding
is_active           TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, currency)

exchange_rate_sources
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       NULL FK   -- null = system source
key                 VARCHAR(40)  NOT NULL  -- 'rbz_interbank','school_rate','gateway'
name                VARCHAR(120) NOT NULL
is_automatic        TINYINT(1)   NOT NULL DEFAULT 0
endpoint_url        VARCHAR(255) NULL
requires_approval   TINYINT(1)   NOT NULL DEFAULT 1
priority            SMALLINT     NOT NULL DEFAULT 0
is_active           TINYINT(1)   NOT NULL DEFAULT 1

exchange_rates                        -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
source_id           BIGINT       FK
from_currency       CHAR(3)      NOT NULL
to_currency         CHAR(3)      NOT NULL
rate                DECIMAL(20,10) NOT NULL
inverse_rate        DECIMAL(20,10) NOT NULL
effective_from      TIMESTAMP    NOT NULL
effective_to        TIMESTAMP    NULL      -- null = current
status              VARCHAR(20)  NOT NULL  -- pending|active|superseded|rejected
captured_by         BIGINT       FK → users.id
approved_by         BIGINT       NULL FK
approved_at         TIMESTAMP    NULL
notes               VARCHAR(255) NULL
created_at
  INDEX (school_id, from_currency, to_currency, effective_from)
  INDEX (school_id, status)
  -- No UPDATE except: effective_to, status, approved_by, approved_at

currency_conversions                  -- audit of every conversion performed
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
journal_line_id     BIGINT       NULL FK
context_type        VARCHAR(60)  NOT NULL  -- journal_line|invoice|receipt|revaluation
context_id          BIGINT       NULL
from_currency       CHAR(3)      NOT NULL
from_amount_minor   BIGINT       NOT NULL
to_currency         CHAR(3)      NOT NULL
to_amount_minor     BIGINT       NOT NULL
exchange_rate_id    BIGINT       FK
rate_used           DECIMAL(20,10) NOT NULL
rate_effective_from TIMESTAMP    NOT NULL
converted_at        TIMESTAMP    NOT NULL
  INDEX (school_id, converted_at)
  INDEX (context_type, context_id)

fx_revaluations
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
term_id             BIGINT       FK
revaluation_date    DATE         NOT NULL
closing_rate_id     BIGINT       FK → exchange_rates.id
accounts_revalued   JSON         NOT NULL   -- account ids and pre/post values
gain_minor          BIGINT       NOT NULL DEFAULT 0
loss_minor          BIGINT       NOT NULL DEFAULT 0
base_currency       CHAR(3)      NOT NULL
journal_id          BIGINT       NULL FK
status              VARCHAR(20)  NOT NULL  -- draft|posted|reversed
performed_by        BIGINT       FK → users.id
performed_at        TIMESTAMP
  UNIQUE (school_id, term_id, revaluation_date)
```

### 3. The two FX events that matter

**Realised FX gain or loss** — arises when a payment settles an obligation at a rate different from the rate at which the obligation was recorded.

```
Invoice raised 10 Jan:  USD 450.00 tuition
                        Learner debtors  Dr 450.00 USD  (base USD, rate 1.0)

Payment received 3 Feb: ZWG 15,300.00
                        Rate on 3 Feb:   1 USD = 34.00 ZWG
                        ZWG 15,300 ÷ 34  = USD 450.00   → settles exactly

BUT if the school's ZWG receipt was recorded against a USD invoice
using a school rate of 33.00 while the bank credited at 34.00:

   Dr Bank (ZWG)              15,300.00 ZWG  → base USD 450.00 @ 34.00
   Cr Learner Debtors (USD)      463.64 USD  (15,300 ÷ 33.00)
   Dr Realised FX Loss            13.64 USD  ← the difference, posted, not absorbed
```

Without that third line the journal does not balance, and a system that "handles" the mismatch by quietly adjusting the debtor balance is exactly how money goes missing.

**Unrealised FX gain or loss** — arises at period close when open foreign-currency balances are restated at the closing rate.

```
Term close 30 Apr:
  Open ZWG-denominated debtor balances:  ZWG 2,400,000
  Recorded at average rate 33.00 → base USD 72,727.27
  Closing rate 30 Apr          34.50 → base USD 69,565.22
  Difference                                USD  3,162.05 loss

   Dr Unrealised FX Loss     3,162.05 USD
   Cr Learner Debtors Control 3,162.05 USD (base restatement)
```

**This is the step most systems skip.** Skipping it means a school's ZWG receivables silently overstate the balance sheet, term after term, until an auditor finds it.

### 4. Rate resolution

```php
public function rateFor(
    Currency $from,
    Currency $to,
    CarbonImmutable $at,
    ?int $schoolId = null,
): ExchangeRate {
    if ($from === $to) return ExchangeRate::identity();

    $rate = ExchangeRate::query()
        ->where('school_id', $schoolId ?? SchoolContext::currentId())
        ->where('from_currency', $from->value)
        ->where('to_currency', $to->value)
        ->where('status', 'active')
        ->where('effective_from', '<=', $at)
        ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at))
        ->orderByDesc('effective_from')
        ->first();

    // Try the inverse pair before giving up
    $rate ??= $this->inverseOf($to, $from, $at, $schoolId);

    return $rate ?? throw new NoExchangeRateException($from, $to, $at);
}
```

**There is no fallback rate.** If no rate exists for the date, the operation fails loudly. Guessing a rate is how a school's books become fiction.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-06-001` | Every school has exactly one base currency. It is immutable once any journal exists. |
| `BR-FIN-06-002` | Every monetary amount everywhere in the system carries a currency. A currency-less amount cannot be constructed. |
| `BR-FIN-06-003` | Every conversion records the rate, the rate source, the rate's effective date, and both amounts, in `currency_conversions`. Every conversion is therefore re-derivable and challengeable. |
| `BR-FIN-06-004` | `exchange_rates` is append-only. Correcting a rate supersedes it (sets `effective_to`, `status = superseded`) and inserts a new row. Rates are never edited in place. |
| `BR-FIN-06-005` | Rates from sources with `requires_approval = 1` are created `pending` and take effect only on approval through `CORE-07`. |
| `BR-FIN-06-006` | Rate periods for a given pair and source may not overlap. Inserting an overlapping rate automatically closes the prior one at the new one's `effective_from`. |
| `BR-FIN-06-007` | If no active rate exists for the required date, the operation throws `NoExchangeRateException`. **There is no default, no last-known-rate fallback, and no 1:1 assumption.** |
| `BR-FIN-06-008` | A transaction uses the rate effective on its `effective_at` date, not the rate at the moment of entry. A receipt backdated to last week uses last week's rate. |
| `BR-FIN-06-009` | Realised FX difference on settlement is posted to the Realised FX account in the same journal as the settlement. The journal always balances. |
| `BR-FIN-06-010` | Period-end revaluation is a mandatory item on the financial close checklist. A period cannot lock without it. |
| `BR-FIN-06-011` | Revaluation covers every account flagged as revaluable — foreign-currency bank accounts, debtors, and creditors. Income and expense accounts are never revalued. |
| `BR-FIN-06-012` | A revaluation is reversible while its period remains open, and is reversed by a full journal reversal, never by deletion. |
| `BR-FIN-06-013` | Rate change simulation shows the effect on debtor balances, creditor balances, and the FX result **before** the rate is approved. |
| `BR-FIN-06-014` | Reports may be produced in transaction currency, base currency, or side by side. Base-currency figures always state the rate basis used. |
| `BR-FIN-06-015` | 🇿🇼 ZWG cash amounts round to `rounding_increment_minor` at the point of cash tender. The rounding difference posts to the Rounding account, never to the learner's balance. |
| `BR-FIN-06-016` | Automatic rate import runs on schedule but never activates a rate without approval where the source requires it. A market rate moving overnight must not silently reprice a school's receivables. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Currency setup | `Finance\Currency\Index` | `finance.currency.manage` |
| Rate table | `Finance\Currency\Rates` | `finance.rate.view` — history per pair, effective ranges, source |
| Rate capture | `Finance\Currency\CaptureRate` | `finance.rate.capture` |
| Rate approval | `Finance\Currency\ApproveRate` | `finance.rate.approve` — with impact simulation shown before approving |
| Impact simulation | `Finance\Currency\Simulate` | `finance.rate.view` — debtors, creditors, FX result at a proposed rate |
| Revaluation | `Finance\Currency\Revaluation` | `finance.fx.revalue` — preview, post, reverse |
| Conversion audit | `Finance\Currency\ConversionLog` | `finance.rate.view` |

### 7. API endpoints

```
GET /api/v1/finance/currencies              → school's active currencies and base
GET /api/v1/finance/exchange-rates/current  → current rates, for portal display
```

The parent portal shows the rate used on their statement. Transparency here prevents a very common category of dispute.

### 8. Acceptance criteria

```gherkin
AC-FIN-06-001
  Given a USD 450.00 invoice
  And a ZWG payment settling it at a rate differing from the invoice rate
  When the receipt is posted
  Then a Realised FX line is included in the same journal
  And the journal balances in both currencies and in base currency

AC-FIN-06-002
  Given no exchange rate exists for USD to ZWG on 2026-03-15
  When a transaction dated 2026-03-15 requires conversion
  Then NoExchangeRateException is thrown
  And no journal is created
  And no fallback rate is used

AC-FIN-06-003
  Given a rate is corrected
  Then the original row still exists with status 'superseded'
  And a new row carries the corrected value
  And no row was updated in place

AC-FIN-06-004
  Given open ZWG debtor balances at term close
  When the financial close checklist runs without a posted revaluation
  Then the checklist fails
  And the period cannot be locked

AC-FIN-06-005
  Given a proposed rate change from 33.00 to 34.50
  When I open the impact simulation
  Then I see the effect on total debtors, total creditors, and the FX result
  Before the rate is approved

AC-FIN-06-006
  Given a receipt backdated to 2026-02-01
  When it is posted on 2026-02-20
  Then the rate effective on 2026-02-01 is used
  Not the rate on 2026-02-20
```

---

# FIN-02 · Fee Structure & Billing Engine ⭐

> The most configuration-dense module in the platform, and the one your full-time versus part-time requirement lives in. Modelled generically: the moment you hard-code two billing modes, a school arrives with a third.

### 1. Scope

**In scope.** Fee component catalogue, billing bases, fee structures and versioning, the applicability rule engine with resolution trace, proration, ad hoc charging, billing runs with preview and approval, recalculation on subject change.

**Out of scope.** Invoice documents (`FIN-03`). Discounts and bursaries (`FIN-07`, though the hooks are here). Payment (`FIN-04`).

### 2. Data model

```sql
fee_components
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
code                VARCHAR(30)  NOT NULL   -- 'TUITION','LEVY','BOARDING','EXAM'
name                VARCHAR(150) NOT NULL
description         VARCHAR(255)
category            VARCHAR(30)  NOT NULL   -- tuition|levy|boarding|transport|
                                            -- activity|examination|material|deposit|other
income_account_id   BIGINT       FK → accounts.id
debtor_account_id   BIGINT       FK → accounts.id
cost_centre_id      BIGINT       NULL FK
default_currency    CHAR(3)      NOT NULL
is_refundable       TINYINT(1)   NOT NULL DEFAULT 0
is_mandatory        TINYINT(1)   NOT NULL DEFAULT 1
is_fiscalisable     TINYINT(1)   NOT NULL DEFAULT 0   -- 🇿🇼 routes to FDMS
tax_category        VARCHAR(20)  NOT NULL DEFAULT 'exempt' -- standard|zero|exempt
allocation_priority SMALLINT     NOT NULL DEFAULT 100  -- lower settles first
counts_toward_report_gate TINYINT(1) NOT NULL DEFAULT 1
is_active           TINYINT(1)   NOT NULL DEFAULT 1
sort_order          SMALLINT
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, code)

fee_structures                        -- versioned container
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       FK
term_id             BIGINT       NULL FK    -- null = applies to all terms in the year
name                VARCHAR(150) NOT NULL   -- 'Form 1-4 Boarder 2026 T1'
version             SMALLINT     NOT NULL DEFAULT 1
status              VARCHAR(20)  NOT NULL   -- draft|active|superseded|archived
priority            SMALLINT     NOT NULL DEFAULT 100  -- rule evaluation order
effective_from      DATE         NULL
effective_to        DATE         NULL
approved_by         BIGINT       NULL FK
approved_at         TIMESTAMP    NULL
created_by, updated_by, created_at, updated_at
  INDEX (school_id, academic_year_id, term_id, status, priority)

fee_structure_rules                   -- WHO this structure applies to
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
structure_id        BIGINT       FK INDEX
attribute           VARCHAR(50)  NOT NULL  -- section|grade_level|class|enrolment_type|
                                           -- residency|pathway|house|nationality|gender|
                                           -- entry_cohort|custom_field
operator            VARCHAR(20)  NOT NULL  -- in|not_in|equals|between|exists
value               JSON         NOT NULL
custom_field_key    VARCHAR(60)  NULL

fee_structure_items                   -- WHAT is charged
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
structure_id        BIGINT       FK INDEX
component_id        BIGINT       FK → fee_components.id
billing_basis       VARCHAR(20)  NOT NULL  -- flat_per_term|per_subject|per_month|per_day|
                                           -- per_unit|one_off|usage_based|tiered
amount_minor        BIGINT       NULL      -- flat_per_term, one_off
currency            CHAR(3)      NOT NULL
unit_rate_minor     BIGINT       NULL      -- per_subject, per_month, per_day, per_unit
unit_label          VARCHAR(40)  NULL      -- 'subject','month','day','item'
minimum_minor       BIGINT       NULL      -- floor for computed bases
maximum_minor       BIGINT       NULL      -- cap
tier_bands          JSON         NULL      -- [{from:1,to:4,rate:9000},{from:5,rate:7500}]
subject_rate_map    JSON         NULL      -- per subject-group overrides
is_prorated         TINYINT(1)   NOT NULL DEFAULT 1
proration_basis     VARCHAR(20)  NOT NULL DEFAULT 'day' -- day|week|month|none
charge_frequency    VARCHAR(20)  NOT NULL DEFAULT 'termly' -- termly|yearly|once_ever
is_optional         TINYINT(1)   NOT NULL DEFAULT 0    -- opt-in items (clubs, trips)
sort_order          SMALLINT

learner_fee_assignments               -- computed result per learner per term
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       FK
term_id             BIGINT       FK INDEX
student_id          BIGINT       FK INDEX
structure_id        BIGINT       FK
structure_version   SMALLINT     NOT NULL   -- the version that produced this
resolution_trace    JSON         NOT NULL   -- WHICH rules matched, in order ⭐
computed_at         TIMESTAMP    NOT NULL
computed_by         BIGINT       FK → users.id
status              VARCHAR(20)  NOT NULL   -- draft|approved|invoiced|superseded
invoice_id          BIGINT       NULL FK
  UNIQUE (school_id, term_id, student_id, status)  -- where status != 'superseded'
  INDEX  (school_id, term_id, status)

learner_fee_lines                     -- the computed charge lines
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
school_id           BIGINT       FK INDEX
assignment_id       BIGINT       FK INDEX
component_id        BIGINT       FK
structure_item_id   BIGINT       NULL FK
billing_basis       VARCHAR(20)  NOT NULL
quantity            DECIMAL(10,4) NOT NULL DEFAULT 1   -- subjects, days, months, units
unit_rate_minor     BIGINT       NULL
gross_minor         BIGINT       NOT NULL
proration_factor    DECIMAL(8,6) NOT NULL DEFAULT 1
discount_minor      BIGINT       NOT NULL DEFAULT 0
net_minor           BIGINT       NOT NULL
currency            CHAR(3)      NOT NULL
calculation_note    VARCHAR(500) NULL   -- human-readable derivation ⭐
source_reference    VARCHAR(120) NULL   -- e.g. subject ulids for per_subject
effective_from      DATE         NULL   -- mid-term additions
  INDEX (school_id, assignment_id)

ad_hoc_charges
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       FK
term_id             BIGINT       FK
student_id          BIGINT       FK INDEX
component_id        BIGINT       FK
description         VARCHAR(255) NOT NULL   -- 'Replacement textbook: Maths F3'
quantity            DECIMAL(10,2) NOT NULL DEFAULT 1
unit_rate_minor     BIGINT       NOT NULL
amount_minor        BIGINT       NOT NULL
currency            CHAR(3)      NOT NULL
source_type         VARCHAR(60)  NULL   -- library_fine|damage|uniform_sale|trip
source_id           BIGINT       NULL
status              VARCHAR(20)  NOT NULL -- pending|invoiced|cancelled
invoice_id          BIGINT       NULL FK
raised_by           BIGINT       FK → users.id
approved_by         BIGINT       NULL FK
created_at
  INDEX (school_id, student_id, status)

billing_runs
──────────────────────────────────────────────────────────────────
id                  BIGINT PK
ulid                CHAR(26)     UNIQUE
school_id           BIGINT       FK INDEX
academic_year_id    BIGINT       FK
term_id             BIGINT       FK
scope_filter        JSON         NULL   -- section, level, class, enrolment type
status              VARCHAR(20)  NOT NULL -- computing|preview|approved|committing|
                                          -- committed|failed|cancelled
total_learners      INT          NOT NULL DEFAULT 0
computed_count      INT          NOT NULL DEFAULT 0
exception_count     INT          NOT NULL DEFAULT 0
total_gross_minor   BIGINT       NOT NULL DEFAULT 0
total_discount_minor BIGINT      NOT NULL DEFAULT 0
total_net_minor     BIGINT       NOT NULL DEFAULT 0
currency_totals     JSON         NULL   -- per currency
variance_report     JSON         NULL   -- vs previous term
exception_report    JSON         NULL
computed_by         BIGINT       FK → users.id
approved_by         BIGINT       NULL FK
approved_at         TIMESTAMP    NULL
committed_at        TIMESTAMP    NULL
journal_batch_uuid  CHAR(36)     NULL
  INDEX (school_id, term_id, status)
```

### 3. ⭐ The billing pipeline

```
FOR EACH learner in scope:

  1  RESOLVE STRUCTURE
     ├─ load all active structures for (school, year, term)
     ├─ order by priority ASC
     ├─ for each, evaluate every fee_structure_rule against the learner
     ├─ first structure where ALL rules match → selected
     └─ record every evaluation in resolution_trace, matched or not ⭐

  2  FOR EACH structure item:
     ├─ FLAT_PER_TERM  → gross = amount_minor
     ├─ PER_SUBJECT    → quantity = count of enrolled subjects at effective date
     │                   gross = Σ(rate per subject, honouring subject_rate_map)
     │                   apply tier_bands if present
     │                   apply minimum / maximum
     ├─ PER_MONTH      → quantity = months in term (or partial from join date)
     ├─ PER_DAY        → quantity = teaching_days (from CORE-03) or attended portion
     ├─ PER_UNIT       → quantity supplied by the requesting module
     ├─ ONE_OFF        → charged only if never charged before (checked against history)
     ├─ TIERED         → band lookup
     └─ USAGE_BASED    → quantity from the metering module (transport zone, wallet)

  3  PRORATION
     if learner joined, left, or changed residency mid-term AND item is_prorated:
        factor = eligible_days / term.teaching_days
        gross  = round(gross × factor)
        record the derivation in calculation_note

  4  DISCOUNTS (FIN-07 hook)
     apply matching discounts per component
     discount_minor recorded separately — gross is NEVER reduced ⭐

  5  AD HOC CHARGES
     pull pending ad_hoc_charges for this learner and term

  6  WRITE learner_fee_assignment + learner_fee_lines  (status = draft)

END FOR

  7  AGGREGATE run totals, per currency
  8  COMPUTE variance vs previous term, per learner and in aggregate
  9  BUILD exception report
 10  PRESENT PREVIEW — nothing is invoiced, no journal posted ⭐
 11  HUMAN APPROVAL
 12  COMMIT → FIN-03 raises invoices → FIN-01 posts journals
```

### 4. ⭐ Full-time versus part-time, concretely

The distinction is **not** a branch in the code. It is a `fee_structure_rule` on `enrolment_type` selecting a structure whose items use different billing bases.

**Full-time structure — Form 3 boarder**

| Component | Basis | Rate | Currency | Computed |
|---|---|---|---|---|
| Tuition | `flat_per_term` | 450.00 | USD | 450.00 |
| Development levy | `flat_per_term` | 1,200.00 | ZWG | 1,200.00 |
| Boarding | `flat_per_term` | 600.00 | USD | 600.00 |
| Sports & activities | `flat_per_term` | 25.00 | USD | 25.00 |

Subject count is irrelevant. A learner taking nine subjects pays the same as one taking seven.

**Part-time structure — A-Level day, three subjects**

```json
{
  "billing_basis": "per_subject",
  "unit_rate_minor": 8000,
  "currency": "USD",
  "subject_rate_map": {
    "group:sciences_practical": 11000,
    "group:sciences": 9000,
    "group:commercials": 8000,
    "group:arts": 7500
  },
  "tier_bands": [
    { "from": 1, "to": 4, "multiplier": 1.00 },
    { "from": 5, "to": null, "multiplier": 0.85 }
  ],
  "minimum_minor": 15000,
  "maximum_minor": 45000
}
```

| Subject | Group | Rate | 
|---|---|---|
| Mathematics | sciences | USD 90.00 |
| Physics | sciences_practical | USD 110.00 |
| Accounting | commercials | USD 80.00 |
| **Total** | | **USD 280.00** |

Plus `ONE_OFF` registration of USD 20.00 in their first term only → **USD 300.00**.

**Mid-term subject addition.** Learner adds Statistics in week 4 of a 13-week term:

```
calculation_note:
  "Statistics added 2026-09-28 (effective week 4).
   Sciences rate USD 90.00 × 45/65 teaching days remaining = USD 62.31.
   Pro-rated per structure item FSI-01H8 basis=per_subject, proration=day."
```

That note is stored on the fee line and printed on the invoice. When a parent phones to query it, the bursar reads the answer straight off the screen.

### 5. ⭐ The resolution trace

Every assignment stores exactly why the learner was billed what they were billed.

```json
{
  "learner": { "id": "01JB...", "admission_no": "SGC/2023/0412" },
  "attributes_evaluated": {
    "section": "USEC", "grade_level": "F5", "enrolment_type": "PART_TIME",
    "residency": "DAY", "pathway": "ACADEMIC", "nationality": "ZW",
    "subject_count": 3, "joined_on": "2024-01-15"
  },
  "structures_evaluated": [
    { "structure": "F1-F4 Boarder 2026 T1", "priority": 10, "matched": false,
      "failed_rule": "grade_level in [F1,F2,F3,F4]" },
    { "structure": "A-Level Full-Time 2026 T1", "priority": 20, "matched": false,
      "failed_rule": "enrolment_type equals FULL_TIME" },
    { "structure": "A-Level Part-Time 2026 T1", "priority": 30, "matched": true,
      "rules_matched": ["section in [USEC]", "enrolment_type equals PART_TIME"] }
  ],
  "selected_structure": { "id": "01JC...", "version": 2 },
  "items_computed": [
    { "component": "TUITION", "basis": "per_subject", "quantity": 3,
      "subjects": ["Mathematics(sciences)", "Physics(sciences_practical)",
                   "Accounting(commercials)"],
      "gross_minor": 28000, "currency": "USD",
      "note": "9000 + 11000 + 8000; tier multiplier 1.00 (3 subjects, band 1-4)" },
    { "component": "REGISTRATION", "basis": "one_off", "gross_minor": 2000,
      "currency": "USD", "note": "First term; no prior REGISTRATION charge found" }
  ]
}
```

This object is the answer to every fee dispute a school will ever have. It is displayed on screen with a "why this amount?" button on every learner's fee page.

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-02-001` | Structures are evaluated in `priority` ascending. The first whose rules **all** match is selected. |
| `BR-FIN-02-002` | A learner matching no structure is an **exception**, not a zero charge. They appear on the exception report and are not invoiced until resolved. |
| `BR-FIN-02-003` | Every assignment stores a complete resolution trace, including structures that were evaluated and rejected and why. |
| `BR-FIN-02-004` | `PER_SUBJECT` quantity is derived from `ACA-02` subject enrolments effective on the billing date. **There is no separate "billable subject count" field.** One source of truth. |
| `BR-FIN-02-005` | A subject added mid-term raises a pro-rated charge from its effective date. A subject dropped raises a pro-rated credit note. Both happen automatically on the enrolment event. |
| `BR-FIN-02-006` | Proration divides by `term.teaching_days` from `CORE-03`. If teaching days change after invoicing, existing invoices are not silently recomputed; the change is flagged for review. |
| `BR-FIN-02-007` | `ONE_OFF` items check the learner's full charging history across all terms and years. A registration fee is charged exactly once, ever. |
| `BR-FIN-02-008` | `minimum_minor` and `maximum_minor` apply after quantity and tier computation, before proration. |
| `BR-FIN-02-009` | Discounts are recorded as `discount_minor` alongside an unreduced `gross_minor`. The school always sees gross billed, total discount, and net. |
| `BR-FIN-02-010` | Each component is billed in its own currency. A single assignment routinely spans USD and ZWG. |
| `BR-FIN-02-011` | Structures are versioned. Editing an `active` structure creates version *n+1*; the prior version is retained and remains linked to the assignments it produced. |
| `BR-FIN-02-012` | A structure cannot be edited in a term whose financial period is locked. |
| `BR-FIN-02-013` ⭐ | **A billing run never commits without human approval of the preview.** Computation, preview, approval and commit are four distinct states. |
| `BR-FIN-02-014` | The preview reports, per learner and in aggregate: total gross, total discount, total net per currency, and variance against the same learner's previous term. |
| `BR-FIN-02-015` | The exception report flags: learners matching no structure; zero-value assignments; variance beyond `finance.billing_variance_alert_percent` (default 25%); part-time learners with zero enrolled subjects; learners with an existing non-superseded assignment for the term. |
| `BR-FIN-02-016` | Committing a run posts one `FEE_BILLING` journal batch through `FIN-01` and raises invoices through `FIN-03`, atomically per learner. A failure on one learner does not abort the run; it is reported. |
| `BR-FIN-02-017` | A committed run cannot be un-run. Corrections are credit notes and supplementary invoices. |
| `BR-FIN-02-018` | Re-running billing for a term supersedes prior draft assignments only. Already-invoiced assignments are untouched. |
| `BR-FIN-02-019` | Ad hoc charges above `finance.ad_hoc_approval_threshold_minor` require approval before invoicing. |
| `BR-FIN-02-020` | A learner who joins mid-term is billed on enrolment using the same engine, pro-rated, without waiting for the next batch run. |
| `BR-FIN-02-021` | A residency change mid-term (boarder → day scholar) triggers recalculation of affected components with pro-rated credit and charge, both traceable. |
| `BR-FIN-02-022` | Optional items (`is_optional = 1`) are only charged where the learner has an explicit opt-in record. |

### 7. Screens

| Screen | Component | Permission |
|---|---|---|
| Fee components | `Finance\Fees\Components` | `finance.fee_component.manage` |
| Structure library | `Finance\Fees\Structures` | `finance.fee_structure.view` |
| Structure builder | `Finance\Fees\StructureBuilder` | `finance.fee_structure.manage` — rules panel, items panel, **live "who does this match?" learner count** |
| Structure versions | `Finance\Fees\StructureVersions` | `finance.fee_structure.view` — diff between versions |
| Billing run wizard | `Finance\Billing\RunWizard` | `finance.billing.run` — scope → compute → preview → approve → commit |
| Billing preview | `Finance\Billing\Preview` | `finance.billing.run` — per-learner table, variance column, exception tab, drill to trace |
| Run history | `Finance\Billing\History` | `finance.billing.view` |
| Learner fee detail | `Finance\Fees\LearnerDetail` | `finance.fee.view` — lines, calculation notes, **"Why this amount?" trace viewer** |
| Ad hoc charge | `Finance\Fees\AdHocCharge` | `finance.ad_hoc.create` — individual or bulk-to-class |
| Fee simulator | `Finance\Fees\Simulator` | `finance.fee_structure.view` — "what would a Form 2 boarder pay?" without any learner |

**The structure builder's live match count** is the highest-value UX detail in this module. As the bursar adds a rule, the screen shows "matches 247 learners" and lists a sample. Building a fee structure blind and discovering the error after invoicing 1,400 families is the failure mode this prevents.

### 8. API endpoints

```
GET /api/v1/finance/fee-structure            → the current learner's applicable fees
GET /api/v1/students/{ulid}/fee-breakdown    ?term=   → lines with calculation notes
```

Parents get the breakdown with the notes. Showing a parent exactly how their part-time child's three subjects produced USD 280.00 removes most fee queries before they are made.

### 9. Permissions

```
finance.fee_component.view       finance.fee_component.manage
finance.fee_structure.view       finance.fee_structure.manage
finance.fee_structure.approve
finance.billing.view             finance.billing.run
finance.billing.approve ⚠        finance.billing.commit ⚠
finance.ad_hoc.create            finance.ad_hoc.approve
finance.fee.view
```

`finance.billing.approve` and `finance.billing.commit` should be held by different people in a well-run school. The system permits combining them but the role template does not.

### 10. Settings

| Key | Type | Default |
|---|---|---|
| `finance.billing_variance_alert_percent` | int | `25` |
| `finance.ad_hoc_approval_threshold_minor` | int | `5000` (USD 50) |
| `finance.default_proration_basis` | enum | `day` |
| `finance.allow_zero_value_invoices` | bool | `false` |
| `finance.one_off_check_scope` | enum | `school` (vs `academic_year`) |
| `finance.auto_bill_on_enrolment` | bool | `true` |
| `finance.auto_recalculate_on_subject_change` | bool | `true` |

### 11. Events published

`FeeStructureVersioned` · `BillingRunComputed` · `BillingRunApproved` · `BillingRunCommitted` · `LearnerFeeAssigned` · `LearnerFeeRecalculated` · `AdHocChargeRaised`

### 12. Events consumed

| From | Event | Reaction |
|---|---|---|
| `ACA-02` | `SubjectEnrolmentAdded` | Pro-rated charge for part-time learners |
| `ACA-02` | `SubjectEnrolmentDropped` | Pro-rated credit note |
| `PPL-01` | `LearnerEnrolled` | Immediate pro-rated billing |
| `PPL-01` | `LearnerResidencyChanged` | Recalculate affected components |
| `PPL-01` | `LearnerWithdrawn` | Pro-rated credit for unused period |
| `ACA-10` | `LibraryFineRaised` | Ad hoc charge |
| `BRD-01` | `HostelDamageRecorded` | Ad hoc charge |
| `OPS-01` | `TransportZoneAssigned` | Usage-based transport charge |

### 13. Acceptance criteria

```gherkin
AC-FIN-02-001
  Given a full-time Form 3 boarder
  When billing runs for Term 1
  Then they are charged the flat composite fee
  And the charge is identical regardless of how many subjects they take

AC-FIN-02-002
  Given a part-time A-Level learner enrolled in Mathematics, Physics and Accounting
  And per-group rates of 9000, 11000 and 8000 minor units
  When billing runs
  Then tuition is USD 280.00
  And the fee line records quantity 3 and names the three subjects

AC-FIN-02-003
  Given a part-time learner adds a fourth subject in week 4 of a 13-week term
  When the enrolment event fires
  Then a pro-rated charge is raised for the remaining teaching days
  And the calculation note states the rate, the day fraction, and the effective date

AC-FIN-02-004
  Given a part-time learner drops a subject in week 6
  Then a pro-rated credit note is raised
  And the learner's balance decreases by exactly the unused portion

AC-FIN-02-005
  Given a learner matches no fee structure
  When billing runs
  Then they appear on the exception report
  And no invoice is raised for them
  And the run can still be approved for the remaining learners

AC-FIN-02-006
  Given a billing run has been computed
  When no human has approved the preview
  Then no invoice exists
  And no journal has been posted

AC-FIN-02-007
  Given a learner was charged a one-off registration fee in 2024
  When billing runs in 2026
  Then the registration fee is not charged again

AC-FIN-02-008
  Given a learner's Term 2 total is 40% higher than their Term 1 total
  And the variance alert threshold is 25%
  Then they appear on the exception report with the variance stated

AC-FIN-02-009
  Given a fee structure is edited after producing invoices
  When I view a historical assignment
  Then it references the structure version that produced it
  And its amounts are unchanged

AC-FIN-02-010
  Given I open a learner's fee detail and click "Why this amount?"
  Then I see every structure evaluated, which rules matched or failed,
       the selected structure, and the derivation of every line
```

---

# FIN-03 · Invoicing, Statements & Debtor Management

### 1. Scope

**In scope.** Invoices and lines, credit notes, split liability across guardians, statements, aging, reminder ladder, payment plans, waivers and write-offs, refunds, report-card release gating.

**Out of scope.** Computing what is owed (`FIN-02`). Receiving money (`FIN-04`).

### 2. Data model

```sql
invoices
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
academic_year_id      BIGINT       FK
term_id               BIGINT       FK INDEX
invoice_number        VARCHAR(80)  NOT NULL   -- CORE-06, gapless
invoice_type          VARCHAR(20)  NOT NULL   -- term|supplementary|ad_hoc|opening
student_id            BIGINT       FK INDEX
billed_party_type     VARCHAR(30)  NOT NULL   -- guardian|sponsor|employer|organisation
billed_party_id       BIGINT       NOT NULL
liability_percent     DECIMAL(5,2) NOT NULL DEFAULT 100.00  -- split invoicing
assignment_id         BIGINT       NULL FK → learner_fee_assignments.id
issue_date            DATE         NOT NULL
due_date              DATE         NOT NULL
gross_minor           BIGINT       NOT NULL
discount_minor        BIGINT       NOT NULL DEFAULT 0
net_minor             BIGINT       NOT NULL
paid_minor            BIGINT       NOT NULL DEFAULT 0   -- CACHE, derived from allocations
credited_minor        BIGINT       NOT NULL DEFAULT 0   -- CACHE
written_off_minor     BIGINT       NOT NULL DEFAULT 0   -- CACHE
balance_minor         BIGINT       NOT NULL             -- CACHE
currency              CHAR(3)      NOT NULL
status                VARCHAR(20)  NOT NULL  -- draft|issued|partially_paid|paid|
                                             -- overdue|voided|written_off
journal_id            BIGINT       NULL FK
document_id           BIGINT       NULL FK → documents.id
voided_at             TIMESTAMP    NULL
void_reason           TEXT         NULL
replaced_by_invoice_id BIGINT      NULL FK → invoices.id
notes                 TEXT         NULL
created_by, created_at, updated_at
  UNIQUE (school_id, invoice_number)
  INDEX  (school_id, student_id, term_id)
  INDEX  (school_id, billed_party_type, billed_party_id, status)
  INDEX  (school_id, status, due_date)
  -- No UPDATE except status and the four cache columns (trigger-enforced)

invoice_lines
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
invoice_id            BIGINT       FK INDEX
line_number           SMALLINT     NOT NULL
component_id          BIGINT       FK → fee_components.id
fee_line_id           BIGINT       NULL FK → learner_fee_lines.id
description           VARCHAR(255) NOT NULL
calculation_note      VARCHAR(500) NULL     -- carried from FIN-02, printed
quantity              DECIMAL(10,4) NOT NULL DEFAULT 1
unit_rate_minor       BIGINT       NULL
gross_minor           BIGINT       NOT NULL
discount_minor        BIGINT       NOT NULL DEFAULT 0
net_minor             BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
allocation_priority   SMALLINT     NOT NULL   -- from component; drives settlement order
tax_category          VARCHAR(20)  NOT NULL
is_fiscalisable       TINYINT(1)   NOT NULL DEFAULT 0
  INDEX (school_id, invoice_id)

credit_notes
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
academic_year_id      BIGINT       FK
term_id               BIGINT       FK
credit_note_number    VARCHAR(80)  NOT NULL
student_id            BIGINT       FK INDEX
invoice_id            BIGINT       NULL FK   -- null = standalone credit
reason_code           VARCHAR(40)  NOT NULL  -- subject_dropped|withdrawal|billing_error|
                                             -- residency_change|goodwill|overcharge
reason                TEXT         NOT NULL
amount_minor          BIGINT       NOT NULL
applied_minor         BIGINT       NOT NULL DEFAULT 0
currency              CHAR(3)      NOT NULL
issue_date            DATE         NOT NULL
status                VARCHAR(20)  NOT NULL  -- draft|approved|issued|applied|voided
journal_id            BIGINT       NULL FK
document_id           BIGINT       NULL FK
approval_request_id   BIGINT       NULL FK
raised_by             BIGINT       FK → users.id
approved_by           BIGINT       NULL FK
created_at
  UNIQUE (school_id, credit_note_number)

credit_note_lines
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
credit_note_id        BIGINT       FK INDEX
invoice_line_id       BIGINT       NULL FK
component_id          BIGINT       FK
description           VARCHAR(255) NOT NULL
amount_minor          BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL

fee_liabilities                       -- who pays what share
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
student_id            BIGINT       FK INDEX
party_type            VARCHAR(30)  NOT NULL  -- guardian|sponsor|employer|organisation
party_id              BIGINT       NOT NULL
component_id          BIGINT       NULL FK   -- null = applies to all components
share_type            VARCHAR(20)  NOT NULL  -- percentage|fixed|full_component
share_percent         DECIMAL(5,2) NULL
share_amount_minor    BIGINT       NULL
currency              CHAR(3)      NULL
effective_from        DATE         NOT NULL
effective_to          DATE         NULL
priority              SMALLINT     NOT NULL DEFAULT 0
is_active             TINYINT(1)   NOT NULL DEFAULT 1
created_by, created_at
  INDEX (school_id, student_id, is_active)

payment_plans
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
student_id            BIGINT       FK INDEX
party_type            VARCHAR(30)  NOT NULL
party_id              BIGINT       NOT NULL
total_minor           BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
instalment_count      TINYINT      NOT NULL
status                VARCHAR(20)  NOT NULL  -- proposed|approved|active|completed|
                                             -- breached|cancelled
agreement_document_id BIGINT       NULL FK
approved_by           BIGINT       NULL FK
breach_count          TINYINT      NOT NULL DEFAULT 0
created_by, created_at

payment_plan_instalments
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
plan_id               BIGINT       FK INDEX
instalment_number     TINYINT      NOT NULL
due_date              DATE         NOT NULL
amount_minor          BIGINT       NOT NULL
paid_minor            BIGINT       NOT NULL DEFAULT 0
status                VARCHAR(20)  NOT NULL  -- pending|paid|partial|overdue|waived

fee_waivers                           -- and write-offs
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
term_id               BIGINT       FK
student_id            BIGINT       FK INDEX
invoice_id            BIGINT       NULL FK
type                  VARCHAR(20)  NOT NULL  -- waiver|write_off
amount_minor          BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
reason_code           VARCHAR(40)  NOT NULL  -- hardship|orphan|staff_child|
                                             -- uncollectable|deceased|goodwill
reason                TEXT         NOT NULL
supporting_document_id BIGINT      NULL FK
status                VARCHAR(20)  NOT NULL  -- pending|approved|rejected|posted
approval_request_id   BIGINT       NULL FK
journal_id            BIGINT       NULL FK
requested_by          BIGINT       FK → users.id
approved_by           BIGINT       NULL FK
created_at

reminder_schedules
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
name                  VARCHAR(120) NOT NULL
days_after_due        SMALLINT     NOT NULL   -- 0, 7, 14, 30, 60
minimum_balance_minor BIGINT       NOT NULL DEFAULT 0
currency              CHAR(3)      NULL
channels              JSON         NOT NULL   -- ['whatsapp','sms']
template_key          VARCHAR(80)  NOT NULL
audience              VARCHAR(30)  NOT NULL   -- fee_responsible|all_guardians
escalate_to_role_id   BIGINT       NULL FK
is_active             TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, days_after_due)

reminders_sent
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
schedule_id           BIGINT       FK
invoice_id            BIGINT       FK INDEX
notification_id       BIGINT       NULL FK
balance_at_send_minor BIGINT       NOT NULL
sent_at               TIMESTAMP    NOT NULL
  UNIQUE (schedule_id, invoice_id)     -- each ladder rung fires once per invoice
```

### 3. Split liability resolution ⭐

Zimbabwean family and sponsorship arrangements are genuinely complicated. Modelling them properly here prevents a large class of billing disputes.

```
Learner: Tinashe Moyo, Form 4 boarder
Total term charges: USD 1,075.00 + ZWG 1,200.00

fee_liabilities:
  Father   → TUITION   100%
  Employer → BOARDING  100%
  Father   → all other  60%
  Mother   → all other  40%

Resolution produces THREE invoices:
  INV/.../0481  Father    Tuition 450.00 + 60% of (levy ZWG 1,200 + sports 25.00)
  INV/.../0482  Employer  Boarding 600.00
  INV/.../0483  Mother    40% of (levy ZWG 1,200 + sports 25.00)

Σ(invoices) ≡ Σ(learner charges), per currency.        ← asserted, always
```

Each party sees only their own invoice and statement. The learner's overall position aggregates all three, and is visible to staff and to any guardian with the `may_view_full_balance` flag.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-03-001` | An invoice is raised from a committed fee assignment, an ad hoc charge, or an opening balance import. Nothing else creates invoices. |
| `BR-FIN-03-002` | Invoice numbers come from `CORE-06` and are gapless per school. |
| `BR-FIN-03-003` | Issuing an invoice posts a `FEE_BILLING` journal through `FIN-01` in the same transaction. An invoice with no journal cannot exist. |
| `BR-FIN-03-004` | **An issued invoice is never edited.** It is voided (posting a reversal, with reason) and replaced, with `replaced_by_invoice_id` linking the two. |
| `BR-FIN-03-005` | Voiding is refused if any payment has been allocated to the invoice. Settle or reallocate first. |
| `BR-FIN-03-006` | `Σ(split invoices for a learner-term) ≡ Σ(learner charges)`, per currency. Asserted on issue; a mismatch aborts the whole set. |
| `BR-FIN-03-007` | Liability resolution honours `priority`, then component-specific rules, then general rules. Any unallocated residue falls to the primary fee-responsible guardian. |
| `BR-FIN-03-008` | `paid_minor`, `credited_minor`, `written_off_minor` and `balance_minor` on the invoice are **caches** derived from allocations and journals, verified nightly. |
| `BR-FIN-03-009` | A statement is generated from journal lines for a date range, never from cached invoice figures, and is therefore reproducible for any closed period. |
| `BR-FIN-03-010` | Credit notes require approval where the amount exceeds `finance.credit_note_approval_threshold_minor`, and always require a reason code and free-text reason. |
| `BR-FIN-03-011` | A credit note posts `Dr Fee Income / Cr Fee Debtors`. It **never** posts as a receipt — a credit is not money received, and conflating the two overstates collections. |
| `BR-FIN-03-012` | Write-offs post `Dr Bad Debt Expense / Cr Fee Debtors`, require approval through `CORE-07`, and are reported separately from collections in every report. |
| `BR-FIN-03-013` | A waiver reduces the amount owed before it is chased; a write-off recognises an amount as uncollectable after it has been chased. They post to different accounts and are never conflated. |
| `BR-FIN-03-014` | Aging buckets are configurable, defaulting to current, 1–30, 31–60, 61–90, 91–120, 120+, computed per currency from `due_date`. |
| `BR-FIN-03-015` | Each reminder ladder rung fires at most once per invoice, tracked in `reminders_sent`. Duplicate chasing damages the school's relationship with parents. |
| `BR-FIN-03-016` | Reminders are suppressed for invoices under an active, non-breached payment plan. |
| `BR-FIN-03-017` | A payment plan instalment missed by `finance.payment_plan_grace_days` marks the plan `breached`, restores normal reminders, and notifies the bursar. |
| `BR-FIN-03-018` | Report card release gating, when enabled, compares the learner's total balance against the threshold, counting only components with `counts_toward_report_gate = 1`. Per-learner override is permitted with a reason and is logged. |
| `BR-FIN-03-019` | A refund requires an approved credit balance, goes through the approval chain, and posts `Dr Fee Debtors / Cr Bank`. |
| `BR-FIN-03-020` | A withdrawn learner's unused pro-rata is credited automatically; any residual credit balance is flagged for refund or transfer to a sibling account. |
| `BR-FIN-03-021` | Statements and invoices are downloadable from the parent portal at any time and always render from the template version in force at issue. |

### 5. Screens

| Screen | Component | Permission |
|---|---|---|
| Invoice list | `Finance\Invoices\Index` | `finance.invoice.view` |
| Invoice detail | `Finance\Invoices\Show` | `finance.invoice.view` — lines, calculation notes, allocations, journal link, PDF |
| Void & reissue | `Finance\Invoices\Void` | `finance.invoice.void` ⚠ |
| Credit note | `Finance\CreditNotes\Create` | `finance.credit_note.create` |
| Learner account | `Finance\Accounts\LearnerAccount` | `finance.fee.view` — **the bursar's most-used screen**: balance per currency, all invoices, all receipts, running statement, aging, quick actions |
| Statement generator | `Finance\Statements\Generate` | `finance.statement.generate` — per learner, per guardian, per family, any date range |
| Aged debtors | `Finance\Reports\AgedDebtors` | `finance.report.debtors` — by class, section, residency, currency; drill to learner |
| Debtor workbench | `Finance\Debtors\Workbench` | `finance.debtor.manage` — prioritised chase list with call notes and outcomes |
| Reminder ladder | `Finance\Reminders\Schedules` | `finance.reminder.manage` |
| Payment plans | `Finance\PaymentPlans\Index` | `finance.payment_plan.manage` |
| Waivers & write-offs | `Finance\Waivers\Index` | `finance.waiver.request` / `.approve` |
| Liability setup | `Finance\Liabilities\Editor` | `finance.liability.manage` — per learner, visual share allocation with live total check |

### 6. API endpoints

```
GET /api/v1/finance/balance                    → caller's total across their learners
GET /api/v1/students/{ulid}/balance            ?currency=
GET /api/v1/students/{ulid}/invoices           paginated
GET /api/v1/finance/invoices/{ulid}
GET /api/v1/finance/invoices/{ulid}/download
GET /api/v1/finance/statement                  ?from=&to=&student=&format=json|pdf
GET /api/v1/finance/payment-plans              → caller's plans and instalment schedule
```

### 7. Permissions

```
finance.invoice.view          finance.invoice.issue        finance.invoice.void ⚠
finance.credit_note.create    finance.credit_note.approve ⚠
finance.statement.generate    finance.report.debtors
finance.debtor.manage         finance.reminder.manage
finance.payment_plan.create   finance.payment_plan.approve
finance.waiver.request        finance.waiver.approve ⚠
finance.write_off.request     finance.write_off.approve ⚠⚠
finance.refund.request        finance.refund.approve ⚠
finance.liability.manage      finance.report_gate.override
```

### 8. Settings

| Key | Type | Default |
|---|---|---|
| `finance.invoice_due_days_after_issue` | int | `14` |
| `finance.aging_buckets` | array | `[30,60,90,120]` |
| `finance.credit_note_approval_threshold_minor` | int | `0` (all require approval) |
| `finance.write_off_approval_threshold_minor` | int | `0` |
| `finance.payment_plan_grace_days` | int | `5` |
| `finance.report_gate_enabled` | bool | `false` |
| `finance.report_gate_threshold_minor` | int | `0` |
| `finance.report_gate_threshold_currency` | string | `USD` |
| `finance.statement_show_all_currencies` | bool | `true` |

### 9. Acceptance criteria

```gherkin
AC-FIN-03-001
  Given a learner with father paying tuition, employer paying boarding,
        and levy split 60/40 between father and mother
  When invoices are issued
  Then three invoices exist
  And their total equals the learner's total charges in every currency
  And each party sees only their own invoice

AC-FIN-03-002
  Given an issued invoice with a payment allocated
  When I attempt to void it
  Then the void is refused with a message naming the allocated receipt

AC-FIN-03-003
  Given an issued invoice with no payment
  When I void it with a reason
  Then a reversal journal is posted
  And the original invoice and its journal remain unaltered
  And the replacement invoice links back to the original

AC-FIN-03-004
  Given a credit note is issued for USD 62.31
  Then it posts Dr Fee Income / Cr Fee Debtors
  And it does NOT appear in the term's fee collection total

AC-FIN-03-005
  Given the 30-day reminder has already fired for an invoice
  When the reminder job runs again the following day
  Then no duplicate reminder is sent

AC-FIN-03-006
  Given a learner is under an active payment plan meeting its instalments
  When the reminder job runs
  Then no overdue reminder is sent for the covered invoices

AC-FIN-03-007
  Given report gating is enabled with a USD 100 threshold
  And a learner owes USD 340
  When report cards are released
  Then that learner's report is withheld
  And an authorised user can override for that learner with a logged reason

AC-FIN-03-008
  Given a statement for 2025 Term 2 was printed in 2025
  When I regenerate it in 2026
  Then it is identical
  And it renders with the statement template version in force in 2025

AC-FIN-03-009
  Given a learner withdraws in week 5 of a 13-week term
  Then a pro-rated credit note is raised for the unused portion
  And any resulting credit balance is flagged for refund or sibling transfer
```

---

# FIN-04 · Receipting, Cashiering & Till Control ⭐

> Where cash actually goes missing. The controls here are deliberately strict and none of them is optional.

### 1. Scope

**In scope.** Multi-tender receipting, till sessions with blind cash-up, allocation engine, part and over payment, the suspense workflow, receipt void, banking sheets, fiscalisation routing.

**Out of scope.** Online payment initiation (`FIN-05`). Supplier payments (`FIN-08`). Fiscalisation mechanics (`FIN-13`).

### 2. Data model

```sql
tills
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
code                  VARCHAR(20)  NOT NULL   -- 'BURSARY-1','TUCKSHOP'
name                  VARCHAR(120) NOT NULL
location              VARCHAR(120)
bank_account_id       BIGINT       NULL FK → accounts.id
cash_account_id       BIGINT       FK → accounts.id
accepted_currencies   JSON         NOT NULL
accepted_tenders      JSON         NOT NULL
is_fiscalised         TINYINT(1)   NOT NULL DEFAULT 0
is_active             TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

till_sessions
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
academic_year_id      BIGINT       FK
term_id               BIGINT       FK
till_id               BIGINT       FK INDEX
cashier_id            BIGINT       FK → users.id
session_number        VARCHAR(60)  NOT NULL
opened_at             TIMESTAMP    NOT NULL
closed_at             TIMESTAMP    NULL
opening_float         JSON         NOT NULL   -- { "USD": 5000, "ZWG": 200000 }
declared_closing      JSON         NULL       -- BLIND: cashier's count
expected_closing      JSON         NULL       -- system figure, revealed AFTER declaration
variance              JSON         NULL       -- per currency
variance_reason       TEXT         NULL
status                VARCHAR(20)  NOT NULL   -- open|declaring|closed|reconciled|disputed
receipt_count         INT          NOT NULL DEFAULT 0
totals_by_tender      JSON         NULL
totals_by_currency    JSON         NULL
supervised_by         BIGINT       NULL FK → users.id   -- variance sign-off
journal_id            BIGINT       NULL FK
banking_sheet_doc_id  BIGINT       NULL FK
  UNIQUE (school_id, session_number)
  INDEX  (school_id, till_id, status)
  INDEX  (school_id, cashier_id, opened_at)

receipts
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
academic_year_id      BIGINT       FK
term_id               BIGINT       FK INDEX
receipt_number        VARCHAR(80)  NOT NULL   -- gapless
till_session_id       BIGINT       NULL FK    -- null for gateway receipts
receipt_type          VARCHAR(20)  NOT NULL   -- fee|tuckshop|hire|sundry|deposit
payer_type            VARCHAR(30)  NOT NULL   -- guardian|sponsor|student|external
payer_id              BIGINT       NULL
payer_name            VARCHAR(200) NOT NULL   -- always captured, even for walk-ins
payer_phone           VARCHAR(30)  NULL
student_id            BIGINT       NULL FK INDEX   -- null when unidentified
amount_minor          BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
base_amount_minor     BIGINT       NOT NULL
exchange_rate_id      BIGINT       NULL FK
allocated_minor       BIGINT       NOT NULL DEFAULT 0
unallocated_minor     BIGINT       NOT NULL DEFAULT 0
is_suspense           TINYINT(1)   NOT NULL DEFAULT 0
received_at           TIMESTAMP    NOT NULL
effective_date        DATE         NOT NULL
narration             VARCHAR(255) NULL
status                VARCHAR(20)  NOT NULL   -- posted|partially_allocated|allocated|voided
journal_id            BIGINT       FK
document_id           BIGINT       NULL FK
fiscal_receipt_id     BIGINT       NULL FK    -- 🇿🇼 FIN-13
fiscalisation_status  VARCHAR(20)  NULL       -- not_required|queued|submitted|failed
voided_at             TIMESTAMP    NULL
void_reason           TEXT         NULL
void_journal_id       BIGINT       NULL FK
received_by           BIGINT       FK → users.id
created_at
  UNIQUE (school_id, receipt_number)
  INDEX  (school_id, student_id, received_at)
  INDEX  (school_id, is_suspense, status)
  INDEX  (school_id, term_id, received_at)
  -- No UPDATE except status and the allocation cache columns

receipt_tenders                       -- a receipt may mix tenders
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK
receipt_id            BIGINT       FK INDEX
tender_type           VARCHAR(30)  NOT NULL  -- cash|bank_transfer|ecocash|onemoney|
                                             -- innbucks|omari|zipit|card|cheque|
                                             -- journal_transfer|in_kind
amount_minor          BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
reference             VARCHAR(120) NULL      -- transfer ref, cheque number, wallet txn
bank_account_id       BIGINT       NULL FK → accounts.id
cleared_at            TIMESTAMP    NULL      -- cheques
is_cleared            TINYINT(1)   NOT NULL DEFAULT 1

receipt_allocations
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
receipt_id            BIGINT       FK INDEX
invoice_id            BIGINT       NULL FK INDEX
invoice_line_id       BIGINT       NULL FK
component_id          BIGINT       NULL FK
amount_minor          BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
allocation_method     VARCHAR(20)  NOT NULL  -- auto_oldest|auto_priority|manual|suspense
allocated_at          TIMESTAMP    NOT NULL
allocated_by          BIGINT       FK → users.id
reversed_at           TIMESTAMP    NULL
reversed_by           BIGINT       NULL FK
reversal_reason       TEXT         NULL
  INDEX (school_id, invoice_id)

suspense_items                        -- unidentified money ⭐
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
receipt_id            BIGINT       FK
source                VARCHAR(30)  NOT NULL  -- bank_deposit|gateway|cash|transfer
amount_minor          BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
resolved_minor        BIGINT       NOT NULL DEFAULT 0
reference_text        VARCHAR(255) NULL      -- whatever the depositor wrote
depositor_name        VARCHAR(200) NULL
deposit_date          DATE         NOT NULL
status                VARCHAR(20)  NOT NULL  -- unidentified|investigating|
                                             -- partially_resolved|resolved|refunded
suggested_matches     JSON         NULL      -- fuzzy candidates with scores
age_days              INT          NOT NULL DEFAULT 0
assigned_to           BIGINT       NULL FK → users.id
resolved_at           TIMESTAMP    NULL
resolved_by           BIGINT       NULL FK
resolution_note       TEXT         NULL
  INDEX (school_id, status, deposit_date)
```

### 3. ⭐ The blind cash-up

```
1  OPEN
   Cashier declares opening float per currency.
   System records it, posts nothing (float is a transfer within cash accounts).
   Till session status → open.

2  TRANSACT
   Every receipt at this till is bound to this session.
   Running totals accumulate but are NOT displayed to the cashier.   ← the point

3  DECLARE  (status → declaring)
   Cashier enters their physical count, per currency, per tender.
   The expected figure is NOT shown, and cannot be reached from any screen
   while the session is in 'declaring' state.

4  REVEAL
   System reveals expected totals and computes variance per currency.

5  RESOLVE
   |variance| ≤ tolerance  → session closes, variance posts to Cash Over/Short.
   |variance| >  tolerance → mandatory written explanation
                             + supervisor sign-off (a different user)
                             + status may be set to 'disputed'
                             + bursar notified

6  BANK
   Banking sheet generated. Cash handed over. Session → reconciled.
```

If the cashier can see the expected figure before counting, the count is worthless. Every school ERP that skips this has a cash-up feature that proves nothing.

### 4. The allocation engine

```php
public function allocate(Receipt $receipt, AllocationStrategy $strategy): AllocationResult
{
    $open = $this->openInvoicesFor($receipt->student, $receipt->currency);

    $ordered = match ($strategy) {
        AllocationStrategy::OldestFirst    => $open->sortBy('due_date'),
        AllocationStrategy::ComponentPriority
            => $open->sortBy(fn($i) => [$i->minLinePriority(), $i->due_date]),
        AllocationStrategy::Manual         => $this->userSuppliedOrder,
    };

    $remaining = $receipt->amount();

    foreach ($ordered as $invoice) {
        if ($remaining->isZero()) break;
        $settle = Money::min($remaining, $invoice->balance());
        $this->createAllocation($receipt, $invoice, $settle);
        $remaining = $remaining->minus($settle);
    }

    if (!$remaining->isZero()) {
        // Overpayment → learner credit balance. Never silently absorbed.
        $this->createCreditBalance($receipt, $remaining);
    }

    return new AllocationResult(...);
}
```

**Currency rule.** A receipt only auto-allocates to invoices in the same currency. Cross-currency settlement is a deliberate, manual act that goes through `FIN-06` and posts realised FX.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-04-001` | No cashier may receipt outside an open till session assigned to them. |
| `BR-FIN-04-002` | A cashier may hold at most one open session at a time, at one till. |
| `BR-FIN-04-003` | The expected closing figure is inaccessible to the cashier until their declaration is submitted. Enforced server-side, not by hiding a UI element. |
| `BR-FIN-04-004` | Variance beyond `finance.till_variance_tolerance_minor` requires a written reason and sign-off by a different user holding `finance.till.supervise`. |
| `BR-FIN-04-005` | Variance posts to Cash Over/Short. It is never absorbed into fee income and never adjusted away. |
| `BR-FIN-04-006` | A till session cannot be reopened once closed. A correction is a new session with a journal adjustment. |
| `BR-FIN-04-007` | A term's financial period cannot lock while any till session is open. |
| `BR-FIN-04-008` | Every receipt posts its journal in the same transaction. A receipt with no journal cannot exist. |
| `BR-FIN-04-009` | Receipt numbers are gapless per school. A failed receipt voids its number with a reason. |
| `BR-FIN-04-010` ⭐ | **Money is receipted the moment it arrives.** A deposit that cannot be matched to a learner is receipted to Suspense — never held off-ledger, never left in a notebook, never "sorted out on Monday". |
| `BR-FIN-04-011` | A suspense item offers fuzzy match suggestions against admission number, learner surname, guardian name and phone, scored and ranked. The cashier confirms; the system never auto-allocates suspense. |
| `BR-FIN-04-012` | Suspense age is tracked and reported daily. Items older than `finance.suspense_escalation_days` escalate to the bursar. |
| `BR-FIN-04-013` | The suspense balance must be zero, or explicitly acknowledged with a written reason, before a period can lock. |
| `BR-FIN-04-014` | Overpayment creates a learner credit balance. It is never written to income and never quietly retained. |
| `BR-FIN-04-015` | Allocation strategy is configurable per school; the strategy used is recorded on every allocation. |
| `BR-FIN-04-016` | Reallocating a receipt reverses the prior allocations (recorded, not deleted) and creates new ones, with a reason. |
| `BR-FIN-04-017` | Voiding a receipt reverses its journal, reverses every allocation, requires a reason, notifies the payer, and preserves the original in full. Voiding is refused once the period is locked. |
| `BR-FIN-04-018` | Cheques are receipted as uncleared. They do not reduce the learner's balance until cleared. A bounced cheque reverses automatically and raises a charge for the bank fee. |
| `BR-FIN-04-019` | A payer name is captured on every receipt, even for anonymous walk-in payments. |
| `BR-FIN-04-020` | An SMS or WhatsApp confirmation dispatches to the payer immediately on posting. This is transactional and is not suppressible by opt-out. |
| `BR-FIN-04-021` | 🇿🇼 Receipts containing any line where `is_fiscalisable = 1` queue for FDMS submission. **Fiscalisation never blocks receipting** — money is taken first, fiscalised asynchronously, reconciled after. |
| `BR-FIN-04-022` | 🇿🇼 ZWG cash tender rounds to the configured increment; the rounding difference posts to the Rounding account, never to the learner's balance. |
| `BR-FIN-04-023` | The receipting screen must remain usable and complete a transaction in under 20 seconds for an experienced cashier. Queue length at a Zimbabwean bursary on fee-deadline day is a real operational constraint. |

### 6. Screens

| Screen | Component | Permission |
|---|---|---|
| Open till | `Finance\Till\Open` | `finance.till.operate` |
| **Receipt capture** | `Finance\Receipts\Capture` | `finance.receipt.create` — learner search by name, admission number or phone; balance shown; tender split; instant print |
| Receipt detail | `Finance\Receipts\Show` | `finance.receipt.view` |
| Void receipt | `Finance\Receipts\Void` | `finance.receipt.void` ⚠ |
| Reallocate | `Finance\Receipts\Reallocate` | `finance.receipt.reallocate` ⚠ |
| Cash-up | `Finance\Till\CashUp` | `finance.till.operate` — blind declaration then reveal |
| Variance sign-off | `Finance\Till\VarianceApproval` | `finance.till.supervise` |
| Session history | `Finance\Till\Sessions` | `finance.till.view` — by cashier, by till, with variance trend |
| **Suspense workbench** | `Finance\Suspense\Workbench` | `finance.suspense.manage` — aged queue, fuzzy match candidates, one-click allocate |
| Daily banking | `Finance\Till\Banking` | `finance.till.view` |
| Collections dashboard | `Finance\Reports\Collections` | `finance.report.collections` — by day, tender, currency, cashier |

**The receipt capture screen is the most performance-critical screen in the product.** Search must return in under 300 ms. The learner's balance must appear the moment they are selected. Keyboard-only operation must be possible end to end.

### 7. API endpoints

```
GET  /api/v1/students/{ulid}/receipts       → parent view of payment history
GET  /api/v1/finance/receipts/{ulid}
GET  /api/v1/finance/receipts/{ulid}/download
```

Receipting itself is Livewire-only. A counter transaction is not a mobile workflow, and exposing receipt creation over the API widens the attack surface on the most sensitive operation in the system for no benefit.

### 8. Permissions

```
finance.till.operate         finance.till.supervise ⚠     finance.till.view
finance.receipt.create       finance.receipt.view
finance.receipt.void ⚠       finance.receipt.reallocate ⚠
finance.suspense.view        finance.suspense.manage
finance.report.collections
```

### 9. Settings

| Key | Type | Default |
|---|---|---|
| `finance.till_variance_tolerance_minor` | int | `100` (USD 1.00) |
| `finance.default_allocation_strategy` | enum | `auto_oldest` |
| `finance.allow_manual_allocation` | bool | `true` |
| `finance.suspense_escalation_days` | int | `3` |
| `finance.require_payer_phone` | bool | `false` |
| `finance.receipt_sms_enabled` | bool | `true` |
| `finance.cheque_clearing_days` | int | `5` |
| `finance.max_cash_receipt_minor` | int | `0` (no limit) |

### 10. Acceptance criteria

```gherkin
AC-FIN-04-001
  Given a cashier has no open till session
  When they attempt to create a receipt
  Then it is refused with a message directing them to open a till

AC-FIN-04-002
  Given a till session is in 'declaring' state
  When the cashier attempts to view expected totals by any route
  Then the server refuses
  And the figure appears only after their declaration is submitted

AC-FIN-04-003
  Given a declared count is USD 12.00 short and tolerance is USD 1.00
  When the cashier attempts to close
  Then a written reason is mandatory
  And a different user with finance.till.supervise must sign off
  And the variance posts to Cash Over/Short

AC-FIN-04-004
  Given a bank deposit of USD 500 with reference "SCHOOL FEES"
  When it is receipted
  Then it posts Dr Bank / Cr Suspense
  And it appears on the suspense workbench with match suggestions
  And the school's cash position increases immediately

AC-FIN-04-005
  Given a suspense item is matched to a learner
  Then a SUSPENSE_ALLOCATION journal posts Dr Suspense / Cr Fee Debtors
  And the suspense item is marked resolved with the resolving user recorded

AC-FIN-04-006
  Given the suspense balance is USD 1,200
  When the financial close checklist runs
  Then it fails
  Unless the balance is explicitly acknowledged with a written reason

AC-FIN-04-007
  Given a learner owes USD 300 and pays USD 500
  Then USD 300 allocates to the invoice
  And USD 200 becomes a credit balance
  And no amount is posted to income

AC-FIN-04-008
  Given a receipt is voided with a reason
  Then its journal is reversed
  And every allocation is reversed and recorded
  And the original receipt remains fully visible with status 'voided'
  And the payer is notified

AC-FIN-04-009
  Given a receipt includes a fiscalisable tuckshop line
  And the ZIMRA FDMS endpoint is unreachable
  Then the receipt is still created successfully
  And it is queued for fiscalisation
  And the queue depth appears on the health dashboard

AC-FIN-04-010
  Given a cheque receipt for USD 400
  When it is posted
  Then the learner's balance is unchanged until the cheque clears
  And on clearing, the allocation completes automatically
```

---

# FIN-05 · Payment Gateways & Reconciliation 🇿🇼

### 1. Scope

**In scope.** Gateway driver abstraction, payment intents, hosted and push checkout flows, webhook ingestion, status polling, automatic receipting, bank statement import and matching, the four-way reconciliation, exception workbench, gateway fee accounting, refunds and disbursements.

**Out of scope.** Manual counter receipting (`FIN-04`). Fiscalisation (`FIN-13`).

### 2. The 2026 Zimbabwean gateway landscape

| Provider | Coverage | Why you would pick it |
|---|---|---|
| **ContiPay** | Visa with 3DS, Mastercard, EcoCash USD and ZWL, InnBucks, OneMoney, TeleCash, ZIPIT, ZIMSWITCH, O'Mari, Mukuru, Nostro transfers, direct deposit | The widest coverage from one integration, and it supports **disbursements** with RSA private-key signing — which matters for refunds back to a parent's wallet. Enterprise track record. |
| **Pesepay** | EcoCash (best push-prompt experience in the market), cards, wallets | Smoothest EcoCash flow — collect the number in your own UI and push the prompt. Has a **Dart SDK**, directly relevant to the Flutter app. |
| **Paynow** | EcoCash, OneMoney, TeleCash, ZIMSWITCH, and cards via Express | Ubiquitous. Parents recognise the name. Mature PHP SDK. Documentation is dated but the webhook delivery is reliable. |
| **ZB Smile&Pay** | EcoCash, OneMoney, O'Mari, InnBucks, SmileCash, Visa/Mastercard | Bank-backed alternative with hosted and express checkout. |

**Recommended posture:** implement **ContiPay** as primary (breadth plus disbursements), **Pesepay** for the EcoCash push flow in the Flutter app, and **Paynow** as a fallback because parents already trust the brand. The driver abstraction means a school picks its own in settings.

### 3. Data model

```sql
payment_gateways
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
driver                VARCHAR(30)  NOT NULL  -- contipay|pesepay|paynow|smilepay|stripe
name                  VARCHAR(120) NOT NULL
credentials           TEXT         NOT NULL  -- ENCRYPTED
supported_methods     JSON         NOT NULL  -- ['ecocash','innbucks','visa','zipit']
supported_currencies  JSON         NOT NULL
settlement_account_id BIGINT       FK → accounts.id
fee_account_id        BIGINT       FK → accounts.id
fee_model             JSON         NULL      -- { type:'percentage', value:2.5, cap:500 }
is_default            TINYINT(1)   NOT NULL DEFAULT 0
is_sandbox            TINYINT(1)   NOT NULL DEFAULT 1
is_active             TINYINT(1)   NOT NULL DEFAULT 0
priority              SMALLINT     NOT NULL DEFAULT 0
last_health_check_at  TIMESTAMP    NULL
health_status         VARCHAR(20)  NULL      -- up|degraded|down
  UNIQUE (school_id, driver)

payment_intents
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
academic_year_id      BIGINT       FK
term_id               BIGINT       FK
gateway_id            BIGINT       FK
reference             VARCHAR(80)  NOT NULL UNIQUE   -- our reference to the gateway
idempotency_key       CHAR(36)     NOT NULL UNIQUE   -- from the client
student_id            BIGINT       NULL FK INDEX
payer_user_id         BIGINT       NULL FK
payer_name            VARCHAR(200) NOT NULL
payer_phone           VARCHAR(30)  NULL
payer_email           VARCHAR(150) NULL
purpose               VARCHAR(30)  NOT NULL  -- fees|wallet_topup|application|event|hire
amount_minor          BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
method                VARCHAR(30)  NULL      -- ecocash|innbucks|visa|zipit|...
status                VARCHAR(20)  NOT NULL  -- created|pending|processing|succeeded|
                                             -- failed|cancelled|expired|refunded
gateway_reference     VARCHAR(150) NULL
poll_url              VARCHAR(500) NULL
checkout_url          VARCHAR(500) NULL
instructions          TEXT         NULL      -- push-prompt guidance for the payer
failure_code          VARCHAR(60)  NULL
failure_message       TEXT         NULL
fee_minor             BIGINT       NULL
net_settled_minor     BIGINT       NULL
receipt_id            BIGINT       NULL FK   -- created on success
initiated_at          TIMESTAMP    NOT NULL
completed_at          TIMESTAMP    NULL
expires_at            TIMESTAMP    NOT NULL
poll_attempts         SMALLINT     NOT NULL DEFAULT 0
last_polled_at        TIMESTAMP    NULL
metadata              JSON         NULL
  INDEX (school_id, status, initiated_at)
  INDEX (student_id, status)
  INDEX (gateway_reference)

gateway_webhooks                      -- raw, append-only
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       NULL FK
gateway_id            BIGINT       NULL FK
driver                VARCHAR(30)  NOT NULL
event_type            VARCHAR(60)  NULL
raw_headers           JSON         NOT NULL
raw_payload           LONGTEXT     NOT NULL
payload_hash          CHAR(64)     NOT NULL
signature_valid       TINYINT(1)   NOT NULL
intent_id             BIGINT       NULL FK
processing_status     VARCHAR(20)  NOT NULL -- received|processed|ignored|failed|duplicate
processing_error      TEXT         NULL
received_at           TIMESTAMP(6) NOT NULL
processed_at          TIMESTAMP    NULL
  UNIQUE (driver, payload_hash)          -- replay protection
  INDEX  (processing_status, received_at)

bank_accounts
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
gl_account_id         BIGINT       FK → accounts.id
bank_name             VARCHAR(120) NOT NULL
account_name          VARCHAR(200) NOT NULL
account_number        VARCHAR(60)  NOT NULL
branch                VARCHAR(120)
currency              CHAR(3)      NOT NULL
account_type          VARCHAR(30)  NOT NULL  -- current|nostro|fcа|savings
is_active             TINYINT(1)   NOT NULL DEFAULT 1

bank_statements
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
bank_account_id       BIGINT       FK
statement_from        DATE         NOT NULL
statement_to          DATE         NOT NULL
opening_balance_minor BIGINT       NOT NULL
closing_balance_minor BIGINT       NOT NULL
currency              CHAR(3)      NOT NULL
source_file_id        BIGINT       FK → files.id
line_count            INT          NOT NULL DEFAULT 0
matched_count         INT          NOT NULL DEFAULT 0
status                VARCHAR(20)  NOT NULL -- imported|matching|reconciled|disputed
imported_by           BIGINT       FK → users.id
reconciled_by         BIGINT       NULL FK
reconciled_at         TIMESTAMP    NULL

bank_statement_lines
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
school_id             BIGINT       FK INDEX
statement_id          BIGINT       FK INDEX
line_number           INT          NOT NULL
transaction_date      DATE         NOT NULL
value_date            DATE         NULL
description           VARCHAR(500) NOT NULL
reference             VARCHAR(150) NULL
debit_minor           BIGINT       NULL
credit_minor          BIGINT       NULL
running_balance_minor BIGINT       NULL
currency              CHAR(3)      NOT NULL
match_status          VARCHAR(20)  NOT NULL -- unmatched|auto_matched|manually_matched|
                                            -- ignored|disputed
matched_type          VARCHAR(60)  NULL     -- receipt|supplier_payment|journal|fee
matched_id            BIGINT       NULL
match_confidence      TINYINT      NULL     -- 0-100
matched_by            BIGINT       NULL FK
matched_at            TIMESTAMP    NULL
  INDEX (school_id, statement_id, match_status)
  INDEX (school_id, transaction_date, match_status)

reconciliation_runs
──────────────────────────────────────────────────────────────────
id                    BIGINT PK
ulid                  CHAR(26)     UNIQUE
school_id             BIGINT       FK INDEX
run_date              DATE         NOT NULL
scope                 VARCHAR(30)  NOT NULL -- gateway|bank|full
gateway_id            BIGINT       NULL FK
bank_account_id       BIGINT       NULL FK
gateway_total_minor   BIGINT       NULL
receipts_total_minor  BIGINT       NULL
bank_total_minor      BIGINT       NULL
gl_total_minor        BIGINT       NULL
currency              CHAR(3)      NOT NULL
variance_minor        BIGINT       NOT NULL DEFAULT 0
exception_count       INT          NOT NULL DEFAULT 0
exceptions            JSON         NULL
status                VARCHAR(20)  NOT NULL -- running|clean|exceptions|failed
ran_at                TIMESTAMP    NOT NULL
reviewed_by           BIGINT       NULL FK
reviewed_at           TIMESTAMP    NULL
  UNIQUE (school_id, run_date, scope, gateway_id, bank_account_id)
```

### 4. The driver contract

```php
interface PaymentGatewayDriver
{
    public function key(): string;
    public function supportedMethods(): array;
    public function supportedCurrencies(): array;

    /** Hosted checkout: returns a URL to redirect to. */
    public function createCheckout(PaymentIntent $intent): CheckoutResponse;

    /** Push flow: sends a prompt to the payer's phone. */
    public function createPush(PaymentIntent $intent, string $phone, string $method): PushResponse;

    public function poll(PaymentIntent $intent): PaymentStatus;
    public function verifyWebhook(array $headers, string $body): bool;
    public function parseWebhook(array $headers, string $body): WebhookEvent;

    public function refund(PaymentIntent $intent, Money $amount): RefundResponse;
    public function supportsDisbursement(): bool;
    public function disburse(DisbursementRequest $r): DisbursementResponse;

    public function healthCheck(): HealthStatus;
}
```

### 5. The four-way reconciliation ⭐

Run daily, unattended. It is the control that turns "we think the money arrived" into "we can prove it".

```
        GATEWAY                RECEIPTS                BANK                    GL
   settlement report      our receipt records    bank statement      journal lines
          │                       │                     │                   │
          └───────────┬───────────┴──────────┬──────────┴─────────┬─────────┘
                      ▼                      ▼                    ▼
              Gateway ↔ Receipts     Receipts ↔ Bank        Bank ↔ GL
                      │                      │                    │
                      └──────────────────────┴────────────────────┘
                                             ▼
                                    EXCEPTION REPORT
```

**Exception classes, each with its own resolution path:**

| Exception | Meaning | Resolution |
|---|---|---|
| `GATEWAY_NO_RECEIPT` | Gateway settled, we have no receipt | Missed webhook. Poll and create the receipt. |
| `RECEIPT_NO_GATEWAY` | We receipted, gateway shows nothing | Investigate urgently — possible fraudulent entry. |
| `AMOUNT_MISMATCH` | Same reference, different amount | Compare, correct by reversal. |
| `BANK_NO_RECEIPT` | Bank credit unmatched | → Suspense workflow (`FIN-04`). |
| `RECEIPT_NO_BANK` | Receipted but never banked | Cash not deposited. Escalate immediately. |
| `GL_VARIANCE` | Bank total ≠ GL bank account | Missing or duplicated journal. Critical. |
| `DUPLICATE_SETTLEMENT` | Same gateway reference twice | Idempotency failure. Investigate and reverse one. |
| `FEE_UNPOSTED` | Settlement net of fee, no fee journal | Post the gateway fee. |

**Nothing auto-resolves.** The job produces the exception list; a human clears each one, and the clearing is recorded. A reconciliation that silently fixes its own discrepancies is not a control.

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-05-001` | Every payment initiation requires a client-supplied `Idempotency-Key`. Repeating a key returns the original intent rather than creating a second one. Zimbabwean connectivity guarantees double submissions. |
| `BR-FIN-05-002` | A payment intent is **not** a receipt. No journal is posted until the gateway confirms settlement. |
| `BR-FIN-05-003` | Webhook signatures are verified before any processing. Invalid signatures are stored, flagged, and a security event raised. |
| `BR-FIN-05-004` | Webhook replay protection is by payload hash uniqueness per driver. Duplicates are recorded as `duplicate` and ignored. |
| `BR-FIN-05-005` | Raw webhook payloads are stored verbatim, append-only, for the audit retention period. When a gateway disputes what it sent, the raw payload settles it. |
| `BR-FIN-05-006` | Webhook processing is idempotent. Receiving the same settlement event five times creates exactly one receipt. |
| `BR-FIN-05-007` | Polling is the fallback for missed webhooks: pending intents poll on a decaying schedule (1, 2, 5, 10, 30 minutes) until settled, failed, or expired. |
| `BR-FIN-05-008` | Intents expire after `finance.payment_intent_ttl_minutes` (default 30) and are marked expired. A late settlement on an expired intent is still honoured, receipted, and flagged for review. |
| `BR-FIN-05-009` | On confirmed settlement, a receipt is created through `FIN-04` and allocated by the school's configured strategy. |
| `BR-FIN-05-010` | Gateway fees post to the fee expense account in the same journal as the settlement. The learner is credited the **gross**; the school bears the fee. |
| `BR-FIN-05-011` | Bank statement import auto-matches on reference, then amount and date proximity, then fuzzy description. Any match below `finance.auto_match_confidence_threshold` (default 85) is presented for human confirmation. |
| `BR-FIN-05-012` | Unmatched bank credits become suspense items in `FIN-04`. Money on the bank statement is always on the ledger. |
| `BR-FIN-05-013` | The four-way reconciliation runs daily and never auto-resolves an exception. |
| `BR-FIN-05-014` | An unresolved reconciliation exception blocks the financial period close. |
| `BR-FIN-05-015` | Refunds go through the approval chain, require an available credit balance, and post `Dr Fee Debtors / Cr Bank`. Where the driver supports disbursement, the refund can return to the original wallet. |
| `BR-FIN-05-016` | Gateway credentials are encrypted at rest, masked in the UI, redacted in logs and exports, and never returned by any API. |
| `BR-FIN-05-017` | Gateway health is checked every 5 minutes. A gateway marked `down` is hidden from the parent portal so parents are not sent into a broken flow. |
| `BR-FIN-05-018` | With multiple active gateways, the client is offered the choice; a school may pin a default per payment method. |
| `BR-FIN-05-019` | Sandbox and production credentials are strictly separated. A gateway in sandbox mode is visibly labelled everywhere it appears and cannot post to production accounts. |

### 7. Payment flow — parent app, EcoCash push

```
1  Parent opens Fees → Pay Now, selects amount and EcoCash
2  Flutter POSTs /api/v1/finance/payments/initiate with Idempotency-Key
3  Server creates intent (status=created), calls driver createPush()
4  Driver returns instructions; intent → pending
5  Parent receives the prompt on their phone and enters their PIN
6  Gateway POSTs the webhook → signature verified → parsed
7  Settlement confirmed:
      receipt created (FIN-04) → journal posted (FIN-01)
      → allocated to invoices → gateway fee journal posted
      → 🇿🇼 fiscalisation queued if any line is fiscalisable
      → confirmation SMS/WhatsApp/push to parent
8  App polls /payments/{ulid} or receives push; shows the receipt
9  If no webhook arrives, the polling job settles it within 30 minutes anyway
```

Step 9 is what makes this work in Zimbabwe. Webhooks fail. The polling fallback means a parent who paid always ends up receipted.

### 8. Screens

| Screen | Component | Permission |
|---|---|---|
| Gateway configuration | `Finance\Gateways\Index` | `finance.gateway.manage` ⚠ — credentials masked, per-gateway test button |
| Intent monitor | `Finance\Gateways\Intents` | `finance.gateway.view` — live status, manual poll, force-settle with approval |
| Webhook log | `Finance\Gateways\Webhooks` | `finance.gateway.view` — raw payload viewer, replay for failed processing |
| Bank accounts | `Finance\Bank\Accounts` | `finance.bank.manage` |
| Statement import | `Finance\Bank\Import` | `finance.bank.reconcile` — CSV/OFX with column mapping |
| **Matching workbench** | `Finance\Bank\Matching` | `finance.bank.reconcile` — split pane: statement lines against candidate receipts, confidence scores, one-click match, bulk match on high confidence |
| Reconciliation dashboard | `Finance\Reconciliation\Dashboard` | `finance.reconciliation.view` — the four totals side by side, variance, exception queue |
| Exception workbench | `Finance\Reconciliation\Exceptions` | `finance.reconciliation.resolve` — one exception per row, class, suggested action, resolution note |

### 9. API endpoints

```
GET  /api/v1/finance/payment-methods         → available methods for this school, live health
POST /api/v1/finance/payments/initiate       Idempotency-Key REQUIRED
     { amount_minor, currency, method, student, purpose, phone? }
GET  /api/v1/finance/payments/{ulid}         → status polling for the client
POST /api/v1/finance/payments/{ulid}/cancel

POST /webhooks/gateways/{driver}             PUBLIC, signature-verified, rate-limited
```

### 10. Permissions

```
finance.gateway.view          finance.gateway.manage ⚠
finance.gateway.force_settle ⚠⚠
finance.bank.view             finance.bank.manage
finance.bank.reconcile        finance.reconciliation.view
finance.reconciliation.resolve ⚠
finance.refund.request        finance.refund.approve ⚠
finance.disbursement.execute ⚠⚠
```

### 11. Settings

| Key | Type | Default |
|---|---|---|
| `finance.payment_intent_ttl_minutes` | int | `30` |
| `finance.auto_match_confidence_threshold` | int | `85` |
| `finance.gateway_health_check_minutes` | int | `5` |
| `finance.reconciliation_run_hour` | int | `4` |
| `finance.block_period_close_on_reconciliation_exceptions` | bool | `true` |
| `finance.gateway_fee_borne_by` | enum | `school` (vs `payer`) |
| `finance.min_online_payment_minor` | int | `100` |

### 12. Jobs

| Job | Schedule | Notes |
|---|---|---|
| `JOB-PollPendingIntents` | Every minute | Decaying schedule per intent |
| `JOB-ProcessWebhook` | On receipt | Priority queue; idempotent |
| `JOB-ExpireStaleIntents` | Every 10 min | |
| `JOB-CheckGatewayHealth` | Every 5 min | Updates health, hides down gateways from the portal |
| `JOB-RunDailyReconciliation` | Daily 04:00 | Four-way; produces exceptions |
| `JOB-AutoMatchBankStatement` | On import | High-confidence only |
| `JOB-AlertUnresolvedExceptions` | Daily 09:00 | Bursar digest |

### 13. Acceptance criteria

```gherkin
AC-FIN-05-001
  Given a payment is initiated with idempotency key K
  When the same request is submitted again with key K
  Then the original intent is returned
  And no second intent or receipt is created

AC-FIN-05-002
  Given a webhook arrives with an invalid signature
  Then it is stored, flagged, and a security event is raised
  And no receipt is created

AC-FIN-05-003
  Given the same settlement webhook is delivered five times
  Then exactly one receipt exists
  And four webhooks are recorded as duplicates

AC-FIN-05-004
  Given a payment settles but the webhook never arrives
  When the polling job runs
  Then the intent is settled within 30 minutes
  And a receipt is created
  And the parent is notified

AC-FIN-05-005
  Given a settlement of USD 500 with a USD 12.50 gateway fee
  Then the learner is credited USD 500
  And USD 12.50 posts to gateway fee expense
  And the bank account is debited USD 487.50 net
  And the journal balances

AC-FIN-05-006
  Given a bank statement credit that matches no receipt
  Then it becomes a suspense item
  And appears on the suspense workbench

AC-FIN-05-007
  Given the daily reconciliation finds a GATEWAY_NO_RECEIPT exception
  Then the exception is listed and NOT auto-resolved
  And the financial period cannot be closed until it is cleared
  And the clearing records who cleared it and how

AC-FIN-05-008
  Given the ContiPay health check reports 'down'
  Then that gateway is hidden from the parent payment screen
  And any remaining healthy gateway is offered instead

AC-FIN-05-009
  Given gateway credentials are configured
  When I view them in the UI, an export, or any API response
  Then they are masked or absent
  And they are encrypted at rest
```

---

## Part 3 — Domain D Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **D1** | `FIN-01` chart of accounts, cost centres, posting engine, balance derivation | A journal posts. Unbalanced journals are refused. DB grants revoked and verified. |
| **D2** | `FIN-01` reversal, trial balance, balance cache, integrity jobs | `AC-FIN-01-*` green. Nightly assertions running. |
| **D3** | `FIN-06` currencies, rates, conversion, conversion audit | Every journal line carries a rate. Missing-rate throws. |
| **D4** | `FIN-06` realised FX, revaluation, simulation | Revaluation posts; close checklist item wired. |
| **D5** | `FIN-02` components, structures, rule engine, resolution trace | Structure builder with live match count. Trace renders. |
| **D6** | `FIN-02` billing bases including `per_subject`, proration, tiering | **Full-time and part-time both compute correctly.** `AC-FIN-02-001..004` green. |
| **D7** | `FIN-02` billing run — compute, preview, variance, exceptions, approve, commit | No commit without approval. Exception report complete. |
| **D8** | `FIN-03` invoices, lines, split liability, credit notes | Split invoicing sums correctly per currency. Void-and-reissue works. |
| **D9** | `FIN-03` statements, aging, reminders, payment plans, waivers, write-offs | Historical statement reproducibility proven. |
| **D10** | `FIN-04` tills, sessions, receipt capture, tenders | Blind cash-up enforced server-side. Receipt in under 20 seconds. |
| **D11** | `FIN-04` allocation engine, suspense workbench, void, banking | Suspense blocks period close. Overpayment creates credit. |
| **D12** | `FIN-05` driver abstraction + ContiPay + Paynow, intents, webhooks | Idempotency and replay protection proven under load. |
| **D13** | `FIN-05` Pesepay push for Flutter, polling fallback, health checks | Missed webhook still settles within 30 minutes. |
| **D14** | `FIN-05` bank import, matching workbench, four-way reconciliation | Every exception class reproduced in tests and resolvable. |
| **D15** | Roll-over handlers: carry-forward, FX revaluation, invariant | **`AC-CORE-03-003` passes with real financial data.** |

Sprint D15 is the moment the product's central promise becomes demonstrable. Do not let it slip to the end of the project.

---

## Part 4 — Domain D Acceptance Gate

> No other domain that touches money begins until every item here is green.

### The five invariants

- [ ] **I-1** Trial balance balances for every school and currency across a seeded 3-year, 3-term, 1,400-learner dataset
- [ ] **I-2** Every learner balance derived from source equals its cached value, across the full dataset
- [ ] **I-3** For every term boundary in the dataset: `Σ closing = Σ opening`, per currency, with FX movement separately accounted
- [ ] **I-4** No path exists by which money is recorded outside the ledger — verified by code review and by the "receipt without journal" test
- [ ] **I-5** Every statement, invoice, and income statement regenerates byte-identically from an archived period

### Correctness

- [ ] All `AC-FIN-01-*` through `AC-FIN-06-*` pass
- [ ] Full-time billing produces a subject-count-independent charge
- [ ] Part-time billing produces `Σ(subject rates)` with tier bands, floors, and caps applied in the right order
- [ ] Mid-term subject add and drop produce correct pro-rated charge and credit with readable calculation notes
- [ ] Split liability across three parties sums exactly to the learner's charges in every currency
- [ ] Realised FX posts on cross-currency settlement; unrealised FX posts on revaluation
- [ ] A locked period rejects every write path from every module

### Controls

- [ ] `UPDATE` and `DELETE` confirmed revoked on `journal_lines`; `journals` restricted to the two permitted columns by trigger
- [ ] Blind cash-up cannot be circumvented by any request to any endpoint
- [ ] Suspense balance blocks period close
- [ ] Unresolved reconciliation exceptions block period close
- [ ] Billing cannot commit without recorded human approval
- [ ] Manual journals cannot be posted by their author alone
- [ ] Write-offs, credit notes, refunds, and period reopening all require approval
- [ ] Gateway credentials encrypted, masked, and absent from every API response

### Robustness

- [ ] 200 concurrent receipts produce 200 gapless, unique receipt numbers
- [ ] Duplicate webhook delivery ×5 produces exactly one receipt
- [ ] A payment settling with no webhook is receipted within 30 minutes by polling
- [ ] A receipt succeeds while the ZIMRA FDMS endpoint is unreachable, and queues for fiscalisation
- [ ] A billing run over 1,400 learners completes within 5 minutes and reports progress
- [ ] Killing the application mid-receipt leaves no orphaned journal, allocation, or number

### Quality

- [ ] **Coverage ≥ 90%** for every `FIN` module
- [ ] PHPStan level 8 clean
- [ ] Every business rule in this book has a named test referencing its rule ID
- [ ] Every finance pull request reviewed by a second developer against the Financial Integrity Charter

---

## Appendix A — Seeded Chart of Accounts (Zimbabwean school)

Abridged. The full seed ships in the `coa` pack.

```
1000  ASSETS
  1100  Cash & Bank
    1110  Cash on Hand — USD          system_key: cash_usd
    1115  Cash on Hand — ZWG
    1120  Bank — Current USD          currency: USD
    1125  Bank — Nostro USD
    1130  Bank — Current ZWG          currency: ZWG
    1140  Mobile Money Clearing
    1150  Gateway Settlement Clearing
  1200  Receivables
    1210  Fee Debtors Control         system_key: fee_debtors   subledger: learner
    1220  Sponsor Debtors Control     subledger: guardian
    1230  Staff Advances              subledger: staff
    1290  Suspense Account            system_key: suspense      ⭐
  1300  Inventory
    1310  Kitchen Store   1320  General Store   1330  Uniform Stock
    1340  Textbook Stock  1350  Farm Inputs
  1400  Fixed Assets
    1410  Land & Buildings   1420  Motor Vehicles   1430  Furniture & Equipment
    1440  Computer Equipment 1450  Livestock
    1490  Accumulated Depreciation (contra)

2000  LIABILITIES
  2100  Payables
    2110  Trade Creditors Control     subledger: supplier
    2120  Accruals
  2200  Statutory                     🇿🇼
    2210  PAYE Payable       2215  AIDS Levy Payable
    2220  NSSA Payable       2225  NSSA APWCS Payable
    2230  ZIMDEF Payable     2240  NEC Dues Payable
    2250  VAT Payable        2260  Withholding Tax Payable
  2300  Deferred & Prepaid
    2310  Fees Received in Advance    system_key: fee_credits
    2320  Refundable Deposits
  2400  Payroll
    2410  Net Salaries Payable  2420  Staff Loan Deductions

3000  EQUITY
  3100  Accumulated Fund   3200  Retained Surplus   system_key: retained_earnings
  3300  Opening Balance Control       system_key: opening_balance_control
  3400  Capital Reserves

4000  INCOME
  4100  Fee Income
    4110  Tuition — Full-Time   4115  Tuition — Part-Time (per subject)
    4120  Development Levy      4130  Boarding Fees
    4140  Examination Fees      4150  Transport Fees
    4160  Activity & Sports     4170  Registration & Admission
    4190  Fee Discounts (contra)      system_key: fee_discount_contra  ⭐
  4200  Other Income
    4210  Tuckshop Sales        4220  Uniform Sales
    4230  Facility Hire         4240  Farm Produce Sales
    4250  Donations & Bequests  4260  Interest Received
  4900  FX & Adjustments
    4910  Realised FX Gain      system_key: fx_realised_gain
    4920  Unrealised FX Gain    system_key: fx_unrealised_gain

5000  EXPENSES
  5100  Staff Costs
    5110  Salaries & Wages   5120  Employer NSSA   5130  ZIMDEF Levy
    5140  Staff Welfare      5150  Training & CPD
  5200  Academic
    5210  Textbooks & Stationery   5220  Laboratory Consumables
    5230  Examination Costs        5240  Library
  5300  Boarding & Catering
    5310  Food & Provisions   5320  Linen & Bedding   5330  Laundry
  5400  Estates & Utilities
    5410  Electricity (ZESA)   5415  Generator Diesel   5420  Water & Borehole
    5430  Repairs & Maintenance 5440  Grounds
  5500  Transport
    5510  Fuel   5520  Vehicle Maintenance
    5530  Licensing, ZINARA & Insurance
  5600  Administration
    5610  Bank Charges   5615  Payment Gateway Fees
    5620  Communication (SMS/WhatsApp)   5630  Software & Subscriptions
    5640  Professional Fees   5650  Insurance
  5900  Provisions & Adjustments
    5910  Bad Debt Expense      system_key: bad_debt
    5920  Realised FX Loss      system_key: fx_realised_loss
    5930  Unrealised FX Loss    system_key: fx_unrealised_loss
    5940  Cash Over / Short     system_key: cash_over_short     ⭐
    5950  Rounding Differences  system_key: rounding            ⭐
    5960  Prior Period Adjustments  system_key: prior_period_adjustment  ⭐
```

The four starred accounts at the bottom of 5900, plus Suspense at 1290 and Fee Discounts at 4190, are the accounts that make the difference between a system that balances and one that appears to.

---

## Appendix B — Next Book

**Book C — Domain B People (`PPL-01` → `PPL-04`)** covers the Student Information System, Admissions and Enrolment CRM, Guardian and Family Management, and Staff and HR. It consumes the fee liability model defined here and supplies the learner attributes the billing rule engine matches against.

---

*End of Volume 2, Book B.*

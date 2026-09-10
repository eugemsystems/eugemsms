# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book H1 — Domain D: Procurement, Stores, Assets & Budgets (`FIN-08` → `FIN-11`)

| Field | Value |
|---|---|
| Document | Volume 2, Book H1 of 10 |
| Covers | Procurement & Accounts Payable · Inventory & Stores · Fixed Assets & Depreciation · Budgeting & Commitment Accounting |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Book B `FIN-01`, `FIN-06` complete.** Book F `BRD-04` benefits immediately. |
| Next book | Book H2 — Operations & Estates (`OPS-01` → `OPS-07`) |

---

## Part 0 — What This Book Closes

### 0.1 The Book F dependency

Book F §0.3 stated the honest position: `BRD-04` catering costs food issued from the kitchen store, and store mechanics live here. `FIN-09` in this book implements `StoreIssuanceProvider` in full, across every store type. Catering costing lights up the moment it ships.

| Interface | Opened in | Closed by |
|---|---|---|
| `StoreIssuanceProvider` | Book F `BRD-04` §4 | `FIN-09` §5 |
| Damage repair work orders | Book F `BRD-01` | `OPS-02`, Book H2 |
| Farm-to-kitchen transfer | Book F `BRD-04` | `OPS-03`, Book H2 |

### 0.2 ⭐ Why this book matters in Zimbabwe specifically

Two controls in this book address problems that are acute in this market and largely absent from generic ERP.

**Commitment accounting.** A department raises a purchase order in March; the invoice arrives in May. Between those dates, most systems show the budget as unspent — so three more departments spend it. `FIN-11` consumes budget the moment a purchase order is approved. This is the single most requested control from bursars who have run a school through a term of unexplained overspend.

**🇿🇼 Fiscal tax invoice capture.** ZIMRA permits VAT input tax claims only where the claim is supported by a fiscal tax invoice produced by a device connected to the Fiscalisation Data Management System. A school that accepts a non-fiscalised invoice from a supplier has lost the input tax, usually without noticing until the accountant reconciles at year end. `FIN-08` captures fiscal status at invoice registration and reports the exposure in real time.

**And one that addresses theft.** Fuel, mealie-meal, cooking oil, sugar and meat are the highest-shrinkage items in a Zimbabwean boarding school. `FIN-09`'s consumption anomaly detection exists because the pattern — issues rising while occupancy falls — is visible in data long before it is visible in the store.

### 0.3 Build order

```
FIN-09  Inventory & Stores        ← ⭐ closes the Book F dependency; build first
   ↓
FIN-08  Procurement & AP          ← feeds goods receipts into stores
   ↓
FIN-10  Fixed Assets              ← capitalises from procurement
   ↓
FIN-11  Budgeting & Commitment    ← constrains procurement; build last, wire back
```

`FIN-11` is built last but **wires backwards** into `FIN-08`: once budgets exist, requisitions check against them. Plan for that rework in the `FIN-08` estimate rather than discovering it.

---

# FIN-09 · Inventory, Stores & Requisitions ⭐

### 1. Scope

**In scope.** Multi-store structure, item master, unit of measure conversion, FIFO and weighted-average costing, goods receipt, internal requisition and issue with automatic expense recognition, inter-store transfer, stock take and adjustment, batch and expiry tracking, shrinkage and anomaly detection, saleable stock linked to learner accounts.

**Out of scope.** Purchasing (`FIN-08`). Capitalised assets (`FIN-10` — the boundary is in §3). Recipe scaling (`BRD-04`).

### 2. Data model

```sql
stores
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL   -- 'MAIN','KITCHEN','LAB','FARM'
name                    VARCHAR(120) NOT NULL
store_type              VARCHAR(30)  NOT NULL   -- main|kitchen|boarding|laboratory|
                                                -- workshop|farm|clinic|uniform|
                                                -- textbook|fuel|maintenance
custodian_staff_id      BIGINT       NULL FK
cost_centre_id          BIGINT       FK → cost_centres.id
inventory_account_id    BIGINT       FK → accounts.id     -- asset account
default_expense_account_id BIGINT    FK → accounts.id     -- on issue
location                VARCHAR(150) NULL
costing_method          VARCHAR(20)  NOT NULL DEFAULT 'fifo'  -- fifo|weighted_average
requires_issue_approval TINYINT(1)   NOT NULL DEFAULT 0
allows_negative_stock   TINYINT(1)   NOT NULL DEFAULT 0
is_active               TINYINT(1)   NOT NULL DEFAULT 1
created_by, created_at, updated_at
  UNIQUE (school_id, code)
  INDEX  (school_id, store_type, is_active)

inventory_items
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL
name                    VARCHAR(200) NOT NULL
description             VARCHAR(500) NULL
category_id             BIGINT       FK
base_unit               VARCHAR(20)  NOT NULL   -- kg|litre|each|metre|box
purchase_unit           VARCHAR(20)  NULL       -- how it's bought
purchase_conversion     DECIMAL(12,6) NOT NULL DEFAULT 1   -- 1 bag = 50 kg
issue_unit              VARCHAR(20)  NULL
issue_conversion        DECIMAL(12,6) NOT NULL DEFAULT 1
is_perishable           TINYINT(1)   NOT NULL DEFAULT 0
requires_batch_tracking TINYINT(1)   NOT NULL DEFAULT 0
shelf_life_days         SMALLINT     NULL
is_high_risk            TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ theft-prone
is_saleable             TINYINT(1)   NOT NULL DEFAULT 0   -- uniform, textbooks
sale_price_minor        BIGINT       NULL
sale_currency           CHAR(3)      NULL
sale_fee_component_id   BIGINT       NULL FK    -- FIN-02, for learner charging
is_capitalisable        TINYINT(1)   NOT NULL DEFAULT 0   -- → FIN-10 on issue
capitalisation_threshold_minor BIGINT NULL
expense_account_id      BIGINT       NULL FK    -- overrides store default
preferred_supplier_id   BIGINT       NULL FK
standard_cost_minor     BIGINT       NULL       -- for variance reporting
standard_cost_currency  CHAR(3)      NULL
barcode                 VARCHAR(60)  NULL
image_file_id           BIGINT       NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, code)
  INDEX  (school_id, category_id, is_active)
  INDEX  (school_id, is_high_risk)
  FULLTEXT (name, description)

item_categories
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
parent_id               BIGINT       NULL FK
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
default_expense_account_id BIGINT    NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

store_item_settings                   -- per item per store
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
store_id                BIGINT       FK INDEX
item_id                 BIGINT       FK INDEX
reorder_level           DECIMAL(14,4) NULL
reorder_quantity        DECIMAL(14,4) NULL
maximum_level           DECIMAL(14,4) NULL
bin_location            VARCHAR(60)  NULL
is_stocked              TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (store_id, item_id)

stock_lots                            -- ⭐ FIFO layers; APPEND-ONLY on quantity_in
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
store_id                BIGINT       FK INDEX
item_id                 BIGINT       FK INDEX
lot_reference           VARCHAR(60)  NOT NULL
batch_number            VARCHAR(60)  NULL
received_on             DATE         NOT NULL
expiry_date             DATE         NULL
quantity_received       DECIMAL(14,4) NOT NULL
quantity_remaining      DECIMAL(14,4) NOT NULL
unit_cost_minor         BIGINT       NOT NULL     -- landed cost per base unit
currency                CHAR(3)      NOT NULL
base_unit_cost_minor    BIGINT       NOT NULL     -- converted to school base
exchange_rate_id        BIGINT       NULL FK
source_type             VARCHAR(30)  NOT NULL     -- goods_receipt|transfer_in|
                                                  -- adjustment|opening|production
source_id               BIGINT       NULL
supplier_id             BIGINT       NULL FK
is_depleted             TINYINT(1)   NOT NULL DEFAULT 0
  INDEX (school_id, store_id, item_id, is_depleted, received_on)   -- ⭐ FIFO query
  INDEX (school_id, expiry_date, is_depleted)

stock_movements                       -- APPEND-ONLY, the source of truth
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
store_id                BIGINT       FK INDEX
item_id                 BIGINT       FK INDEX
lot_id                  BIGINT       NULL FK
movement_type           VARCHAR(30)  NOT NULL     -- receipt|issue|return|
                                                  -- transfer_out|transfer_in|
                                                  -- adjustment_up|adjustment_down|
                                                  -- write_off|sale|production_in
direction               CHAR(3)      NOT NULL     -- in | out
quantity                DECIMAL(14,4) NOT NULL    -- always positive
unit_cost_minor         BIGINT       NOT NULL
total_cost_minor        BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
base_total_minor        BIGINT       NOT NULL
balance_after           DECIMAL(14,4) NOT NULL    -- running balance, this store+item
source_type             VARCHAR(60)  NULL
source_id               BIGINT       NULL
cost_centre_id          BIGINT       NULL FK
expense_account_id      BIGINT       NULL FK
journal_id              BIGINT       NULL FK      -- ⭐ FIN-01 linkage
reference               VARCHAR(120) NULL
notes                   VARCHAR(255) NULL
performed_by            BIGINT       FK → users.id
occurred_at             TIMESTAMP(6) NOT NULL
  INDEX (school_id, store_id, item_id, occurred_at)
  INDEX (school_id, term_id, movement_type)
  INDEX (source_type, source_id)
  -- DB grants: INSERT, SELECT only. No UPDATE. No DELETE.

stock_balances                        -- CACHE ONLY, rebuilt from movements
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
store_id                BIGINT       FK
item_id                 BIGINT       FK
quantity_on_hand        DECIMAL(14,4) NOT NULL DEFAULT 0
quantity_committed      DECIMAL(14,4) NOT NULL DEFAULT 0   -- requisitioned, not issued
quantity_available      DECIMAL(14,4) NOT NULL DEFAULT 0
value_minor             BIGINT       NOT NULL DEFAULT 0
currency                CHAR(3)      NOT NULL
average_unit_cost_minor BIGINT       NULL
last_movement_id        BIGINT       NULL         -- rebuild watermark
last_received_on        DATE         NULL
last_issued_on          DATE         NULL
rebuilt_at              TIMESTAMP
  UNIQUE (school_id, store_id, item_id)
  INDEX  (school_id, store_id, quantity_on_hand)

store_requisitions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
requisition_number      VARCHAR(40)  NOT NULL     -- gapless, CORE-06
store_id                BIGINT       FK
requesting_department_id BIGINT      NULL FK
cost_centre_id          BIGINT       FK
purpose                 VARCHAR(255) NOT NULL
required_by             DATE         NULL
source_type             VARCHAR(40)  NULL         -- meal_service|work_order|
                                                  -- clinic|manual|classroom
source_id               BIGINT       NULL
status                  VARCHAR(20)  NOT NULL     -- draft|pending|approved|
                                                  -- partially_issued|issued|
                                                  -- rejected|cancelled
approval_request_id     BIGINT       NULL FK
requested_by            BIGINT       FK → users.id
approved_by             BIGINT       NULL FK
issued_by               BIGINT       NULL FK
issued_at               TIMESTAMP    NULL
received_by             BIGINT       NULL FK      -- who collected
total_cost_minor        BIGINT       NULL
currency                CHAR(3)      NOT NULL
journal_id              BIGINT       NULL FK
  UNIQUE (school_id, requisition_number)
  INDEX  (school_id, store_id, status)

store_requisition_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
requisition_id          BIGINT       FK INDEX
item_id                 BIGINT       FK
quantity_requested      DECIMAL(14,4) NOT NULL
quantity_approved       DECIMAL(14,4) NULL
quantity_issued         DECIMAL(14,4) NULL
quantity_returned       DECIMAL(14,4) NULL
unit                    VARCHAR(20)  NOT NULL
unit_cost_minor         BIGINT       NULL
line_cost_minor         BIGINT       NULL
substituted_item_id     BIGINT       NULL FK
substitution_note       VARCHAR(255) NULL
notes                   VARCHAR(255) NULL

stock_transfers
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
transfer_number         VARCHAR(40)  NOT NULL
from_store_id           BIGINT       FK
to_store_id             BIGINT       FK
reason                  VARCHAR(255) NOT NULL
status                  VARCHAR(20)  NOT NULL     -- draft|in_transit|received|
                                                  -- discrepancy|cancelled
dispatched_by           BIGINT       NULL FK
dispatched_at           TIMESTAMP    NULL
received_by             BIGINT       NULL FK
received_at             TIMESTAMP    NULL
discrepancy_note        TEXT         NULL
journal_id              BIGINT       NULL FK
  UNIQUE (school_id, transfer_number)

stock_takes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
store_id                BIGINT       FK INDEX
take_number             VARCHAR(40)  NOT NULL
take_type               VARCHAR(20)  NOT NULL     -- full|cycle|spot
scheduled_for           DATE         NOT NULL
counted_on              DATE         NULL
status                  VARCHAR(20)  NOT NULL     -- planned|counting|
                                                  -- variance_review|approved|
                                                  -- posted|cancelled
is_blind_count          TINYINT(1)   NOT NULL DEFAULT 1   -- ⭐
total_variance_minor    BIGINT       NULL
line_count              SMALLINT     NOT NULL DEFAULT 0
variance_line_count     SMALLINT     NOT NULL DEFAULT 0
counted_by              BIGINT       NULL FK
verified_by             BIGINT       NULL FK      -- second person
approved_by             BIGINT       NULL FK
approval_request_id     BIGINT       NULL FK
journal_id              BIGINT       NULL FK
  UNIQUE (school_id, take_number)

stock_take_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
stock_take_id           BIGINT       FK INDEX
item_id                 BIGINT       FK
system_quantity         DECIMAL(14,4) NOT NULL    -- ⭐ hidden until counted
counted_quantity        DECIMAL(14,4) NULL
recount_quantity        DECIMAL(14,4) NULL
variance_quantity       DECIMAL(14,4) NULL
variance_value_minor    BIGINT       NULL
variance_percent        DECIMAL(6,2) NULL
variance_reason         VARCHAR(255) NULL
requires_recount        TINYINT(1)   NOT NULL DEFAULT 0
counted_by              BIGINT       NULL FK
counted_at              TIMESTAMP    NULL

consumption_baselines                 -- ⭐ anomaly detection
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
store_id                BIGINT       FK
item_id                 BIGINT       FK
period_type             VARCHAR(20)  NOT NULL     -- daily|weekly|per_boarder_day
expected_quantity       DECIMAL(14,4) NOT NULL
tolerance_percent       DECIMAL(5,2) NOT NULL DEFAULT 15
computed_from_days      SMALLINT     NOT NULL
last_computed_at        TIMESTAMP
  UNIQUE (store_id, item_id, period_type)

consumption_anomalies
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
store_id                BIGINT       FK
item_id                 BIGINT       FK
period_start            DATE         NOT NULL
period_end              DATE         NOT NULL
expected_quantity       DECIMAL(14,4) NOT NULL
actual_quantity         DECIMAL(14,4) NOT NULL
variance_percent        DECIMAL(6,2) NOT NULL
occupancy_factor        DECIMAL(6,2) NULL         -- boarders present vs baseline
severity                VARCHAR(20)  NOT NULL     -- low|medium|high
status                  VARCHAR(20)  NOT NULL     -- flagged|investigating|
                                                  -- explained|escalated
investigation_note      TEXT         NULL
reviewed_by             BIGINT       NULL FK
detected_at             TIMESTAMP    NOT NULL
  INDEX (school_id, status, severity)
```

### 3. ⭐ The inventory / fixed asset boundary

A recurring source of confusion. The rule is simple and configurable:

```
Item received into stores
        │
        ├─ item.is_capitalisable = 1
        │     AND unit cost ≥ capitalisation_threshold
        │     ────────────────────────────────────────►  ON ISSUE, creates a
        │                                                 FIN-10 asset record.
        │                                                 Dr Fixed Assets / Cr Inventory
        │
        └─ otherwise ───────────────────────────────────►  ON ISSUE, expensed.
                                                           Dr Expense / Cr Inventory
```

A laptop bought for the ICT lab sits in stores as inventory, and becomes a tracked fixed asset the moment it is issued to a department. A ream of paper is expensed. The threshold is per school, and the item flag overrides it in both directions.

### 4. ⭐ FIFO issue and automatic expense recognition

The original specification asked for this in one line: *"when a school item is flagged as issued for use, the system automatically writes an expense entry to the ledger."* Here is what that actually requires.

```php
public function issue(StoreRequisition $req): IssueResult
{
    return DB::transaction(function () use ($req) {

        PeriodGuard::assertFinancialWritable($req->term);

        $journalLines = collect();

        foreach ($req->lines as $line) {
            $remaining = $line->quantity_approved;
            $lineCost  = Money::zero($req->currencyEnum());

            // ⭐ FIFO: consume oldest lots first
            $lots = StockLot::where('store_id', $req->store_id)
                ->where('item_id', $line->item_id)
                ->where('is_depleted', false)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_on')          // FIFO
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) break;

                $take = min($remaining, $lot->quantity_remaining);
                $cost = Money::of((int) round($take * $lot->base_unit_cost_minor),
                                  $this->baseCurrency);

                $this->recordMovement($req, $line, $lot, $take, $cost, 'issue', 'out');

                $lot->decrement('quantity_remaining', $take);
                if ($lot->quantity_remaining <= 0) $lot->update(['is_depleted' => true]);

                $lineCost  = $lineCost->plus($cost);
                $remaining -= $take;
            }

            if ($remaining > 0) {
                if (! $req->store->allows_negative_stock) {
                    throw new InsufficientStockException($line->item, $remaining);
                }
                // Negative stock issues at the last known cost, and is flagged.
                $lineCost = $lineCost->plus($this->issueAtLastKnownCost($line, $remaining));
            }

            // ⭐ Expense recognition — the requesting cost centre bears it
            $journalLines->push(JournalLine::debit(
                account:    $this->expenseAccountFor($line->item, $req->store),
                amount:     $lineCost,
                costCentre: $req->cost_centre_id,
                narration:  "Issue {$line->item->name} — {$req->purpose}",
            ));

            $journalLines->push(JournalLine::credit(
                account: $req->store->inventory_account_id,
                amount:  $lineCost,
            ));

            $line->update(['quantity_issued' => $line->quantity_approved,
                           'line_cost_minor' => $lineCost->minor]);
        }

        // One journal per requisition, not per line
        $journal = $this->postJournal->execute(new PostJournalData(
            type:        JournalType::StockIssue,
            narration:   "Store issue {$req->requisition_number}",
            lines:       $journalLines->groupByAccountAndCostCentre(),
            effectiveAt: now()->toDateString(),
            sourceType:  StoreRequisition::class,
            sourceId:    $req->id,
        ));

        $req->update(['status' => 'issued', 'journal_id' => $journal->id]);

        event(new StockIssued($req));

        return new IssueResult($journal, $req->total_cost_minor);
    });
}
```

**Three details that matter.** Lots are locked for update, so two concurrent issues cannot consume the same layer. The journal is one per requisition with lines grouped by account and cost centre, not one journal per item — a kitchen requisition of forty items produces one readable journal, not forty. And expense lands on the **requesting** cost centre, not the store's, so the boarding department carries the cost of the food it eats.

### 5. ⭐ `StoreIssuanceProvider` — the Book F interface, implemented

```php
final class StoreIssuanceService implements StoreIssuanceProvider
{
    public function checkAvailability(Collection $lines, Store $store): AvailabilityReport
    {
        // Per line: on hand, committed, available, shortfall, substitutes, expiry warnings
    }

    public function createRequisition(MealService $s, Collection $lines): StoreRequisition
    {
        // source_type = 'meal_service', source_id = $s->id
        // cost centre = catering; approval per store setting
    }

    public function issue(StoreRequisition $r, Collection $actual): IssueResult
    {
        // §4 above
    }

    public function recordReturn(StoreRequisition $r, Collection $returned): void
    {
        // Reverses proportionally at the SAME lot costs the issue consumed
    }

    public function currentCost(int $itemId, Store $store): Money
    {
        // Weighted average of remaining lots — for planning, not for posting
    }
}
```

**Returns reverse at the original lot cost**, not at current cost. Issuing 100kg at $0.80 and returning 20kg must credit $16.00, not 20kg at today's $0.95. Getting this wrong creates a slow, invisible drift between the inventory account and physical stock.

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-09-001` | `stock_movements` is append-only, enforced at database grant level. Corrections are new movements, never edits. |
| `BR-FIN-09-002` | `stock_balances` is a cache derived from movements, rebuilt nightly and verified against source. Nothing writes to it directly. |
| `BR-FIN-09-003` | Every movement that changes value posts a journal through `FIN-01` in the same transaction. Stock and the ledger cannot diverge. |
| `BR-FIN-09-004` | FIFO consumes lots by `received_on` then `id`, with row locking. Weighted average is available as a per-store alternative and, once set with movements present, cannot be changed. |
| `BR-FIN-09-005` | Issuing more than is on hand is refused unless the store permits negative stock, in which case it issues at last known cost and flags the movement for review. |
| `BR-FIN-09-006` ⭐ | Issue recognises expense immediately against the **requesting** cost centre, not the store's. |
| `BR-FIN-09-007` | One journal per requisition, with lines aggregated by account and cost centre. |
| `BR-FIN-09-008` | Returns reverse at the lot costs the original issue consumed. |
| `BR-FIN-09-009` | Items with `requires_batch_tracking = 1` cannot be received without a batch number, and cannot be issued without selecting a lot. |
| `BR-FIN-09-010` | Perishable items issue by earliest expiry rather than earliest receipt, where those differ. |
| `BR-FIN-09-011` | Expired stock cannot be issued. It is written off through the adjustment path with approval, posting to a wastage account. |
| `BR-FIN-09-012` | Expiry alerts fire at the configured intervals and appear on the storekeeper's dashboard. |
| `BR-FIN-09-013` | Unit conversions between purchase, base and issue units are exact and stored on the item. A 50kg bag received and 2.5kg issued must reconcile to the gram. |
| `BR-FIN-09-014` ⭐ | Stock takes are **blind by default**: `system_quantity` is not visible to the counter until their count is submitted. |
| `BR-FIN-09-015` | Variance beyond the configured percentage requires a recount by a different person before it can be approved. |
| `BR-FIN-09-016` | Approved stock take variances post an adjustment journal to the shrinkage account with the reason recorded. Variances are never silently absorbed. |
| `BR-FIN-09-017` | High-risk items (`is_high_risk = 1`) require a full count at every stock take regardless of the cycle plan. |
| `BR-FIN-09-018` | Inter-store transfers create paired out and in movements. Stock in transit belongs to neither store's available balance until received. |
| `BR-FIN-09-019` | A transfer received with a quantity discrepancy sets status `discrepancy`, alerts both custodians, and blocks completion until resolved. |
| `BR-FIN-09-020` | Saleable items issued to a learner (uniform, textbook) create an ad hoc charge in `FIN-02` at `sale_price_minor`, and post cost of sales separately from the revenue. |
| `BR-FIN-09-021` | Items breaching the capitalisation rule (§3) create a `FIN-10` asset on issue rather than an expense. |
| `BR-FIN-09-022` ⭐ | Consumption baselines are computed per item per store, normalised **per boarder-day** where the store serves boarding. Actual consumption beyond tolerance raises an anomaly. |
| `BR-FIN-09-023` | An anomaly requires investigation and a recorded explanation. It is never auto-dismissed, and unexplained anomalies on high-risk items escalate to the bursar. |
| `BR-FIN-09-024` | Reorder level breaches raise a purchase requisition suggestion in `FIN-08`, not an automatic order. |
| `BR-FIN-09-025` | A store's financial period must be open to receive or issue. Stock movements obey `PeriodGuard` like every other financial write. |
| `BR-FIN-09-026` | Opening stock import posts real journals through `FIN-01` and creates lots at stated cost. It never writes balance rows directly. |

### 7. Screens

| Screen | Component | Permission |
|---|---|---|
| Store setup | `Stores\Stores\Index` | `inventory.store.manage` |
| Item master | `Stores\Items\Index` | `inventory.item.view` — categories, units, conversions, flags |
| Item editor | `Stores\Items\Editor` | `inventory.item.manage` |
| **Stock on hand** | `Stores\Stock\OnHand` | `inventory.stock.view` — by store, value, days of cover, reorder flags |
| Item ledger | `Stores\Stock\ItemLedger` | `inventory.stock.view` — every movement, running balance, drill to journal |
| Goods receipt | `Stores\Receipts\Create` | `inventory.receipt.create` — from PO or direct, batch and expiry capture |
| **Requisition** | `Stores\Requisitions\Create` | `inventory.requisition.create` — item search, availability shown live, cost centre |
| **Issue** | `Stores\Requisitions\Issue` | `inventory.issue` — FIFO lots shown, substitutions, one-tap issue |
| Returns | `Stores\Requisitions\Return` | `inventory.issue` |
| Transfers | `Stores\Transfers\Index` | `inventory.transfer.manage` — dispatch, receive, discrepancy |
| **Stock take** | `Stores\StockTake\Count` | `inventory.stocktake.count` ⭐ — blind entry, mobile with barcode scan |
| Variance review | `Stores\StockTake\Variance` | `inventory.stocktake.approve` ⚠ — recount queue, reasons, value impact |
| Expiry monitor | `Stores\Stock\Expiry` | `inventory.stock.view` |
| **Anomaly review** | `Stores\Anomalies\Index` | `inventory.anomaly.review` ⭐ — flagged consumption, occupancy overlay |
| Valuation report | `Stores\Reports\Valuation` | `inventory.report.view` — by store, category, as-at date |
| Consumption report | `Stores\Reports\Consumption` | `inventory.report.view` — per cost centre, per boarder-day |

**The stock take screen must hide the system figure.** Not greyed out, not behind a toggle — absent from the payload until the count is submitted. A blind count where the counter can reach the expected number is not a blind count.

### 8. API endpoints

```
GET  /api/v1/inventory/items                 ?store=&search=   mobile lookup
GET  /api/v1/inventory/stock                 ?store=&item=
POST /api/v1/inventory/requisitions          mobile requisition
GET  /api/v1/inventory/requisitions/pending  storekeeper queue
POST /api/v1/inventory/requisitions/{ulid}/issue
POST /api/v1/inventory/stock-takes/{ulid}/count   Idempotency-Key; blind
GET  /api/v1/inventory/stock-takes/{ulid}/sheet   count sheet, no system figures ⭐
```

### 9. Permissions · Settings · Events

```
inventory.store.view            inventory.store.manage
inventory.item.view             inventory.item.manage
inventory.stock.view            inventory.receipt.create
inventory.requisition.create    inventory.requisition.approve
inventory.issue                 inventory.transfer.manage
inventory.stocktake.count       inventory.stocktake.approve ⚠
inventory.adjustment.post ⚠     inventory.anomaly.review
inventory.report.view
```

| Setting | Type | Default |
|---|---|---|
| `inventory.default_costing_method` | enum | `fifo` |
| `inventory.capitalisation_threshold_minor` | int | `50000` (USD 500) |
| `inventory.stocktake_blind` | bool | `true` (**locked**) |
| `inventory.stocktake_recount_variance_percent` | int | `5` |
| `inventory.expiry_alert_days` | array | `[90,30,7]` |
| `inventory.anomaly_tolerance_percent` | int | `15` |
| `inventory.anomaly_baseline_days` | int | `60` |
| `inventory.allow_negative_stock` | bool | `false` |
| `inventory.high_risk_full_count_always` | bool | `true` |

Events: `StockReceived` · `StockIssued` ⭐ · `StockReturned` · `StockTransferred` · `TransferDiscrepancy` ⚠ · `StockTakeApproved` · `StockAdjustmentPosted` ⚠ · `ReorderLevelBreached` · `ExpiryApproaching` · `ConsumptionAnomalyDetected` ⚠ · `NegativeStockIssued` ⚠

### 10. Acceptance criteria

```gherkin
AC-FIN-09-001
  Given lots of 100kg at $0.80 and 200kg at $0.95, received in that order
  When 150kg is issued
  Then 100kg consumes the first lot and 50kg the second
  And the journal debits $127.50 to the requesting cost centre

AC-FIN-09-002
  Given a kitchen requisition of 40 items is issued
  Then exactly one journal is posted
  With lines aggregated by account and cost centre

AC-FIN-09-003
  Given 100kg was issued at $0.80 and 20kg is returned
  Then $16.00 is credited
  Not 20kg at the current cost

AC-FIN-09-004
  Given two concurrent issues for the same item and store
  Then no lot is consumed twice
  And both issues produce correct costs

AC-FIN-09-005
  Given a stock take is in progress
  When the counter requests the count sheet
  Then the system quantity is absent from the response payload

AC-FIN-09-006
  Given a counted variance of 8% and a recount threshold of 5%
  Then a recount by a different person is required before approval

AC-FIN-09-007
  Given a stock take variance is approved
  Then an adjustment journal posts to the shrinkage account
  With the recorded reason

AC-FIN-09-008
  Given mealie-meal consumption is 30% above the per-boarder-day baseline
  While boarder occupancy fell 10%
  Then a high-severity anomaly is raised
  And it cannot be dismissed without a recorded explanation

AC-FIN-09-009
  Given an item with unit cost above the capitalisation threshold is issued
  Then a FIN-10 asset record is created
  And the journal debits Fixed Assets, not an expense account

AC-FIN-09-010
  Given a uniform item is issued to a learner
  Then an ad hoc charge is raised in FIN-02 at the sale price
  And cost of sales posts separately from the revenue

AC-FIN-09-011
  Given expired stock exists
  When issue is attempted
  Then it is refused
  And the write-off path requires approval

AC-FIN-09-012
  Given the financial period is locked
  When a store issue is attempted
  Then PeriodLockedException is thrown and no movement is created
```

---

# FIN-08 · Procurement, Suppliers & Accounts Payable 🇿🇼

### 1. Scope

**In scope.** Supplier master, tax clearance tracking and withholding, purchase requisitions with budget checking, quotation comparison, purchase orders, goods receipt notes, three-way matching, supplier invoice registration with fiscal capture, payment scheduling, supplier statements, contract register, spend analytics.

**Out of scope.** Stock mechanics (`FIN-09`). Payment execution mechanics (`FIN-05` gateways, `FIN-01` journals). Budget definition (`FIN-11`).

### 2. Data model

```sql
suppliers
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(200) NOT NULL
trading_name            VARCHAR(200) NULL
supplier_type           VARCHAR(30)  NOT NULL    -- company|sole_trader|
                                                 -- individual|government|ngo
-- 🇿🇼 statutory identifiers
bp_number               VARCHAR(30)  NULL        -- ZIMRA Business Partner number
vat_number              VARCHAR(30)  NULL
company_registration    VARCHAR(40)  NULL
is_vat_registered       TINYINT(1)   NOT NULL DEFAULT 0
-- contact
contact_person          VARCHAR(150) NULL
phone                   VARCHAR(30)  NULL
email                   VARCHAR(150) NULL
address_line_1          VARCHAR(200) NULL
city                    VARCHAR(100) NULL
country                 CHAR(2)      NOT NULL DEFAULT 'ZW'
-- banking
bank_name               VARCHAR(80)  NULL
bank_branch             VARCHAR(80)  NULL
account_number          VARCHAR(40)  NULL        -- ENCRYPTED
account_name            VARCHAR(200) NULL
swift_code              VARCHAR(20)  NULL
mobile_money_number     VARCHAR(30)  NULL        -- 🇿🇼 EcoCash business
preferred_currency      CHAR(3)      NOT NULL
-- commercial
payment_terms_days      SMALLINT     NOT NULL DEFAULT 30
credit_limit_minor      BIGINT       NULL
credit_limit_currency   CHAR(3)      NULL
category_ids            JSON         NULL
control_account_id      BIGINT       FK → accounts.id
-- performance
rating                  DECIMAL(3,2) NULL        -- 0.00–5.00, computed
on_time_delivery_pct    DECIMAL(5,2) NULL
quality_rejection_pct   DECIMAL(5,2) NULL
last_evaluated_at       TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL    -- pending_approval|active|
                                                 -- suspended|blacklisted|inactive
blacklist_reason        TEXT         NULL
approved_by             BIGINT       NULL FK
notes                   TEXT         NULL
created_by, updated_by, created_at, updated_at, deleted_at
  UNIQUE (school_id, code)
  INDEX  (school_id, status)
  FULLTEXT (name, trading_name)

supplier_tax_clearances               -- 🇿🇼 ITF263 ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
supplier_id             BIGINT       FK INDEX
certificate_number      VARCHAR(60)  NOT NULL
issued_on               DATE         NOT NULL
expires_on              DATE         NOT NULL
document_file_id        BIGINT       NULL FK
verified_by             BIGINT       NULL FK
verified_at             TIMESTAMP    NULL
verification_method     VARCHAR(30)  NULL        -- manual|zimra_portal
status                  VARCHAR(20)  NOT NULL    -- valid|expired|revoked|unverified
  INDEX (school_id, supplier_id, expires_on)
  INDEX (school_id, expires_on, status)

purchase_requisitions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
requisition_number      VARCHAR(40)  NOT NULL
department_id           BIGINT       FK
cost_centre_id          BIGINT       FK
budget_line_id          BIGINT       NULL FK     -- FIN-11
justification           TEXT         NOT NULL
required_by             DATE         NULL
urgency                 VARCHAR(20)  NOT NULL DEFAULT 'normal'  -- normal|urgent|emergency
estimated_total_minor   BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
-- ⭐ budget checking at the point of request
budget_available_minor  BIGINT       NULL        -- snapshot at submission
budget_check_result     VARCHAR(20)  NULL        -- within|exceeds|no_budget|not_checked
status                  VARCHAR(20)  NOT NULL    -- draft|pending|approved|
                                                 -- rejected|sourcing|ordered|
                                                 -- cancelled|closed
approval_request_id     BIGINT       NULL FK
requested_by            BIGINT       FK → users.id
approved_by             BIGINT       NULL FK
rejection_reason        VARCHAR(255) NULL
source_type             VARCHAR(40)  NULL        -- reorder|work_order|manual|
                                                 -- meal_plan|capital_project
source_id               BIGINT       NULL
created_at, updated_at
  UNIQUE (school_id, requisition_number)
  INDEX  (school_id, status, cost_centre_id)

purchase_requisition_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
requisition_id          BIGINT       FK INDEX
item_id                 BIGINT       NULL FK     -- null for services
description             VARCHAR(500) NOT NULL
specification           TEXT         NULL
quantity                DECIMAL(14,4) NOT NULL
unit                    VARCHAR(20)  NOT NULL
estimated_unit_minor    BIGINT       NULL
estimated_total_minor   BIGINT       NULL
currency                CHAR(3)      NOT NULL
ordered_quantity        DECIMAL(14,4) NOT NULL DEFAULT 0

quotation_requests
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
requisition_id          BIGINT       FK INDEX
request_number          VARCHAR(40)  NOT NULL
suppliers_invited       JSON         NOT NULL    -- supplier_ids
issued_on               DATE         NOT NULL
closes_on               DATE         NOT NULL
status                  VARCHAR(20)  NOT NULL    -- open|closed|evaluated|awarded
evaluation_criteria     JSON         NULL        -- price, delivery, quality weights
awarded_quotation_id    BIGINT       NULL FK
award_justification     TEXT         NULL        -- ⭐ mandatory if not lowest
awarded_by              BIGINT       NULL FK

quotations
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
quotation_request_id    BIGINT       FK INDEX
supplier_id             BIGINT       FK
quotation_reference     VARCHAR(60)  NULL
received_on             DATE         NOT NULL
valid_until             DATE         NULL
subtotal_minor          BIGINT       NOT NULL
tax_minor               BIGINT       NOT NULL DEFAULT 0
total_minor             BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
delivery_days           SMALLINT     NULL
payment_terms_days      SMALLINT     NULL
document_file_id        BIGINT       NULL FK
evaluation_score        DECIMAL(6,2) NULL
is_compliant            TINYINT(1)   NOT NULL DEFAULT 1
non_compliance_note     VARCHAR(255) NULL
status                  VARCHAR(20)  NOT NULL    -- received|evaluated|
                                                 -- awarded|rejected

quotation_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
quotation_id            BIGINT       FK INDEX
requisition_line_id     BIGINT       NULL FK
description             VARCHAR(500) NOT NULL
quantity                DECIMAL(14,4) NOT NULL
unit_price_minor        BIGINT       NOT NULL
line_total_minor        BIGINT       NOT NULL
lead_time_days          SMALLINT     NULL

purchase_orders
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
po_number               VARCHAR(40)  NOT NULL    -- gapless
supplier_id             BIGINT       FK INDEX
requisition_id          BIGINT       NULL FK
quotation_id            BIGINT       NULL FK
cost_centre_id          BIGINT       FK
budget_line_id          BIGINT       NULL FK
order_date              DATE         NOT NULL
expected_delivery       DATE         NULL
delivery_address        VARCHAR(255) NULL
subtotal_minor          BIGINT       NOT NULL
tax_minor               BIGINT       NOT NULL DEFAULT 0
total_minor             BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
exchange_rate_id        BIGINT       NULL FK
base_total_minor        BIGINT       NOT NULL
-- ⭐ commitment accounting
committed_minor         BIGINT       NOT NULL    -- consumed from budget on approval
released_minor          BIGINT       NOT NULL DEFAULT 0   -- as invoices arrive
status                  VARCHAR(20)  NOT NULL    -- draft|pending_approval|approved|
                                                 -- sent|acknowledged|
                                                 -- partially_received|received|
                                                 -- invoiced|closed|cancelled
approval_request_id     BIGINT       NULL FK
approved_by             BIGINT       NULL FK
sent_at                 TIMESTAMP    NULL
document_id             BIGINT       NULL FK
terms_and_conditions    TEXT         NULL
notes                   TEXT         NULL
created_by, created_at, updated_at
  UNIQUE (school_id, po_number)
  INDEX  (school_id, supplier_id, status)
  INDEX  (school_id, status, order_date)

purchase_order_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
purchase_order_id       BIGINT       FK INDEX
line_number             SMALLINT     NOT NULL
item_id                 BIGINT       NULL FK
description             VARCHAR(500) NOT NULL
quantity_ordered        DECIMAL(14,4) NOT NULL
quantity_received       DECIMAL(14,4) NOT NULL DEFAULT 0
quantity_rejected       DECIMAL(14,4) NOT NULL DEFAULT 0
quantity_invoiced       DECIMAL(14,4) NOT NULL DEFAULT 0
unit                    VARCHAR(20)  NOT NULL
unit_price_minor        BIGINT       NOT NULL
tax_rate_percent        DECIMAL(5,2) NOT NULL DEFAULT 0
tax_category            VARCHAR(20)  NOT NULL    -- standard|zero|exempt
line_total_minor        BIGINT       NOT NULL
expense_account_id      BIGINT       NULL FK
is_capital              TINYINT(1)   NOT NULL DEFAULT 0   -- → FIN-10
store_id                BIGINT       NULL FK     -- receive into

goods_received_notes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
grn_number              VARCHAR(40)  NOT NULL
purchase_order_id       BIGINT       FK INDEX
supplier_id             BIGINT       FK
delivery_note_ref       VARCHAR(60)  NULL
received_on             DATE         NOT NULL
received_by             BIGINT       FK → users.id
inspected_by            BIGINT       NULL FK
store_id                BIGINT       NULL FK
is_partial              TINYINT(1)   NOT NULL DEFAULT 0
has_rejections          TINYINT(1)   NOT NULL DEFAULT 0
total_value_minor       BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
status                  VARCHAR(20)  NOT NULL    -- draft|received|
                                                 -- inspected|posted|disputed
journal_id              BIGINT       NULL FK     -- Dr Inventory / Cr GRN accrual
photo_file_ids          JSON         NULL
notes                   TEXT         NULL
  UNIQUE (school_id, grn_number)
  INDEX  (school_id, purchase_order_id)

grn_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
grn_id                  BIGINT       FK INDEX
po_line_id              BIGINT       FK
item_id                 BIGINT       NULL FK
quantity_delivered      DECIMAL(14,4) NOT NULL
quantity_accepted       DECIMAL(14,4) NOT NULL
quantity_rejected       DECIMAL(14,4) NOT NULL DEFAULT 0
rejection_reason        VARCHAR(255) NULL
batch_number            VARCHAR(60)  NULL
expiry_date             DATE         NULL
unit_cost_minor         BIGINT       NOT NULL
stock_lot_id            BIGINT       NULL FK     -- FIN-09 lot created

supplier_invoices
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
supplier_id             BIGINT       FK INDEX
invoice_number          VARCHAR(60)  NOT NULL    -- supplier's number
purchase_order_id       BIGINT       NULL FK
invoice_date            DATE         NOT NULL
received_on             DATE         NOT NULL
due_date                DATE         NOT NULL
subtotal_minor          BIGINT       NOT NULL
tax_minor               BIGINT       NOT NULL DEFAULT 0
total_minor             BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
exchange_rate_id        BIGINT       NULL FK
base_total_minor        BIGINT       NOT NULL
-- 🇿🇼 fiscal capture ⭐
is_fiscal_invoice       TINYINT(1)   NOT NULL DEFAULT 0
fiscal_device_id        VARCHAR(60)  NULL
fiscal_verification_code VARCHAR(80) NULL
fiscal_qr_verified      TINYINT(1)   NOT NULL DEFAULT 0
input_vat_claimable     TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ derived
input_vat_minor         BIGINT       NULL
-- 🇿🇼 withholding
withholding_applied     TINYINT(1)   NOT NULL DEFAULT 0
withholding_rate_percent DECIMAL(5,2) NULL
withholding_minor       BIGINT       NOT NULL DEFAULT 0
withholding_reason      VARCHAR(120) NULL        -- 'no_valid_itf263'
net_payable_minor       BIGINT       NOT NULL
-- matching
match_status            VARCHAR(20)  NOT NULL    -- unmatched|matched|
                                                 -- variance|manual_override
match_variance_minor    BIGINT       NULL
status                  VARCHAR(20)  NOT NULL    -- received|under_review|
                                                 -- approved|rejected|
                                                 -- partially_paid|paid|
                                                 -- disputed|cancelled
approval_request_id     BIGINT       NULL FK
approved_by             BIGINT       NULL FK
paid_minor              BIGINT       NOT NULL DEFAULT 0   -- CACHE
balance_minor           BIGINT       NOT NULL              -- CACHE
journal_id              BIGINT       NULL FK
document_file_id        BIGINT       NULL FK
dispute_reason          TEXT         NULL
  UNIQUE (school_id, supplier_id, invoice_number)
  INDEX  (school_id, status, due_date)
  INDEX  (school_id, supplier_id, status)
  INDEX  (school_id, is_fiscal_invoice, input_vat_claimable)

supplier_invoice_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
invoice_id              BIGINT       FK INDEX
po_line_id              BIGINT       NULL FK
grn_line_id             BIGINT       NULL FK
description             VARCHAR(500) NOT NULL
quantity                DECIMAL(14,4) NOT NULL
unit_price_minor        BIGINT       NOT NULL
tax_category            VARCHAR(20)  NOT NULL
tax_rate_percent        DECIMAL(5,2) NOT NULL DEFAULT 0
tax_minor               BIGINT       NOT NULL DEFAULT 0
line_total_minor        BIGINT       NOT NULL
expense_account_id      BIGINT       FK
cost_centre_id          BIGINT       NULL FK

supplier_payments
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
payment_number          VARCHAR(40)  NOT NULL
supplier_id             BIGINT       FK INDEX
payment_date            DATE         NOT NULL
payment_method          VARCHAR(30)  NOT NULL    -- bank_transfer|cheque|cash|
                                                 -- mobile_money|rtgs
bank_account_id         BIGINT       NULL FK
reference               VARCHAR(120) NULL
gross_minor             BIGINT       NOT NULL
withholding_minor       BIGINT       NOT NULL DEFAULT 0
net_minor               BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
exchange_rate_id        BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL    -- draft|pending_approval|
                                                 -- approved|paid|cleared|
                                                 -- cancelled|returned
approval_request_id     BIGINT       NULL FK
journal_id              BIGINT       NULL FK
remittance_document_id  BIGINT       NULL FK
batch_id                BIGINT       NULL FK
  UNIQUE (school_id, payment_number)

supplier_payment_allocations
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
payment_id              BIGINT       FK INDEX
invoice_id              BIGINT       FK INDEX
amount_minor            BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
allocated_at            TIMESTAMP    NOT NULL
allocated_by            BIGINT       FK → users.id

supplier_contracts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
supplier_id             BIGINT       FK INDEX
contract_number         VARCHAR(40)  NOT NULL
title                   VARCHAR(200) NOT NULL
contract_type           VARCHAR(30)  NOT NULL    -- supply|service|maintenance|lease
starts_on               DATE         NOT NULL
ends_on                 DATE         NULL
value_minor             BIGINT       NULL
currency                CHAR(3)      NULL
renewal_notice_days     SMALLINT     NULL
auto_renew              TINYINT(1)   NOT NULL DEFAULT 0
document_file_id        BIGINT       NULL FK
owner_staff_id          BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL    -- draft|active|expiring|
                                                 -- expired|terminated|renewed
  UNIQUE (school_id, contract_number)
  INDEX  (school_id, ends_on, status)
```

### 3. ⭐ 🇿🇼 Tax clearance and withholding

ZIMRA requires that a payment to a supplier who cannot produce a valid tax clearance certificate suffers withholding tax, remitted to ZIMRA by the 10th of the following month. Schools routinely lose money here in both directions — paying gross to an uncleared supplier and becoming liable, or withholding from a cleared supplier and damaging the relationship.

```
Supplier invoice registered
        │
        ▼
  Valid ITF263 on the INVOICE DATE?          ← not today's date ⭐
        │
        ├─ YES → withholding_applied = 0
        │        net_payable = total
        │
        └─ NO  → withholding_applied = 1
                 withholding = total × configured rate
                 net_payable = total − withholding
                 reason recorded
                 ⭐ Supplier notified so they can produce a certificate
                 Journal on payment:
                   Dr Creditors        total
                     Cr Bank                       net
                     Cr Withholding Tax Payable    withheld
```

**Validity is assessed on the invoice date**, not the payment date. A certificate expiring between invoice and payment does not retrospectively create a withholding obligation.

**The exposure report.** A live screen shows, per supplier: clearance status, days to expiry, invoices in the current period, and withholding applied. A clearance expiring in fourteen days with $40,000 of orders outstanding is a conversation to have now, not at payment run.

### 4. ⭐ 🇿🇼 Fiscal invoice capture and input VAT

ZIMRA permits VAT input tax claims only where supported by a fiscal tax invoice from a device connected to the FDMS. The system captures this at registration and reports the exposure.

| # | Rule |
|---|---|
| Every supplier invoice registration asks: is this a fiscal tax invoice? If yes, the device identifier and verification code are captured. |
| `input_vat_claimable` is derived: the supplier is VAT-registered **and** the invoice is fiscal **and** the tax category is standard-rated. |
| A non-fiscal invoice from a VAT-registered supplier raises a **warning at registration**, not at year end: *"Input VAT of $312.50 is not claimable on this invoice. Request a fiscal tax invoice from the supplier."* |
| The unclaimable input VAT report totals the exposure by supplier and by period. |
| Where a supplier subsequently provides a fiscal invoice, the original is replaced by credit note and reissue, with the linkage recorded. |

**This is a pure-margin feature.** A school spending $400,000 a year with 15% VAT exposure recovers real money by never accepting a non-fiscal invoice, and no bursar can track it manually across hundreds of invoices.

### 5. ⭐ Three-way matching

```
PO line  ─────┐
              ├──► compare quantity, price, total
GRN line ─────┤
              │
Invoice line ─┘

Tolerances (configurable):
  quantity  ±  2%   or  ±1 unit, whichever greater
  price     ±  1%
  total     ±  configured absolute amount

Within tolerance   → match_status = matched      → auto-approve for payment
Outside tolerance  → match_status = variance     → approval required, variance shown
No GRN             → match_status = unmatched    → blocked; goods not received
Service (no goods) → GRN step skipped by PO line flag
```

**A variance is presented, not hidden.** The approver sees ordered 100 at $4.00, received 98, invoiced 100 at $4.15, with each difference and its value spelled out. Approving is a decision, not a click through a dialog.

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-08-001` | New suppliers require approval before any order can be placed. A supplier created and immediately paid is the classic procurement fraud pattern. |
| `BR-FIN-08-002` | Supplier bank details are encrypted at rest. **Changing them requires approval by a different user from the one requesting**, and notifies the bursar. |
| `BR-FIN-08-003` ⭐ | 🇿🇼 Tax clearance validity is assessed on the invoice date. Absent or expired clearance applies withholding at the configured rate. |
| `BR-FIN-08-004` | Clearance expiry alerts fire at 60, 30 and 7 days, and the exposure report shows outstanding order value against each expiring certificate. |
| `BR-FIN-08-005` ⭐ | 🇿🇼 Non-fiscal invoices from VAT-registered suppliers warn at registration with the unclaimable amount stated. |
| `BR-FIN-08-006` | Requisitions check budget availability at submission (`FIN-11`) and record the result. Exceeding budget does not block submission; it routes to a higher approval level. |
| `BR-FIN-08-007` | Quotations are required above the configured threshold, with a configured minimum number of suppliers. |
| `BR-FIN-08-008` | Awarding to other than the lowest compliant quotation requires a written justification. |
| `BR-FIN-08-009` | Purchase order approval follows `CORE-07` chains by value threshold and cost centre. |
| `BR-FIN-08-010` ⭐ | Approving a purchase order **commits budget immediately** in `FIN-11`. The commitment releases as invoices are matched. |
| `BR-FIN-08-011` | Purchase order numbers are gapless per school. A cancelled order voids its number with a reason. |
| `BR-FIN-08-012` | A goods received note posts `Dr Inventory (or Expense) / Cr GRN Accrual`, creating `FIN-09` stock lots for stocked items in the same transaction. |
| `BR-FIN-08-013` | Partial delivery is normal and supported. A purchase order stays open until fully received, cancelled, or closed short with a reason. |
| `BR-FIN-08-014` | Rejected goods are recorded with a reason, do not enter stock, and reduce the invoiceable quantity. |
| `BR-FIN-08-015` | An invoice posts `Dr GRN Accrual / Cr Creditors`, clearing the accrual raised at receipt. |
| `BR-FIN-08-016` | Three-way matching runs automatically on invoice registration. Variances beyond tolerance require approval with the variance displayed. |
| `BR-FIN-08-017` | An invoice with no matching GRN cannot be approved for payment unless the purchase order line is flagged as a service. |
| `BR-FIN-08-018` | Duplicate invoice detection matches on supplier plus invoice number, and warns on supplier plus amount plus date proximity. |
| `BR-FIN-08-019` | Payment runs batch approved invoices by due date, produce remittance advice, and post one journal per payment. |
| `BR-FIN-08-020` | Payment approval requires a different user from the one who approved the invoice. |
| `BR-FIN-08-021` | Withholding tax posts to the statutory payable account and appears on the `CMP` remittance schedule. |
| `BR-FIN-08-022` | Foreign-currency supplier balances revalue at period close through `FIN-06`. Settlement at a different rate posts realised FX. |
| `BR-FIN-08-023` | Supplier aging is computed per currency from invoice due dates. |
| `BR-FIN-08-024` | Contract expiry alerts at the renewal notice period; auto-renewing contracts alert before the point at which cancellation becomes impossible. |
| `BR-FIN-08-025` | Supplier performance ratings compute from on-time delivery and rejection rates, and are visible during quotation evaluation. |
| `BR-FIN-08-026` | Blacklisted suppliers cannot receive orders. Existing obligations remain payable. |

### 7. Screens

| Screen | Component | Permission |
|---|---|---|
| Supplier register | `Procurement\Suppliers\Index` | `procurement.supplier.view` |
| Supplier profile | `Procurement\Suppliers\Show` | — orders, invoices, payments, aging, performance, clearances |
| Bank detail change | `Procurement\Suppliers\BankChange` | `procurement.supplier.bank_change` ⚠⚠ — dual approval |
| **Tax clearance monitor** | `Procurement\Suppliers\Clearances` | `procurement.supplier.view` 🇿🇼 — expiring, exposure value |
| Requisitions | `Procurement\Requisitions\Index` | `procurement.requisition.create` — budget indicator inline |
| Quotation comparison | `Procurement\Quotations\Compare` | `procurement.quotation.manage` — side by side, scores, award with justification |
| Purchase orders | `Procurement\Orders\Index` | `procurement.order.view` |
| Create order | `Procurement\Orders\Create` | `procurement.order.create` — **budget impact shown before approval** |
| Goods receipt | `Procurement\Receipts\Create` | `procurement.grn.create` — mobile, photo, batch, rejection |
| **Invoice registration** | `Procurement\Invoices\Register` | `procurement.invoice.register` 🇿🇼 — **fiscal capture and input VAT warning** |
| **Match review** | `Procurement\Invoices\Match` | `procurement.invoice.approve` ⭐ — three-way variance, line by line |
| Payment run | `Procurement\Payments\Run` | `procurement.payment.create` — due invoices, batch, remittance preview |
| Supplier aging | `Procurement\Reports\Aging` | `procurement.report.view` |
| **Unclaimable VAT** | `Procurement\Reports\VatExposure` | `procurement.report.view` 🇿🇼 |
| Withholding schedule | `Procurement\Reports\Withholding` | `procurement.report.view` 🇿🇼 |
| Contracts | `Procurement\Contracts\Index` | `procurement.contract.manage` |
| Spend analytics | `Procurement\Reports\Spend` | `procurement.report.view` |

### 8. API endpoints

```
POST /api/v1/procurement/requisitions            mobile requisition
GET  /api/v1/procurement/requisitions/pending    approver queue
POST /api/v1/procurement/requisitions/{ulid}/approve
GET  /api/v1/procurement/orders/{ulid}
POST /api/v1/procurement/grn                     mobile goods receipt with photos
GET  /api/v1/procurement/approvals/pending       all procurement approvals for me
```

### 9. Permissions · Settings · Events

```
procurement.supplier.view          procurement.supplier.manage
procurement.supplier.approve ⚠     procurement.supplier.bank_change ⚠⚠
procurement.requisition.create     procurement.requisition.approve
procurement.quotation.manage       procurement.order.view
procurement.order.create           procurement.order.approve ⚠
procurement.grn.create             procurement.grn.inspect
procurement.invoice.register       procurement.invoice.approve ⚠
procurement.payment.create         procurement.payment.approve ⚠⚠
procurement.contract.manage        procurement.report.view
```

| Setting | Type | Default |
|---|---|---|
| `procurement.quotation_threshold_minor` | int | `100000` (USD 1,000) |
| `procurement.minimum_quotations` | int | `3` |
| `procurement.match_quantity_tolerance_percent` | int | `2` |
| `procurement.match_price_tolerance_percent` | int | `1` |
| `procurement.withholding_rate_percent` | decimal | `10.00` 🇿🇼 |
| `procurement.clearance_alert_days` | array | `[60,30,7]` |
| `procurement.warn_non_fiscal_invoice` | bool | `true` 🇿🇼 (**locked**) |
| `procurement.require_grn_before_payment` | bool | `true` |
| `procurement.separate_approver_for_payment` | bool | `true` (**locked**) |
| `procurement.duplicate_invoice_check` | bool | `true` |

Events: `SupplierApproved` · `SupplierBankDetailsChanged` ⚠⚠ · `TaxClearanceExpiring` 🇿🇼 · `WithholdingApplied` 🇿🇼 · `NonFiscalInvoiceRegistered` 🇿🇼 ⚠ · `RequisitionApproved` · `PurchaseOrderApproved` (→ `FIN-11` commit) · `GoodsReceived` (→ `FIN-09`) · `GoodsRejected` · `InvoiceMatched` · `MatchVarianceDetected` ⚠ · `DuplicateInvoiceSuspected` ⚠ · `PaymentApproved` · `ContractExpiring`

### 10. Acceptance criteria

```gherkin
AC-FIN-08-001
  Given a supplier with no valid ITF263 on the invoice date
  When their invoice for USD 5,000 is registered
  Then withholding of USD 500 is applied at the configured rate
  And net payable is USD 4,500
  And the withholding posts to the statutory payable account

AC-FIN-08-002
  Given a supplier's clearance expires between invoice date and payment date
  And it was valid on the invoice date
  Then no withholding is applied

AC-FIN-08-003
  Given a VAT-registered supplier submits a non-fiscal invoice
  Then registration warns with the unclaimable input VAT amount stated
  And the invoice appears on the VAT exposure report

AC-FIN-08-004
  Given a purchase order for USD 8,000 is approved
  Then USD 8,000 is committed against the budget line immediately
  And the department's available budget reduces by that amount

AC-FIN-08-005
  Given a PO line of 100 units at $4.00
  And a GRN of 98 units
  And an invoice of 100 units at $4.15
  Then matching reports a quantity variance of 2 and a price variance of 3.75%
  And approval is required with both variances displayed

AC-FIN-08-006
  Given goods are received into the kitchen store
  Then a GRN journal posts Dr Inventory / Cr GRN Accrual
  And FIN-09 stock lots are created in the same transaction

AC-FIN-08-007
  Given an invoice matching a GRN is approved
  Then the journal posts Dr GRN Accrual / Cr Creditors
  And the accrual raised at receipt is cleared

AC-FIN-08-008
  Given a user approved a supplier invoice
  When the same user attempts to approve its payment
  Then it is refused

AC-FIN-08-009
  Given supplier bank details are changed
  Then a different user must approve the change
  And the bursar is notified

AC-FIN-08-010
  Given an invoice number already exists for that supplier
  When it is registered again
  Then a duplicate warning blocks submission until acknowledged

AC-FIN-08-011
  Given a quotation is awarded to other than the lowest compliant bid
  Then a written justification is mandatory
  And it appears on the purchase order record

AC-FIN-08-012
  Given a supplier is blacklisted
  Then no new purchase order can be raised against them
  And existing approved invoices remain payable
```

---

# FIN-10 · Fixed Assets & Depreciation

### 1. Scope

Asset register, capitalisation from procurement and stores, depreciation schedules and automated posting, revaluation and impairment, transfer, disposal, physical verification, insurance register.

### 2. Data model

```sql
asset_categories
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL     -- 'VEH','ICT','FURN','BLDG','LIVE'
name                    VARCHAR(120) NOT NULL
asset_account_id        BIGINT       FK → accounts.id
accum_depreciation_account_id BIGINT FK
depreciation_expense_account_id BIGINT FK
disposal_account_id     BIGINT       FK
default_method          VARCHAR(30)  NOT NULL     -- straight_line|reducing_balance|
                                                  -- units_of_production|none
default_useful_life_years DECIMAL(4,1) NULL
default_residual_percent DECIMAL(5,2) NOT NULL DEFAULT 0
is_depreciable          TINYINT(1)   NOT NULL DEFAULT 1
verification_frequency_months SMALLINT NOT NULL DEFAULT 12
  UNIQUE (school_id, code)

fixed_assets
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
asset_tag               VARCHAR(40)  NOT NULL     -- physical label, gapless
category_id             BIGINT       FK INDEX
name                    VARCHAR(200) NOT NULL
description             TEXT         NULL
serial_number           VARCHAR(80)  NULL
model                   VARCHAR(120) NULL
manufacturer            VARCHAR(120) NULL
-- acquisition
acquisition_date        DATE         NOT NULL
acquisition_cost_minor  BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
base_cost_minor         BIGINT       NOT NULL
exchange_rate_id        BIGINT       NULL FK
acquisition_source      VARCHAR(30)  NOT NULL     -- purchase|donation|
                                                  -- construction|transfer|
                                                  -- opening_balance
supplier_id             BIGINT       NULL FK
purchase_order_id       BIGINT       NULL FK
grn_id                  BIGINT       NULL FK
stock_movement_id       BIGINT       NULL FK      -- capitalised from FIN-09
donor_name              VARCHAR(200) NULL
-- depreciation
is_depreciable          TINYINT(1)   NOT NULL DEFAULT 1
depreciation_method     VARCHAR(30)  NOT NULL
useful_life_years       DECIMAL(4,1) NULL
residual_value_minor    BIGINT       NOT NULL DEFAULT 0
depreciation_start_date DATE         NULL
total_units_expected    DECIMAL(14,2) NULL        -- units_of_production
units_consumed          DECIMAL(14,2) NOT NULL DEFAULT 0
accumulated_depreciation_minor BIGINT NOT NULL DEFAULT 0
net_book_value_minor    BIGINT       NOT NULL
last_depreciated_on     DATE         NULL
fully_depreciated       TINYINT(1)   NOT NULL DEFAULT 0
-- location and custody
location                VARCHAR(150) NULL
building                VARCHAR(80)  NULL
room                    VARCHAR(60)  NULL
department_id           BIGINT       NULL FK
cost_centre_id          BIGINT       FK
custodian_staff_id      BIGINT       NULL FK
-- lifecycle
status                  VARCHAR(20)  NOT NULL     -- active|in_maintenance|
                                                  -- idle|impaired|disposed|
                                                  -- written_off|lost|stolen
condition               VARCHAR(20)  NOT NULL DEFAULT 'good'
warranty_expires_on     DATE         NULL
photo_file_ids          JSON         NULL
barcode                 VARCHAR(60)  NULL
qr_code                 VARCHAR(80)  NULL
last_verified_on        DATE         NULL
next_verification_on    DATE         NULL
notes                   TEXT         NULL
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, asset_tag)
  INDEX  (school_id, category_id, status)
  INDEX  (school_id, cost_centre_id)
  INDEX  (school_id, next_verification_on)

depreciation_runs
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
period_month            CHAR(7)      NOT NULL     -- '2026-09'
run_date                DATE         NOT NULL
asset_count             INT          NOT NULL DEFAULT 0
total_depreciation_minor BIGINT      NOT NULL DEFAULT 0
currency                CHAR(3)      NOT NULL
status                  VARCHAR(20)  NOT NULL     -- computing|preview|
                                                  -- approved|posted|reversed
journal_id              BIGINT       NULL FK
computed_by             BIGINT       FK → users.id
approved_by             BIGINT       NULL FK
posted_at               TIMESTAMP    NULL
  UNIQUE (school_id, period_month)

depreciation_entries
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
run_id                  BIGINT       FK INDEX
asset_id                BIGINT       FK INDEX
opening_nbv_minor       BIGINT       NOT NULL
depreciation_minor      BIGINT       NOT NULL
closing_nbv_minor       BIGINT       NOT NULL
method_used             VARCHAR(30)  NOT NULL
calculation_note        VARCHAR(255) NULL
  UNIQUE (run_id, asset_id)

asset_movements                       -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
asset_id                BIGINT       FK INDEX
movement_type           VARCHAR(30)  NOT NULL     -- transfer|custodian_change|
                                                  -- status_change|condition_change|
                                                  -- revaluation|impairment
from_value              VARCHAR(255) NULL
to_value                VARCHAR(255) NULL
reason                  VARCHAR(255) NULL
journal_id              BIGINT       NULL FK
performed_by            BIGINT       FK → users.id
occurred_at             TIMESTAMP    NOT NULL

asset_verifications
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
verification_round      VARCHAR(40)  NOT NULL
asset_id                BIGINT       FK INDEX
verified_on             DATE         NULL
found                   TINYINT(1)   NULL
location_confirmed      TINYINT(1)   NULL
actual_location         VARCHAR(150) NULL
condition_observed      VARCHAR(20)  NULL
scan_method             VARCHAR(20)  NULL         -- barcode|qr|manual
photo_file_id           BIGINT       NULL FK
verified_by             BIGINT       NULL FK
discrepancy_note        TEXT         NULL
status                  VARCHAR(20)  NOT NULL     -- pending|verified|
                                                  -- not_found|discrepancy|resolved
  UNIQUE (verification_round, asset_id)
  INDEX  (school_id, verification_round, status)

asset_disposals
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK
asset_id                BIGINT       FK UNIQUE
disposal_date           DATE         NOT NULL
disposal_method         VARCHAR(30)  NOT NULL     -- sale|scrap|donation|
                                                  -- trade_in|loss|theft
proceeds_minor          BIGINT       NOT NULL DEFAULT 0
currency                CHAR(3)      NOT NULL
nbv_at_disposal_minor   BIGINT       NOT NULL
gain_loss_minor         BIGINT       NOT NULL     -- positive = gain
buyer                   VARCHAR(200) NULL
reason                  TEXT         NOT NULL
approval_request_id     BIGINT       NULL FK
approved_by             BIGINT       NULL FK
journal_id              BIGINT       NULL FK
document_file_id        BIGINT       NULL FK

asset_insurance
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
policy_number           VARCHAR(60)  NOT NULL
insurer                 VARCHAR(200) NOT NULL
policy_type             VARCHAR(40)  NOT NULL     -- all_risk|fire|motor|
                                                  -- public_liability
covered_asset_ids       JSON         NULL         -- null = category-wide
category_id             BIGINT       NULL FK
sum_insured_minor       BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
premium_minor           BIGINT       NOT NULL
starts_on               DATE         NOT NULL
expires_on              DATE         NOT NULL
document_file_id        BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL     -- active|expiring|expired|lapsed
  INDEX (school_id, expires_on, status)
```

### 3. Depreciation calculation

```
STRAIGHT LINE
   monthly = (cost − residual) / (useful_life_years × 12)

REDUCING BALANCE
   annual_rate = 1 − (residual / cost)^(1 / useful_life_years)
   monthly     = opening_nbv × (annual_rate / 12)

UNITS OF PRODUCTION            -- vehicles by kilometre, generators by hour
   per_unit = (cost − residual) / total_units_expected
   period   = per_unit × units_consumed_this_period

In all methods:
   NBV never falls below residual_value_minor.
   The final period's charge is trimmed so it lands exactly on residual.
   Depreciation starts on depreciation_start_date, defaulting to the
   first day of the month following acquisition (configurable).
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-10-001` | Asset tags are gapless per school and physically applied. An untagged asset appears on the exception report. |
| `BR-FIN-10-002` | Assets capitalise automatically from `FIN-08` where the purchase order line is flagged capital, and from `FIN-09` where the item breaches the capitalisation rule on issue. |
| `BR-FIN-10-003` | Donated assets are recorded at fair value with the donor named, posting `Dr Fixed Assets / Cr Donation Income`. |
| `BR-FIN-10-004` | Depreciation runs monthly, is previewed and approved before posting, and posts one journal per run with lines by category and cost centre. |
| `BR-FIN-10-005` | A depreciation run for a period already posted is refused. Reversal requires approval and reverses the whole run. |
| `BR-FIN-10-006` | Net book value never falls below residual value. The final charge is trimmed to land exactly. |
| `BR-FIN-10-007` | Depreciation cannot post into a locked financial period. |
| `BR-FIN-10-008` | Units-of-production assets draw consumption from `OPS-01` mileage logs or `OPS-04` generator hours where those modules are enabled. |
| `BR-FIN-10-009` | Every transfer, custodian change, status change and condition change writes an append-only movement record. |
| `BR-FIN-10-010` | Transferring an asset between cost centres moves future depreciation to the new centre from the transfer date. Prior charges are not restated. |
| `BR-FIN-10-011` | Verification rounds generate a checklist per location. Scanning confirms presence; a scan in an unexpected location records a discrepancy without failing the asset. |
| `BR-FIN-10-012` | An asset not found in a verification round is flagged, investigated, and either located or written off with approval. **It is never quietly removed.** |
| `BR-FIN-10-013` | Disposal computes gain or loss against net book value at the disposal date and posts it, requiring approval above the configured threshold. |
| `BR-FIN-10-014` | Theft or loss disposal additionally triggers an insurance claim prompt and a `BRD-07` or security incident where applicable. |
| `BR-FIN-10-015` | Insurance policies alert at 60, 30 and 7 days before expiry with the sum insured and asset count shown. |
| `BR-FIN-10-016` | An insurance sum insured materially below aggregate net book value for the covered category raises an under-insurance warning. |
| `BR-FIN-10-017` | Foreign-currency assets are held at historical cost in base currency and are **not** revalued for FX. This is deliberate and matches standard accounting treatment. |
| `BR-FIN-10-018` | Impairment reduces carrying value with a recorded reason and approval, posting to an impairment expense account. |
| `BR-FIN-10-019` | The asset register reconciles to the general ledger asset and accumulated depreciation accounts. A nightly job asserts this and alerts on divergence. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Asset register | `Assets\Register\Index` | `assets.view` — filter by category, location, custodian, status |
| Asset detail | `Assets\Register\Show` | — acquisition, depreciation schedule, movements, maintenance, verifications |
| Create asset | `Assets\Register\Create` | `assets.manage` |
| Depreciation run | `Assets\Depreciation\Run` | `assets.depreciation.run` — preview, approve, post |
| Transfer | `Assets\Register\Transfer` | `assets.transfer` |
| **Verification** | `Assets\Verification\Round` | `assets.verify` — mobile, scan-based, photo, discrepancy capture |
| Discrepancies | `Assets\Verification\Discrepancies` | `assets.verify` |
| Disposal | `Assets\Disposal\Create` | `assets.dispose` ⚠ — gain/loss computed and shown |
| Insurance | `Assets\Insurance\Index` | `assets.insurance.manage` — under-insurance warnings |
| Reconciliation | `Assets\Reports\Reconciliation` | `assets.report.view` — register vs ledger |

```
GET  /api/v1/assets/{tag}                 scan lookup
POST /api/v1/assets/verify                mobile verification with photo
GET  /api/v1/assets/my-custody            custodian's assets
```

| Setting | Type | Default |
|---|---|---|
| `assets.tag_pattern` | string | `{SCHOOL}/AST/{SEQ:5}` |
| `assets.depreciation_starts` | enum | `month_following` |
| `assets.disposal_approval_threshold_minor` | int | `0` (all) |
| `assets.verification_frequency_months` | int | `12` |
| `assets.under_insurance_warning_percent` | int | `80` |

Events: `AssetCapitalised` · `DepreciationPosted` · `AssetTransferred` · `AssetNotFound` ⚠ · `AssetDisposed` · `InsuranceExpiring` · `UnderInsuranceDetected` ⚠ · `RegisterLedgerDivergence` ⚠

### 6. Acceptance criteria

```gherkin
AC-FIN-10-001
  Given an asset costing USD 12,000 with residual USD 2,000 over 5 years straight line
  Then monthly depreciation is USD 166.67
  And after 60 months the net book value is exactly USD 2,000

AC-FIN-10-002
  Given a depreciation run is posted for 2026-09
  When a second run for 2026-09 is attempted
  Then it is refused

AC-FIN-10-003
  Given a capital PO line is received
  Then an asset record is created automatically
  And linked to the GRN and purchase order

AC-FIN-10-004
  Given an item above the capitalisation threshold is issued from stores
  Then FIN-09 creates the asset rather than expensing it
  And the journal debits Fixed Assets

AC-FIN-10-005
  Given an asset is not found during verification
  Then it is flagged for investigation
  And it cannot be removed from the register without an approved write-off

AC-FIN-10-006
  Given a vehicle with NBV USD 4,000 is sold for USD 5,500
  Then a gain of USD 1,500 posts to the disposal account

AC-FIN-10-007
  Given a policy's sum insured is 60% of covered assets' net book value
  Then an under-insurance warning is raised

AC-FIN-10-008
  Given the nightly reconciliation runs
  When the register total differs from the ledger asset account
  Then an alert is raised naming the difference
```

---

# FIN-11 · Budgeting, Forecasting & Commitment Accounting ⭐

### 1. Scope

Budget definition by cost centre and account, departmental submission and consolidation, commitment accounting, variance reporting, virement, fee income and cash flow forecasting, scenario modelling.

### 2. Data model

```sql
budgets
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK INDEX
name                    VARCHAR(150) NOT NULL
budget_type             VARCHAR(20)  NOT NULL     -- operating|capital|project
period_basis            VARCHAR(20)  NOT NULL     -- annual|termly|monthly
currency                CHAR(3)      NOT NULL
version                 SMALLINT     NOT NULL DEFAULT 1
status                  VARCHAR(20)  NOT NULL     -- draft|submitted|
                                                  -- under_review|approved|
                                                  -- active|revised|closed
total_income_minor      BIGINT       NOT NULL DEFAULT 0
total_expense_minor     BIGINT       NOT NULL DEFAULT 0
surplus_minor           BIGINT       NOT NULL DEFAULT 0
approval_request_id     BIGINT       NULL FK
approved_by             BIGINT       NULL FK
approved_at             TIMESTAMP    NULL
board_approved_on       DATE         NULL
prepared_by             BIGINT       FK → users.id
  UNIQUE (school_id, academic_year_id, name, version)
  INDEX  (school_id, academic_year_id, status)

budget_lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
budget_id               BIGINT       FK INDEX
account_id              BIGINT       FK INDEX
cost_centre_id          BIGINT       FK INDEX
term_id                 BIGINT       NULL FK      -- null = annual line
annual_amount_minor     BIGINT       NOT NULL
term_1_minor            BIGINT       NULL
term_2_minor            BIGINT       NULL
term_3_minor            BIGINT       NULL
currency                CHAR(3)      NOT NULL
-- ⭐ live position
committed_minor         BIGINT       NOT NULL DEFAULT 0   -- approved POs
actual_minor            BIGINT       NOT NULL DEFAULT 0   -- posted journals
available_minor         BIGINT       NOT NULL DEFAULT 0   -- annual − committed − actual
prior_year_actual_minor BIGINT       NULL
basis_note              VARCHAR(500) NULL         -- how the figure was derived
is_locked               TINYINT(1)   NOT NULL DEFAULT 0
  UNIQUE (budget_id, account_id, cost_centre_id, term_id)
  INDEX  (school_id, cost_centre_id, available_minor)

budget_commitments                    -- ⭐ the control
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
budget_line_id          BIGINT       FK INDEX
source_type             VARCHAR(40)  NOT NULL     -- purchase_order|contract|
                                                  -- payroll_commitment
source_id               BIGINT       NOT NULL
committed_minor         BIGINT       NOT NULL
released_minor          BIGINT       NOT NULL DEFAULT 0
outstanding_minor       BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
committed_at            TIMESTAMP    NOT NULL
released_at             TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL     -- open|partially_released|
                                                  -- released|cancelled
  INDEX (school_id, budget_line_id, status)
  INDEX (source_type, source_id)

budget_virements                      -- transfers between lines
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
budget_id               BIGINT       FK
from_line_id            BIGINT       FK
to_line_id              BIGINT       FK
amount_minor            BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
reason                  TEXT         NOT NULL
status                  VARCHAR(20)  NOT NULL     -- pending|approved|rejected
approval_request_id     BIGINT       NULL FK
requested_by            BIGINT       FK → users.id
approved_by             BIGINT       NULL FK
effective_from          DATE         NOT NULL

forecasts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
forecast_type           VARCHAR(30)  NOT NULL     -- fee_income|cash_flow|
                                                  -- enrolment|expenditure
scenario_name           VARCHAR(120) NOT NULL     -- 'Base','Collection 85%'
assumptions             JSON         NOT NULL     -- enrolment, collection rate,
                                                  -- fee increase, FX rate
projections             JSON         NOT NULL     -- per period
generated_at            TIMESTAMP    NOT NULL
generated_by            BIGINT       FK → users.id
is_baseline             TINYINT(1)   NOT NULL DEFAULT 0
```

### 3. ⭐ Commitment accounting

```
BUDGET LINE:  Kitchen Provisions, Boarding cost centre, 2026
              Annual budget                        USD 180,000

  March:   PO approved for maize meal              USD  24,000
           → commitment created
           → available drops to                    USD 156,000   ⭐ immediately

  April:   Invoice matched, USD 23,400 (short delivery)
           → commitment releases USD 23,400
           → actual increases    USD 23,400
           → residual commitment USD    600 stays open on the PO
           → available remains   USD 156,000        (unchanged, correctly)

  May:     PO closed short
           → residual USD 600 released
           → available rises to  USD 156,600

At any moment:  available = annual − committed_outstanding − actual
```

Without this, three departments spend the same $24,000 between March and April and nobody knows until May.

**The requisition check** is where the control bites. At submission, the requisition reads `available_minor` for its budget line and records the result. Exceeding it does not block — it routes to a higher approval level with the overspend shown. Blocking outright causes staff to route around the system; showing the overspend to a more senior approver is the control that actually works.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-FIN-11-001` | Budget lines are per account per cost centre, optionally per term. |
| `BR-FIN-11-002` | Budgets are versioned. Approving a revision supersedes the prior version; both remain retrievable and variance can be reported against either. |
| `BR-FIN-11-003` | Departmental submission collects bottom-up, consolidates, and routes through `CORE-07`. Each line may carry a basis note explaining its derivation. |
| `BR-FIN-11-004` ⭐ | Approving a purchase order creates a commitment against its budget line immediately. |
| `BR-FIN-11-005` | Matching an invoice releases commitment proportionally and increases actual. Residual commitment stays open until the order is closed. |
| `BR-FIN-11-006` | Cancelling or short-closing an order releases the outstanding commitment. |
| `BR-FIN-11-007` | `available = annual − committed_outstanding − actual`, recomputed on every commitment and journal posting. |
| `BR-FIN-11-008` | Actuals derive from `FIN-01` journal lines by account, cost centre and date. They are never entered manually. |
| `BR-FIN-11-009` | A requisition exceeding available budget routes to a higher approval level with the overspend displayed. It is not blocked outright. |
| `BR-FIN-11-010` | Virement between lines requires approval and takes effect from the recorded date. Both lines' history shows the transfer. |
| `BR-FIN-11-011` | Locked budget lines reject virement in or out. |
| `BR-FIN-11-012` | Variance reports show budget, committed, actual, available and variance percentage, drilling from any figure to the underlying transactions. |
| `BR-FIN-11-013` | Fee income forecasting projects from enrolment by level and residency, the approved fee structure (`FIN-02`), and historical collection rates by term. |
| `BR-FIN-11-014` | Cash flow forecasting combines projected fee receipts by due date and behaviour, committed expenditure by expected payment date, payroll, and known statutory obligations. |
| `BR-FIN-11-015` | Scenarios are modelled without altering the approved budget. A scenario is never mistaken for a plan. |
| `BR-FIN-11-016` | Multi-currency budgets hold lines in their transaction currency and consolidate at the budget rate, with the rate basis stated on every consolidated report. |
| `BR-FIN-11-017` | Budget lines exceeding the configured variance threshold at any point alert the cost centre manager and the bursar. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Budget builder | `Budget\Builder\Index` | `budget.manage` — lines by cost centre, prior year comparative, basis notes |
| Departmental submission | `Budget\Submission\Form` | `budget.submit` — HOD view of own cost centre only |
| Consolidation | `Budget\Consolidation\Review` | `budget.consolidate` — all submissions, surplus position |
| **Variance dashboard** | `Budget\Variance\Dashboard` | `budget.view` ⭐ — budget, committed, actual, available; drill to source |
| Commitment register | `Budget\Commitments\Index` | `budget.view` — open commitments by line and age |
| Virement | `Budget\Virement\Create` | `budget.virement.request` |
| Fee income forecast | `Budget\Forecast\FeeIncome` | `budget.forecast.view` |
| Cash flow forecast | `Budget\Forecast\CashFlow` | `budget.forecast.view` |
| Scenarios | `Budget\Forecast\Scenarios` | `budget.forecast.manage` |

```
GET /api/v1/budget/my-cost-centre       HOD: budget, committed, actual, available
GET /api/v1/budget/check                ?account=&cost_centre=&amount=  pre-check
```

| Setting | Type | Default |
|---|---|---|
| `budget.commitment_accounting_enabled` | bool | `true` (**locked on Enterprise**) |
| `budget.overspend_routes_to_higher_approval` | bool | `true` |
| `budget.variance_alert_percent` | int | `10` |
| `budget.forecast_collection_rate_source` | enum | `historical_3_term` |
| `budget.require_basis_note` | bool | `false` |

Events: `BudgetApproved` · `BudgetRevised` · `CommitmentCreated` · `CommitmentReleased` · `BudgetExceeded` ⚠ · `VirementApproved` · `ForecastGenerated`

### 6. Acceptance criteria

```gherkin
AC-FIN-11-001
  Given a budget line of USD 180,000 with no activity
  When a purchase order for USD 24,000 is approved
  Then available immediately becomes USD 156,000

AC-FIN-11-002
  Given that order is invoiced at USD 23,400
  Then committed reduces by USD 23,400 and actual increases by USD 23,400
  And available remains USD 156,000

AC-FIN-11-003
  Given the order is then closed short
  Then the residual USD 600 commitment releases
  And available becomes USD 156,600

AC-FIN-11-004
  Given a requisition exceeds available budget
  Then it is not blocked
  And it routes to a higher approval level with the overspend displayed

AC-FIN-11-005
  Given actuals are requested for a budget line
  Then they derive from FIN-01 journal lines
  And no manual actual figure can be entered

AC-FIN-11-006
  Given a scenario models an 85% collection rate
  Then the approved budget is unchanged
  And the scenario is clearly labelled as a scenario

AC-FIN-11-007
  Given a budget line passes the variance alert threshold
  Then the cost centre manager and bursar are alerted
```

---

## Part 3 — Book H1 Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **H1-1** | `FIN-09` stores, items, units, lots | Conversions exact to the gram |
| **H1-2** | `FIN-09` receipt, FIFO issue, expense recognition ⭐ | **`AC-FIN-09-001` to `-004` green. Book F costing lights up.** |
| **H1-3** | `FIN-09` transfers, returns, saleable stock | Returns reverse at original lot cost |
| **H1-4** | `FIN-09` stock take, blind count, variance | System quantity absent from the count payload |
| **H1-5** | `FIN-09` baselines, anomaly detection | Per-boarder-day normalisation working |
| **H1-6** | `FIN-08` suppliers, clearances, withholding 🇿🇼 | Validity assessed on invoice date |
| **H1-7** | `FIN-08` requisition, quotation, purchase order | Award justification mandatory when not lowest |
| **H1-8** | `FIN-08` GRN, stock lot creation, rejection | Receipt creates lots in one transaction |
| **H1-9** | `FIN-08` invoice, fiscal capture, three-way match 🇿🇼 | **Non-fiscal warning at registration** |
| **H1-10** | `FIN-08` payment run, remittance, aging | Separate approver enforced |
| **H1-11** | `FIN-10` register, capitalisation, depreciation | NBV lands exactly on residual |
| **H1-12** | `FIN-10` verification, disposal, insurance | Not-found cannot be quietly removed |
| **H1-13** | `FIN-11` budgets, submission, consolidation | Bottom-up with basis notes |
| **H1-14** | `FIN-11` commitment accounting ⭐ | **Wires back into `FIN-08`. `AC-FIN-11-001` to `-003` green.** |
| **H1-15** | `FIN-11` variance, virement, forecasting | Actuals derive from the GL only |

---

## Part 4 — Book H1 Acceptance Gate

### Ledger integrity

- [ ] Every stock movement that changes value posts a journal in the same transaction
- [ ] `stock_movements` confirmed `INSERT`/`SELECT` only at database grant level
- [ ] `stock_balances` verified nightly against movements; discrepancy rebuilds and alerts
- [ ] Asset register reconciles to the GL asset and accumulated depreciation accounts nightly
- [ ] Actuals in `FIN-11` derive exclusively from `FIN-01` journal lines

### Costing correctness

- [ ] FIFO consumes lots in order under concurrency with row locking
- [ ] Returns reverse at the original consumed lot costs
- [ ] Unit conversions reconcile exactly across purchase, base and issue units
- [ ] Depreciation lands exactly on residual value in the final period
- [ ] One journal per requisition, aggregated by account and cost centre

### Controls

- [ ] Blind stock take: system quantity absent from the count payload, not hidden
- [ ] Variance beyond threshold requires recount by a different person
- [ ] Supplier bank changes require a second approver and notify the bursar
- [ ] Invoice approver and payment approver must be different users
- [ ] Purchase order approval commits budget immediately; invoice matching releases proportionally
- [ ] Overspend routes to higher approval rather than blocking
- [ ] Asset not found in verification cannot be removed without approved write-off

### 🇿🇼 Zimbabwe compliance

- [ ] Tax clearance validity assessed on invoice date, not payment date
- [ ] Withholding computed at the configured rate and posted to the statutory payable
- [ ] Withholding appears on the remittance schedule for the 10th-of-month filing
- [ ] Non-fiscal invoices from VAT-registered suppliers warn at registration with the unclaimable amount
- [ ] The VAT exposure report totals by supplier and period

### Book F closure

- [ ] `StoreIssuanceProvider` fully implemented
- [ ] `BRD-04` catering costing operates end to end
- [ ] Cost per boarder per day computes from real issued cost
- [ ] Consumption anomaly detection normalises against live occupancy from `BRD-02`

### Quality

- [ ] Coverage ≥ 90% for all `FIN` modules in this book
- [ ] Tenancy isolation suite passes for every model
- [ ] Every business rule has a named test referencing its rule ID

---

## Appendix A — Interfaces

| Interface | Owner | Consumer | Status |
|---|---|---|---|
| `StoreIssuanceProvider` | `FIN-09` | `BRD-04` | ✅ closed here |
| Capitalisation on issue | `FIN-09` → `FIN-10` | — | ✅ |
| GRN → stock lots | `FIN-08` → `FIN-09` | — | ✅ |
| PO approval → commitment | `FIN-08` → `FIN-11` | — | ✅ |
| Units consumed for depreciation | `OPS-01`, `OPS-04` | `FIN-10` | Book H2 |
| Farm production into stores | `OPS-03` | `FIN-09` | Book H2 |
| Work order parts issue | `OPS-02` | `FIN-09` | Book H2 |
| Withholding remittance schedule | `FIN-08` | `CMP-04` | Book H3 |

---

## Appendix B — Remaining Books

| Book | Domain | Modules |
|---|---|---|
| **H2** | Operations & Estates | `OPS-01` transport · `OPS-02` maintenance · `OPS-03` farm · `OPS-04` utilities · `OPS-05` facilities · `OPS-06` security · `OPS-07` sport & houses |
| **H3** | Payroll, Fiscalisation & Compliance | `PPL-05` payroll 🇿🇼 · `FIN-12` reporting & close · `FIN-13` ZIMRA fiscalisation 🇿🇼 · `FIN-14` wallet & tuckshop · `CMP-01`–`CMP-04` |
| **I** | Communication & Portals | `COM-01` → `COM-08` |
| **J** | Intelligence & SaaS Control | `INT-01`–`INT-04` · `SAA-01`–`SAA-03` |

---

*End of Volume 2, Book H1.*

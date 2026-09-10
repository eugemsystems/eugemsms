<?php

declare(strict_types=1);

namespace Modules\Stores\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Boarding\Domain\Support\StoreIssuanceProvider;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Events\InvoiceMatched;
use Modules\Stores\Domain\Events\PurchaseOrderApproved;
use Modules\Stores\Domain\Listeners\CreateBudgetCommitmentOnPurchaseOrderApprovedListener;
use Modules\Stores\Domain\Listeners\ReleaseBudgetCommitmentOnInvoiceMatchedListener;
use Modules\Stores\Domain\Support\EloquentStoreIssuanceProvider;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\AssetDisposal;
use Modules\Stores\Models\AssetInsurance;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\AssetVerification;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetCommitment;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\BudgetVirement;
use Modules\Stores\Models\ConsumptionAnomaly;
use Modules\Stores\Models\ConsumptionBaseline;
use Modules\Stores\Models\DepreciationEntry;
use Modules\Stores\Models\DepreciationRun;
use Modules\Stores\Models\FixedAsset;
use Modules\Stores\Models\Forecast;
use Modules\Stores\Models\GoodsReceivedNote;
use Modules\Stores\Models\GrnLine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\ItemCategory;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\PurchaseOrderLine;
use Modules\Stores\Models\PurchaseRequisition;
use Modules\Stores\Models\PurchaseRequisitionLine;
use Modules\Stores\Models\Quotation;
use Modules\Stores\Models\QuotationRequest;
use Modules\Stores\Models\StockBalance;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\StockTakeLine;
use Modules\Stores\Models\StockTransfer;
use Modules\Stores\Models\StockTransferLine;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreItemSetting;
use Modules\Stores\Models\StoreRequisition;
use Modules\Stores\Models\StoreRequisitionLine;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierContract;
use Modules\Stores\Models\SupplierInvoice;
use Modules\Stores\Models\SupplierInvoiceLine;
use Modules\Stores\Models\SupplierPayment;
use Modules\Stores\Models\SupplierPaymentAllocation;
use Modules\Stores\Models\SupplierTaxClearance;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H1 Domain D: Procurement, Stores, Assets & Budgets
 * (`FIN-09` -> `FIN-08` -> `FIN-10` -> `FIN-11`).
 *
 * `FIN-09`'s inventory/stores engine joined in this pass:
 * `StockCostingEngine` ⭐ (FIFO/expiry-first, row-locked for the whole
 * issue — AC-FIN-09-004), `IssueStockAction` ⭐⭐ (one journal per
 * requisition, expense on the requesting cost centre, capitalisable
 * items above threshold fire `ItemCapitalisationDue` rather than
 * fabricating a `FIN-10` asset that doesn't exist yet), returns at
 * original lot cost, a real blind-count-sheet payload that never
 * carries `system_quantity` (BR-FIN-09-014), recount-by-a-different-
 * person, variance approval posting one adjustment journal to a
 * caller-supplied shrinkage account, expired-stock write-off,
 * inter-store transfer (value posted once at dispatch, receipt only
 * ever flags — never silently absorbs — a quantity discrepancy), and
 * consumption-anomaly detection normalised per boarder-day via
 * Boarding's own `LiveOccupancyProvider`. `StoreIssuanceProvider`
 * (Boarding's own minimal interface, bound to `NullStoreIssuanceProvider`
 * there) is rebound to `EloquentStoreIssuanceProvider` here — this
 * module loads after Boarding (priority 7 vs. 5), so this binding
 * wins without editing a single line of `BRD-04`'s own shipped code.
 * Deliberately deferred: `FIN-08`/`FIN-10`/`FIN-11` (later in this same
 * book — `ItemCapitalisationDue`'s real consumer arrives with `FIN-10`),
 * `OPS-01`/`OPS-03`/`OPS-04`'s own stores interfaces (Book H2, not yet
 * built), reorder/expiry alerts wired to a scheduled command (the
 * checks themselves are real, `CheckReorderLevelsAction`/
 * `CheckExpiringLotsAction` — only the cron entry is missing), and any
 * Http/API layer (consistent with every module in this codebase).
 *
 * `FIN-10`'s asset register joined in this pass: `CapitalizeAssetAction`
 * ⭐⭐ (the one real entry point for every capitalisation source — direct
 * purchase, donation at fair value per BR-FIN-10-003, or either
 * `FIN-08`/`FIN-09` capital line), `PreviewDepreciationRunAction`/
 * `ApproveDepreciationRunAction`/`PostDepreciationRunAction` (preview
 * → approve → post, one journal per run grouped by category and cost
 * centre, `DepreciationCalculator` implementing all three §3 methods
 * with NBV trimmed to land exactly on residual), append-only asset
 * movements for every transfer/custodian/status/condition change,
 * verification rounds that create one row per asset up front so a
 * never-scanned asset stays visibly `pending` rather than silently
 * absent, a not-found asset that can only leave the register through
 * an approved, different-user write-off (BR-FIN-10-012 ⭐), disposal
 * with gain/loss computed against NBV and an approval threshold, and
 * insurance expiry/under-insurance checks reading real NBV totals.
 * Deliberately deferred, and flagged here explicitly rather than
 * silently wired around: `CapitalizeAssetAction` is NOT yet
 * subscribed to `FIN-08`'s `CapitalPurchaseReceived` or `FIN-09`'s
 * `ItemCapitalisationDue` events, even though BR-FIN-10-002 calls for
 * automatic capitalisation from both. Neither `purchase_order_lines`
 * nor `inventory_items` carries an `asset_category_id` today, so an
 * automatic listener would have to guess which category a capital
 * line or a capitalisable item belongs to — that column choice
 * belongs to a deliberate decision (on one or both of `FIN-08`'s and
 * `FIN-09`'s own tables), not something to invent unreviewed while
 * wiring `FIN-10`. Until that lands, both events still fire for real
 * and a human capitalises manually via `CapitalizeAssetAction`,
 * exactly the way this pass leaves them. `OPS-01` mileage/`OPS-04`
 * generator hours (BR-FIN-10-008's units-of-production source) and
 * `BRD-07`/security-incident integration for theft/loss disposal
 * (BR-FIN-10-014) are likewise deferred — neither module exists yet.
 *
 * `FIN-11`'s budgeting/commitment-accounting engine closes Book H1 in
 * this pass: versioned budgets (`ReviseBudgetAction` creates a new
 * row rather than mutating the active one, BR-FIN-11-002), bottom-up
 * line submission with basis notes, and the ⭐ control itself —
 * `CreateBudgetCommitmentAction`/`ReleaseCommitmentAction` — wired to
 * `FIN-08` for real via two listeners
 * (`CreateBudgetCommitmentOnPurchaseOrderApprovedListener` on
 * `PurchaseOrderApproved`, `ReleaseBudgetCommitmentOnInvoiceMatchedListener`
 * on `InvoiceMatched`, releasing only the slice each specific invoice
 * accounts for so a residual commitment correctly stays open on a
 * partial delivery). `CancelPurchaseOrderAction` and the new
 * `ClosePurchaseOrderShortAction` release whatever remains outstanding
 * directly, closing BR-FIN-11-006/AC-FIN-11-003. `actual_minor` is
 * never written by hand anywhere in this module — only
 * `RecalculateBudgetLineActualsAction`, reading `journal_lines`
 * directly, ever sets it (BR-FIN-11-008 ⭐). `RequestPurchaseRequisitionAction`
 * (`FIN-08`) now calls `CheckBudgetAvailabilityAction` for real
 * instead of always recording `not_checked` — exceeding available
 * budget still never blocks submission, only records `exceeds` for a
 * higher approval level (BR-FIN-11-009). This is the one place this
 * pass edits an already-shipped `FIN-08` action, and it does so
 * exactly the way the book's own build sequence describes: `FIN-11`
 * "wires backwards" into `FIN-08`. Deliberately deferred: any real
 * multi-level `CORE-07` approval routing for an `exceeds` result (the
 * same single-gate boundary this codebase uses everywhere else `CORE-07`
 * is mentioned — see `ApprovePurchaseOrderAction`'s own docblock), and
 * `FIN-11`'s fee-income/cash-flow projection MATH itself
 * (`CreateForecastAction` stores a caller-supplied `projections`
 * payload as a labelled scenario rather than computing enrolment- or
 * collection-rate-driven numbers here — the real integration with
 * `FIN-02`'s fee structure and historical collection rates that
 * BR-FIN-11-013/014 describe is not built).
 */
class StoresServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Stores';

    protected string $nameLower = 'stores';

    public function register(): void
    {
        parent::register();

        $this->app->bind(StoreIssuanceProvider::class, EloquentStoreIssuanceProvider::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerEventListeners();
    }

    /**
     * Book H1 FIN-11 §6 ⭐ — the real `FIN-08` → `FIN-11` wiring.
     */
    private function registerEventListeners(): void
    {
        Event::listen(PurchaseOrderApproved::class, CreateBudgetCommitmentOnPurchaseOrderApprovedListener::class);
        Event::listen(InvoiceMatched::class, ReleaseBudgetCommitmentOnInvoiceMatchedListener::class);
    }

    /**
     * Book H1 FIN-09 §7/FIN-08 §9.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['inventory.reorder_level_breached', ['item.name', 'store.name'], false],
            ['inventory.expiry_approaching', ['item.name', 'days_remaining'], false],
            ['inventory.transfer_discrepancy', ['transfer.transfer_number'], true],
            ['inventory.negative_stock_issued', ['item.name', 'store.name'], true],
            ['inventory.consumption_anomaly_detected', ['item.name', 'store.name', 'variance_percent'], false],
            ['inventory.anomaly_escalated', ['item.name', 'store.name'], true],
            ['procurement.supplier_approved', ['supplier.name'], false],
            ['procurement.supplier_bank_details_changed', ['supplier.name'], true],
            ['procurement.tax_clearance_expiring', ['supplier.name', 'days_remaining'], false],
            ['procurement.withholding_applied', ['supplier.name', 'invoice.invoice_number'], false],
            ['procurement.non_fiscal_invoice_registered', ['supplier.name', 'invoice.invoice_number'], false],
            ['procurement.match_variance_detected', ['invoice.invoice_number'], false],
            ['procurement.duplicate_invoice_suspected', ['invoice.invoice_number'], true],
            ['procurement.contract_expiring', ['contract.title', 'days_remaining'], false],
            ['assets.depreciation_posted', ['run.period_month'], false],
            ['assets.asset_not_found', ['asset.name', 'asset.asset_tag'], true],
            ['assets.asset_disposed', ['asset.name', 'asset.asset_tag'], false],
            ['assets.insurance_expiring', ['policy.policy_number', 'days_remaining'], false],
            ['assets.under_insurance_detected', ['policy.policy_number'], true],
            ['assets.register_ledger_divergence', ['category_id'], true],
            ['budget.budget_approved', ['budget.name'], false],
            ['budget.budget_revised', ['budget.name'], false],
            ['budget.budget_exceeded', ['budget_line.id'], true],
            ['budget.virement_approved', ['virement.id'], false],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['email'],
                defaultAudience: 'staff',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book H1 FIN-09 §9/FIN-08 §9.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['inventory.default_costing_method', 'string', 'fifo', 'Default stock costing method for a new store.'],
            ['inventory.capitalisation_threshold_minor', 'int', '50000', 'Unit cost, in minor units, at or above which a capitalisable item creates a FIN-10 asset on issue instead of an expense.'],
            ['inventory.stocktake_blind', 'bool', '1', 'Whether stock counts are entered blind, with no system quantity shown (locked true).'],
            ['inventory.stocktake_recount_variance_percent', 'int', '5', 'Variance percentage, at or above which a counted line requires a recount by a different person.'],
            ['inventory.expiry_alert_days', 'json', '[90,30,7]', 'Days before expiry at which a lot raises an ExpiryApproaching alert.'],
            ['inventory.anomaly_tolerance_percent', 'int', '15', 'Default consumption variance tolerance, used when an item has no store-specific baseline tolerance.'],
            ['inventory.anomaly_baseline_days', 'int', '60', 'Trailing window, in days, used to compute a consumption baseline.'],
            ['inventory.allow_negative_stock', 'bool', '0', 'Whether a store may issue below zero on-hand, at last known cost, pending replenishment.'],
            ['inventory.high_risk_full_count_always', 'bool', '1', 'Whether a high-risk item requires a full count at every stock take regardless of the cycle plan.'],
            ['procurement.quotation_threshold_minor', 'int', '100000', 'Estimated requisition value at or above which competitive quotations are required.'],
            ['procurement.minimum_quotations', 'int', '3', 'Minimum suppliers invited once the quotation threshold is met.'],
            ['procurement.match_quantity_tolerance_percent', 'int', '2', 'Three-way match quantity tolerance.'],
            ['procurement.match_price_tolerance_percent', 'int', '1', 'Three-way match price tolerance.'],
            ['procurement.withholding_rate_percent', 'decimal', '10.00', 'Withholding tax rate applied when a supplier has no valid tax clearance on the invoice date.'],
            ['procurement.clearance_alert_days', 'json', '[60,30,7]', 'Days before tax clearance expiry at which an alert fires.'],
            ['procurement.warn_non_fiscal_invoice', 'bool', '1', 'Whether a non-fiscal invoice from a VAT-registered supplier warns at registration (locked true).'],
            ['procurement.require_grn_before_payment', 'bool', '1', 'Whether an invoice requires a matching goods received note before it can be approved for payment.'],
            ['procurement.separate_approver_for_payment', 'bool', '1', 'Whether payment approval requires a different user from invoice approval (locked true).'],
            ['procurement.duplicate_invoice_check', 'bool', '1', 'Whether duplicate invoice detection runs at registration.'],
            ['assets.tag_pattern', 'string', '{SCHOOL}/AST/{SEQ:5}', 'Numbering pattern for a new fixed asset tag.'],
            ['assets.depreciation_starts', 'string', 'month_following', 'When depreciation starts relative to acquisition date.'],
            ['assets.disposal_approval_threshold_minor', 'int', '0', 'Net book value at or above which a disposal requires an approver (0 = always).'],
            ['assets.verification_frequency_months', 'int', '12', 'Default months between physical verification rounds for a new asset category.'],
            ['assets.under_insurance_warning_percent', 'int', '80', 'Sum insured as a percentage of covered net book value, below which an under-insurance warning fires.'],
            ['budget.commitment_accounting_enabled', 'bool', '1', 'Whether approving a purchase order creates a budget commitment (locked on).'],
            ['budget.overspend_routes_to_higher_approval', 'bool', '1', 'Whether a requisition exceeding available budget routes to a higher approval level instead of being blocked.'],
            ['budget.variance_alert_percent', 'int', '10', 'Percentage a budget line may run over its annual amount before BudgetExceeded fires.'],
            ['budget.forecast_collection_rate_source', 'string', 'historical_3_term', 'Default source for fee income forecast collection rate assumptions.'],
            ['budget.require_basis_note', 'bool', '0', 'Whether a budget line requires a basis note explaining its derivation before it can be submitted.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'FIN',
                'group_key' => explode('.', $key)[0],
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => $dataType === 'bool' ? 'toggle' : 'text',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A Part 1.11's tenancy isolation test generator.
     */
    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(Store::class, fn (School $school): Store => Store::factory()->for($school)->create());

        TenantModelRegistry::register(ItemCategory::class, fn (School $school): ItemCategory => ItemCategory::factory()->for($school)->create());

        TenantModelRegistry::register(InventoryItem::class, fn (School $school): InventoryItem => InventoryItem::factory()->for($school)->create());

        TenantModelRegistry::register(StoreItemSetting::class, function (School $school): StoreItemSetting {
            $store = Store::factory()->for($school)->create();
            $item = InventoryItem::factory()->for($school)->create();

            return StoreItemSetting::factory()->create(['school_id' => $school->id, 'store_id' => $store->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(StockLot::class, function (School $school): StockLot {
            $store = Store::factory()->for($school)->create();
            $item = InventoryItem::factory()->for($school)->create();

            return StockLot::factory()->create(['school_id' => $school->id, 'store_id' => $store->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(StockMovement::class, function (School $school): StockMovement {
            [$store, $item, $term, $year] = $this->storeItemTerm($school);

            return StockMovement::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'store_id' => $store->id, 'item_id' => $item->id, 'performed_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(StockBalance::class, function (School $school): StockBalance {
            $store = Store::factory()->for($school)->create();
            $item = InventoryItem::factory()->for($school)->create();

            return StockBalance::factory()->create(['school_id' => $school->id, 'store_id' => $store->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(StoreRequisition::class, function (School $school): StoreRequisition {
            [$store, , $term, $year] = $this->storeItemTerm($school);

            return StoreRequisition::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'store_id' => $store->id, 'cost_centre_id' => CostCentre::factory()->for($school), 'requested_by' => User::factory(),
            ]);
        });

        TenantModelRegistry::register(StoreRequisitionLine::class, function (School $school): StoreRequisitionLine {
            [$store, $item, $term, $year] = $this->storeItemTerm($school);
            $requisition = StoreRequisition::factory()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
                'store_id' => $store->id, 'cost_centre_id' => CostCentre::factory()->for($school), 'requested_by' => User::factory(),
            ]);

            return StoreRequisitionLine::factory()->create(['school_id' => $school->id, 'requisition_id' => $requisition->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(StockTransfer::class, function (School $school): StockTransfer {
            $from = Store::factory()->for($school)->create();
            $to = Store::factory()->for($school)->kitchen()->create();

            return StockTransfer::factory()->create(['school_id' => $school->id, 'from_store_id' => $from->id, 'to_store_id' => $to->id]);
        });

        TenantModelRegistry::register(StockTransferLine::class, function (School $school): StockTransferLine {
            $from = Store::factory()->for($school)->create();
            $to = Store::factory()->for($school)->kitchen()->create();
            $item = InventoryItem::factory()->for($school)->create();
            $transfer = StockTransfer::factory()->create(['school_id' => $school->id, 'from_store_id' => $from->id, 'to_store_id' => $to->id]);

            return StockTransferLine::factory()->create(['school_id' => $school->id, 'transfer_id' => $transfer->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(StockTake::class, function (School $school): StockTake {
            $store = Store::factory()->for($school)->create();
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

            return StockTake::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'store_id' => $store->id]);
        });

        TenantModelRegistry::register(StockTakeLine::class, function (School $school): StockTakeLine {
            $store = Store::factory()->for($school)->create();
            $item = InventoryItem::factory()->for($school)->create();
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $take = StockTake::factory()->create(['school_id' => $school->id, 'term_id' => $term->id, 'store_id' => $store->id]);

            return StockTakeLine::factory()->create(['school_id' => $school->id, 'stock_take_id' => $take->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(ConsumptionBaseline::class, function (School $school): ConsumptionBaseline {
            $store = Store::factory()->for($school)->create();
            $item = InventoryItem::factory()->for($school)->create();

            return ConsumptionBaseline::factory()->create(['school_id' => $school->id, 'store_id' => $store->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(ConsumptionAnomaly::class, function (School $school): ConsumptionAnomaly {
            $store = Store::factory()->for($school)->create();
            $item = InventoryItem::factory()->for($school)->create();

            return ConsumptionAnomaly::factory()->create(['school_id' => $school->id, 'store_id' => $store->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(Supplier::class, fn (School $school): Supplier => Supplier::factory()->for($school)->create());

        TenantModelRegistry::register(SupplierTaxClearance::class, function (School $school): SupplierTaxClearance {
            $supplier = Supplier::factory()->for($school)->create();

            return SupplierTaxClearance::factory()->create(['school_id' => $school->id, 'supplier_id' => $supplier->id]);
        });

        TenantModelRegistry::register(PurchaseRequisition::class, fn (School $school): PurchaseRequisition => PurchaseRequisition::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(PurchaseRequisitionLine::class, function (School $school): PurchaseRequisitionLine {
            $requisition = PurchaseRequisition::factory()->create(['school_id' => $school->id]);
            $item = InventoryItem::factory()->for($school)->create();

            return PurchaseRequisitionLine::factory()->create(['school_id' => $school->id, 'requisition_id' => $requisition->id, 'item_id' => $item->id]);
        });

        TenantModelRegistry::register(QuotationRequest::class, fn (School $school): QuotationRequest => QuotationRequest::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Quotation::class, fn (School $school): Quotation => Quotation::factory()->create(['school_id' => $school->id]));

        // QuotationLine has no school_id/BelongsToSchool of its own — it is
        // reachable only through its parent Quotation, matching the spec's
        // own §2 schema for this table exactly — so it is never registered
        // here; there is no per-row tenancy of its own to isolation-test.
        TenantModelRegistry::register(PurchaseOrder::class, fn (School $school): PurchaseOrder => PurchaseOrder::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(PurchaseOrderLine::class, fn (School $school): PurchaseOrderLine => PurchaseOrderLine::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(GoodsReceivedNote::class, fn (School $school): GoodsReceivedNote => GoodsReceivedNote::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(GrnLine::class, fn (School $school): GrnLine => GrnLine::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SupplierInvoice::class, fn (School $school): SupplierInvoice => SupplierInvoice::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SupplierInvoiceLine::class, fn (School $school): SupplierInvoiceLine => SupplierInvoiceLine::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SupplierPayment::class, fn (School $school): SupplierPayment => SupplierPayment::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SupplierPaymentAllocation::class, fn (School $school): SupplierPaymentAllocation => SupplierPaymentAllocation::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SupplierContract::class, fn (School $school): SupplierContract => SupplierContract::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AssetCategory::class, fn (School $school): AssetCategory => AssetCategory::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(FixedAsset::class, fn (School $school): FixedAsset => FixedAsset::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(DepreciationRun::class, fn (School $school): DepreciationRun => DepreciationRun::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(DepreciationEntry::class, fn (School $school): DepreciationEntry => DepreciationEntry::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AssetMovement::class, fn (School $school): AssetMovement => AssetMovement::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AssetVerification::class, fn (School $school): AssetVerification => AssetVerification::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AssetDisposal::class, fn (School $school): AssetDisposal => AssetDisposal::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AssetInsurance::class, function (School $school): AssetInsurance {
            $category = AssetCategory::factory()->create(['school_id' => $school->id]);

            return AssetInsurance::factory()->create(['school_id' => $school->id, 'category_id' => $category->id]);
        });

        TenantModelRegistry::register(Budget::class, fn (School $school): Budget => Budget::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(BudgetLine::class, fn (School $school): BudgetLine => BudgetLine::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(BudgetCommitment::class, fn (School $school): BudgetCommitment => BudgetCommitment::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(BudgetVirement::class, fn (School $school): BudgetVirement => BudgetVirement::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(Forecast::class, fn (School $school): Forecast => Forecast::factory()->create(['school_id' => $school->id]));
    }

    /**
     * @return array{0: Store, 1: InventoryItem, 2: Term, 3: AcademicYear}
     */
    private function storeItemTerm(School $school): array
    {
        $store = Store::factory()->for($school)->create();
        $item = InventoryItem::factory()->for($school)->create();
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

        return [$store, $item, $term, $year];
    }
}

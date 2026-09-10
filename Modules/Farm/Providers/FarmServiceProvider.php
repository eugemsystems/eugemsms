<?php

declare(strict_types=1);

namespace Modules\Farm\Providers;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\CropInput;
use Modules\Farm\Models\FarmField;
use Modules\Farm\Models\FarmSale;
use Modules\Farm\Models\Harvest;
use Modules\Farm\Models\InternalTransfer;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\LivestockEvent;
use Modules\Farm\Models\ProductionOutput;
use Modules\Farm\Models\ProductionUnit;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H2 Domain F: Operations & Estates — `OPS-03` Estates, Farm &
 * Production Units, built after `OPS-04` per the book's own order.
 *
 * Real cross-module wiring in this pass: `fields.water_source_id`
 * (`OPS-04`, real FK); `crop_inputs.store_requisition_id` — a genuine
 * `FIN-09` requisition against the farm store, FIFO-costed
 * (`RecordCropInputAction`); `harvests.stock_lot_id`/`journal_id` — a
 * real `FIN-09` lot via the SAME `ReceiveStockAction` a direct stock
 * receipt and a `FIN-08` GRN line already use, its own docblock
 * naming `OPS-03`'s harvest by name (`RecordHarvestAction`);
 * `internal_transfers` moves real `FIN-09` stock between the farm and
 * kitchen stores through `DispatchStockTransferAction`/
 * `ReceiveStockTransferAction` — the same pair `FIN-09`'s own
 * inter-store transfer uses — at internal cost, one real journal
 * (`TransferToKitchenAction`); `livestock.fixed_asset_id` is a real
 * `FIN-10` capitalisation for breeding stock above threshold
 * (`CreateLivestockAction`) and a real `FIN-10` disposal on death when
 * the caller supplies what it needs to post
 * (`RecordLivestockEventAction`).
 *
 * Two deliberate, documented boundaries:
 *  - `RecordFarmSaleAction` (`BR-OPS-03-016`) posts its own journal
 *    directly rather than routing through `Modules\Finance`'s
 *    `CreateReceiptAction` (`FIN-04`) — that action is built around
 *    student-fee receipting (invoice allocation, till sessions) and
 *    doesn't fit an external buyer with no invoice.
 *  - `farm_sales.fiscal_receipt_id` stays a plain forward-reference
 *    column — `FIN-13` (Book H3, fiscalisation/compliance) doesn't
 *    exist yet, the same "not built yet" boundary `OPS-01`/`OPS-02`
 *    used for each other before both existed.
 *  - `BR-OPS-03-013`'s dipping/vaccination reminders have no schema
 *    to compute a "next due" date from — unlike `OPS-02`'s
 *    `MaintenanceSchedule`, this book's own data model gives farm
 *    treatments no analogous schedule table, so no `CheckOverdueTreatments`
 *    action or `TreatmentOverdue` event exists in this pass; recording
 *    an individual treatment (`RecordLivestockEventAction`) is fully
 *    built, only the reminder side is out of reach without inventing
 *    schema the spec itself doesn't provide.
 */
class FarmServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Farm';

    protected string $nameLower = 'farm';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
    }

    /**
     * Book H2 OPS-03 §4.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['farm.crop_cycle_failed', ['cycle.cycle_reference', 'failure_reason'], false],
            ['farm.withdrawal_period_blocked', ['livestock.tag_number', 'withdrawal_ends_on'], true],
            ['farm.livestock_death_recorded', ['livestock.tag_number'], false],
            ['farm.mortality_rate_exceeded', ['unit.name', 'mortality_percent'], true],
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
     * Book H2 OPS-03 §5.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['farm.overhead_allocation_basis', 'string', 'hectares', 'Basis for apportioning shared farm overhead to crop cycles: hectares or fixed_rate.'],
            ['farm.casual_labour_daily_rate_minor', 'int', '0', 'Default daily rate for casual farm labour with no recorded PPL-04 rate of their own.'],
            ['farm.capitalise_breeding_stock', 'bool', '1', 'Whether breeding-purpose livestock above the capitalisation threshold becomes a FIN-10 fixed asset.'],
            ['farm.breeding_stock_capitalisation_threshold_minor', 'int', '0', 'Acquisition cost, in minor units, at or above which breeding stock capitalises.'],
            ['farm.livestock_mortality_alert_percent', 'int', '5', 'Mortality rate, as a percentage of population, above which the farm manager and bursar are alerted.'],
            ['farm.enforce_withdrawal_periods', 'bool', '1', 'Whether TransferToKitchenAction blocks milk/meat still within a recorded withdrawal period (locked true).'],
            ['farm.record_market_prices', 'bool', '1', 'Whether transfers are expected to record a market price for the savings report.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'OPS',
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
        TenantModelRegistry::register(ProductionUnit::class, fn (School $school): ProductionUnit => ProductionUnit::factory()->for($school)->create());

        TenantModelRegistry::register(FarmField::class, fn (School $school): FarmField => FarmField::factory()->for($school)->create());

        TenantModelRegistry::register(CropCycle::class, fn (School $school): CropCycle => CropCycle::factory()->for($school)->create());

        TenantModelRegistry::register(CropInput::class, fn (School $school): CropInput => CropInput::factory()->for($school)->create());

        TenantModelRegistry::register(Harvest::class, fn (School $school): Harvest => Harvest::factory()->for($school)->create());

        TenantModelRegistry::register(Livestock::class, fn (School $school): Livestock => Livestock::factory()->for($school)->create());

        TenantModelRegistry::register(LivestockEvent::class, fn (School $school): LivestockEvent => LivestockEvent::factory()->for($school)->create());

        TenantModelRegistry::register(ProductionOutput::class, fn (School $school): ProductionOutput => ProductionOutput::factory()->for($school)->create());

        TenantModelRegistry::register(InternalTransfer::class, fn (School $school): InternalTransfer => InternalTransfer::factory()->for($school)->create());

        TenantModelRegistry::register(FarmSale::class, fn (School $school): FarmSale => FarmSale::factory()->for($school)->create());
    }
}

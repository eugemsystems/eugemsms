<?php

declare(strict_types=1);

namespace Modules\Fiscal\Providers;

use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Events\FarmSaleRecorded;
use Modules\Finance\Domain\Events\ReceiptPosted;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\Listeners\RouteFarmSaleListener;
use Modules\Fiscal\Domain\Listeners\RouteFinanceReceiptListener;
use Modules\Fiscal\Domain\Support\CloseChecks\FiscalisationReconciledCheck;
use Modules\Fiscal\Domain\Support\FakeFiscalGatewayDriver;
use Modules\Fiscal\Models\FiscalAuditLogEntry;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalisationRule;
use Modules\Fiscal\Models\FiscalReceipt;
use Modules\Fiscal\Models\FiscalZReport;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H3 FIN-13 — ZIMRA Fiscalisation (FDMS), built after `PPL-05`
 * per the book's own order.
 *
 * The cardinal rule (§4 ⭐/BR-FIN-13-001) is structural, not a
 * convention to remember: `RouteReceiptForFiscalisationAction` does
 * only cheap local writes and NEVER calls the gateway itself;
 * `SubmitFiscalReceiptAction` is the one caller of
 * `FiscalGatewayDriver::submitReceipt()`, and it never throws — an
 * unreachable FDMS moves a receipt to `offline_queued`, never back up
 * the call stack to whatever posted the underlying commercial
 * receipt. `DrainOfflineFiscalQueueAction` retries in counter order.
 *
 * Real, additive retro-fit onto two already-shipped tables that were
 * deliberately left with a forward-reference for this module:
 * `Modules\Finance`'s `receipts.fiscalisation_status` (set by
 * `CreateReceiptAction` already, consumed here for the first time by
 * `RouteFinanceReceiptListener` on the already-dispatched
 * `ReceiptPosted` event) and `Modules\Farm`'s
 * `farm_sales.fiscal_receipt_id` (populated here for the first time
 * by `RouteFarmSaleListener` on `FarmSaleRecorded` — extended with an
 * additive `performedByUserId` payload field, since `FarmSale` itself
 * carries no user reference to open a fiscal day with). Neither
 * listener edits the action that fires its event.
 *
 * `FiscalGatewayDriver` is bound directly to `FakeFiscalGatewayDriver`
 * — deterministic and inspectable rather than a mock, mirroring
 * `Modules\Finance`'s own `PaymentGatewayDriver`/
 * `FakePaymentGatewayDriver` pattern; see that fake driver's own
 * docblock for why a per-key registry isn't warranted for a single
 * national tax authority, and why a real ZIMRA driver is deferred
 * (no sandbox credentials, no documented API to build against
 * honestly).
 *
 * One documented boundary beyond the retro-fit above:
 * `Modules\Facilities`'s hire bookings (`OPS-05`, the third source
 * Appendix A names) carry no `fiscal_receipt_id`/equivalent forward
 * reference at all — unlike `receipts`/`farm_sales`, nothing
 * anticipated this module there, and adding one now would mean
 * editing an already-gated Book H2 action's own persisted shape
 * beyond a plain additive column. Deferred, not silently skipped.
 */
class FiscalServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Fiscal';

    protected string $nameLower = 'fiscal';

    public function register(): void
    {
        parent::register();

        $this->app->bind(FiscalGatewayDriver::class, FakeFiscalGatewayDriver::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerListeners();
        $this->registerCloseChecklistItems();
    }

    /**
     * Book H3 FIN-12 §4 — this module's own entry on the real
     * `Modules\Core\Domain\Registry\CloseChecklistRegistry`.
     */
    private function registerCloseChecklistItems(): void
    {
        CloseChecklistRegistry::register($this->app->make(FiscalisationReconciledCheck::class));
    }

    private function registerListeners(): void
    {
        Event::listen(ReceiptPosted::class, RouteFinanceReceiptListener::class);
        Event::listen(FarmSaleRecorded::class, RouteFarmSaleListener::class);
    }

    /**
     * Book H3 FIN-13 §8.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['fiscal.certificate_expiring', ['device.device_id', 'days_until_expiry'], true],
            ['fiscal.fiscal_day_close_failed', ['day.fiscal_day_number', 'reason'], true],
            ['fiscal.receipt_rejected', ['receipt.invoice_number', 'error_message'], true],
            ['fiscal.offline_queue_backlog', ['queue_depth'], true],
            ['fiscal.reconciliation_exception', ['unreconciled_count'], true],
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
     * Book H3 FIN-13 §7.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['fiscal.enabled', 'bool', '0', 'Whether fiscalisation is active for this school.'],
            ['fiscal.environment', 'string', 'sandbox', 'Default environment new devices register against.'],
            ['fiscal.auto_open_day', 'bool', '1', 'Whether a fiscal day opens automatically at the first fiscalisable receipt.'],
            ['fiscal.submission_retry_attempts', 'int', '10', 'Maximum retry attempts for a queued receipt before it stops auto-retrying (BR-FIN-13-009).'],
            ['fiscal.offline_queue_alert_depth', 'int', '50', 'Offline queue depth at or above which a backlog alert fires (BR-FIN-13-010).'],
            ['fiscal.certificate_alert_days', 'json', '[60,30,7]', 'Days before certificate expiry that an alert fires (BR-FIN-13-014).'],
            ['fiscal.reconciliation_window_hours', 'int', '24', 'Hours a fiscalisable receipt may remain unaccepted before it appears on the reconciliation exception report (BR-FIN-13-021).'],
            ['fiscal.block_period_close_on_unfiscalised', 'bool', '1', 'Whether an unreconciled fiscal receipt blocks financial period close.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'FIN',
                'group_key' => 'fiscal',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => match ($dataType) {
                    'bool' => 'toggle',
                    'json' => 'tags',
                    default => 'text',
                },
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
        TenantModelRegistry::register(FiscalDevice::class, fn (School $school): FiscalDevice => FiscalDevice::factory()->for($school)->create());

        TenantModelRegistry::register(FiscalDay::class, fn (School $school): FiscalDay => FiscalDay::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(FiscalReceipt::class, fn (School $school): FiscalReceipt => FiscalReceipt::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(FiscalisationRule::class, fn (School $school): FiscalisationRule => FiscalisationRule::factory()->for($school)->create());

        TenantModelRegistry::register(FiscalZReport::class, fn (School $school): FiscalZReport => FiscalZReport::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(FiscalAuditLogEntry::class, fn (School $school): FiscalAuditLogEntry => FiscalAuditLogEntry::factory()->for($school)->create());
    }
}

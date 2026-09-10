<?php

declare(strict_types=1);

namespace Modules\Wallet\Providers;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Wallet\Domain\Support\CloseChecks\WalletLiabilityReconcilesCheck;
use Modules\Wallet\Models\SpendPoint;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletProduct;
use Modules\Wallet\Models\WalletSale;
use Modules\Wallet\Models\WalletSaleLine;
use Modules\Wallet\Models\WalletTransaction;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H3 FIN-14 — Student Wallet & Tuckshop, built after `FIN-13`
 * per the book's own order.
 *
 * Real cross-module wiring: `ProcessWalletSaleAction` depletes stock
 * through `Modules\Stores`'s `StockCostingEngine` directly (the same
 * FIFO engine `IssueSaleableItemToLearnerAction` uses — not that
 * action itself, since its revenue mechanism, a debtor charge, is
 * wrong for a wallet sale) and routes fiscalisable sales through the
 * real `Modules\Fiscal\Domain\Actions\RouteReceiptForFiscalisationAction`
 * (Book H3 FIN-13, already built this book — no forward-reference
 * placeholder needed, unlike `Modules\Farm`'s `farm_sales` when it
 * shipped before `FIN-13` existed). `VoidWalletSaleAction` reverses
 * through the real `Modules\Finance\Domain\Actions\ReverseJournalAction`
 * and, where the voided sale was fiscalised, the real
 * `RaiseFiscalCreditNoteAction`. `ProcessTermEndWalletAction`'s
 * `transfer_to_fees` policy mirrors `Modules\Payroll`'s own
 * staff-child fee-offset journal shape exactly — a plain `Cr Fee
 * Debtors` subledgered to the student, the established convention
 * for "credit this student's fee account directly" in this codebase.
 *
 * Two deliberate, documented boundaries:
 *  - BR-FIN-14-004's "or an explicit wallet-control right" is
 *    unmodelled — only a guardian's `is_fee_responsible` flag gates
 *    `SetWalletControlsAction` (see
 *    `WalletControlRightRequiredException`'s own docblock).
 *  - BR-FIN-14-008's auto top-up (`FIN-05` payment intent) is not
 *    built — `Modules\Finance`'s own `PaymentGatewayDriver` has only
 *    `FakePaymentGatewayDriver` registered (no real gateway
 *    credentials exist in this pass), so there is nothing for an
 *    auto-top-up trigger to call that would be more than a
 *    duplicate of that same honest gap.
 */
class WalletServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Wallet';

    protected string $nameLower = 'wallet';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerCloseChecklistItems();
    }

    /**
     * Book H3 FIN-12 §4 — this module's own entry on the real
     * `Modules\Core\Domain\Registry\CloseChecklistRegistry`.
     */
    private function registerCloseChecklistItems(): void
    {
        CloseChecklistRegistry::register($this->app->make(WalletLiabilityReconcilesCheck::class));
    }

    /**
     * Book H3 FIN-14 §6.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['wallet.low_balance', ['student.first_name', 'balance_minor'], false],
            ['wallet.negative_balance', ['student.first_name', 'balance_minor'], true],
            ['wallet.reconciliation_variance', ['variance_minor'], true],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['sms', 'email'],
                defaultAudience: 'guardian',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book H3 FIN-14 §6.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['wallet.enabled', 'bool', '0', 'Whether the student wallet is active for this school.'],
            ['wallet.allow_negative_minor', 'int', '500', 'Maximum a wallet may go negative when an offline sale syncs against an insufficient balance (BR-FIN-14-012).'],
            ['wallet.pos_cache_refresh_minutes', 'int', '5', 'How often the offline POS terminal refreshes its cached balances, controls and catalogue.'],
            ['wallet.term_end_policy', 'string', 'carry_forward', 'Default term-end handling — carry_forward, refund, or transfer_to_fees.'],
            ['wallet.low_balance_default_minor', 'int', '500', 'Default low-balance alert threshold for a wallet with no guardian-set value.'],
            ['wallet.daily_limit_default_minor', 'int', '0', 'Default daily spending limit; 0 means unlimited.'],
            ['wallet.limit_reset_boundary', 'string', 'calendar_day', 'Boundary daily/weekly limits reset on.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'FIN',
                'group_key' => 'wallet',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => match ($dataType) {
                    'bool' => 'toggle',
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
        TenantModelRegistry::register(StudentWallet::class, fn (School $school): StudentWallet => StudentWallet::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(WalletTransaction::class, fn (School $school): WalletTransaction => WalletTransaction::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(SpendPoint::class, fn (School $school): SpendPoint => SpendPoint::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(WalletProduct::class, fn (School $school): WalletProduct => WalletProduct::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(WalletSale::class, fn (School $school): WalletSale => WalletSale::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(WalletSaleLine::class, fn (School $school): WalletSaleLine => WalletSaleLine::factory()->create(['school_id' => $school->id]));
    }
}

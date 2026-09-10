<?php

declare(strict_types=1);

namespace Modules\Reporting\Providers;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Reporting\Models\AccountingExport;
use Modules\Reporting\Models\CloseCheckAcknowledgement;
use Modules\Reporting\Models\PeriodCloseChecklist;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportSchedule;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H3 FIN-12 — Financial Reporting & Period Close, the last of
 * this book's three FIN modules (`PPL-05` → `FIN-13` → `FIN-14` →
 * `FIN-12`, per the book's own order).
 *
 * The close-checklist GATE (BR-FIN-12-011 — a period cannot `LOCK`
 * without zero blocking failures) is not built here at all: it
 * already exists, real and working, from Book A CORE-03
 * (`Modules\Core\Domain\Actions\Sessions\TransitionPeriodStateAction`
 * calls `Modules\Core\Domain\Registry\CloseChecklistRegistry` directly).
 * What CORE-03 left genuinely empty was the registry's own CONTENTS —
 * zero `CloseChecklistItem`s were registered by any module before
 * this pass. This module's real job is threefold: (1) register real
 * checks from the modules that own the data they check —
 * `Modules\Finance` (trial balance, suspense, till sessions, draft
 * invoices), `Modules\Fiscal` (fiscalisation reconciled, reusing the
 * real `ReconcileFiscalisationAction` directly), `Modules\Wallet`
 * (wallet liability, reusing `ReconcileWalletLiabilityAction`), and
 * `Modules\Payroll` (payroll posted and returns prepared) — each
 * registered from that module's OWN provider, never from here,
 * matching `CloseChecklistRegistry`'s own "code owns the list"
 * split; (2) `RunAndRecordCloseChecklistAction`, the durable RECORD
 * of a run (`RunPeriodCloseChecklistAction`, Book A's own real-time
 * engine, is called unchanged); (3) point-in-time reporting
 * (`GenerateIncomeStatementAction`/`GenerateTrialBalanceAction`),
 * genuinely filtering on `journal_lines.posted_at` per
 * BR-FIN-12-004 — a filter, not a reconstruction, since `posted_at`
 * and `effective_at` are already separate columns on every journal
 * (Book B FIN-01's own doctrine).
 *
 * Real gaps this pass does NOT close, honestly: the spec's own
 * eighteen-row close-check table (§4) names several checks no module
 * registers yet — cached-balance verification, gateway/bank
 * reconciliation (`FIN-05`), asset-register-matches-ledger and
 * monthly depreciation (`FIN-10`), supplier accruals (`FIN-08`),
 * open commitments (`FIN-11`), and the CORE-03 carry-forward
 * invariant itself. The registry ARCHITECTURE is complete and
 * extensible — adding one of these later is exactly one new
 * `CloseChecklistItem` class plus one `register()` call in its own
 * owning module's provider, the same shape as every check registered
 * here, not a structural change. `GenerateClosePackAction`'s "signed
 * PDF pack" is a signed JSON document (content-hashed via the real
 * `Core\Domain\Actions\Files\UploadFileAction`) — genuinely complete
 * in content, simpler in presentation format. Scheduled report
 * delivery (`report_schedules.next_run_at` actually firing) has no
 * scheduler wired — the table and `CreateReportScheduleAction` exist,
 * nothing drains `next_run_at` yet.
 */
class ReportingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Reporting';

    protected string $nameLower = 'reporting';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
    }

    /**
     * Book H3 FIN-12 §6.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['reporting.close_check_failed', ['checklist.id', 'failure.label'], true],
            ['reporting.close_pack_generated', ['checklist.id'], false],
            ['reporting.scheduled_report_delivered', ['schedule.name'], false],
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
     * Book H3 FIN-12 §6.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['reporting.default_comparative_periods', 'int', '1', 'Number of comparative periods shown on a statement by default.'],
            ['reporting.show_budget_column', 'bool', '1', 'Whether statements show a budget comparison column by default.'],
            ['reporting.close_pack_recipients', 'json', '[]', 'User ids who receive the close pack once generated.'],
            ['reporting.overhead_allocation_basis', 'string', 'learner_count', 'Basis departmental P&L allocates shared overhead by.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'FIN',
                'group_key' => 'reporting',
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
        TenantModelRegistry::register(ReportDefinition::class, fn (School $school): ReportDefinition => ReportDefinition::factory()->for($school)->create());

        TenantModelRegistry::register(PeriodCloseChecklist::class, fn (School $school): PeriodCloseChecklist => PeriodCloseChecklist::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(CloseCheckAcknowledgement::class, fn (School $school): CloseCheckAcknowledgement => CloseCheckAcknowledgement::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ReportSchedule::class, fn (School $school): ReportSchedule => ReportSchedule::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(AccountingExport::class, fn (School $school): AccountingExport => AccountingExport::factory()->for($school)->create());
    }
}

<?php

declare(strict_types=1);

namespace Modules\Saas\Providers;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Finance\Models\Journal;
use Modules\Intelligence\Domain\DataObjects\RiskIndicatorResult;
use Modules\Saas\Domain\DataObjects\ChurnRiskIndicatorDefinition;
use Modules\Saas\Domain\DataObjects\ModuleAdoptionSignalDefinition;
use Modules\Saas\Domain\Registry\ChurnRiskIndicatorRegistry;
use Modules\Saas\Domain\Registry\ModuleAdoptionSignalRegistry;
use Modules\Saas\Models\ModuleAdoptionScore;
use Modules\Saas\Models\SupportTicket;
use Modules\Saas\Models\TrainingCompletion;
use Modules\Welfare\Models\SickBayAdmission;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book J SAA-01/02/03 — Licensing & Subscription, Vendor Control
 * Centre, Onboarding & Customer Success.
 */
class SaasServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Saas';

    protected string $nameLower = 'saas';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
        $this->registerModuleAdoptionSignals();
        $this->registerChurnRiskIndicators();
    }

    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(
            TrainingCompletion::class,
            fn (School $school): TrainingCompletion => TrainingCompletion::factory()->create(['school_id' => $school->id]),
        );
    }

    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['saas.usage_soft_warning_threshold_percent', 'int', '90', 'Usage percentage of a plan limit at which a soft warning is sent (BR-SAA-01-003).'],
            ['saas.onboarding_stall_threshold_days', 'int', '14', 'Days without onboarding progress before the assigned success manager is alerted (BR-SAA-03-001).'],
            ['saas.support_sla_hours_low', 'int', '120', 'Support ticket SLA, low priority.'],
            ['saas.support_sla_hours_normal', 'int', '48', 'Support ticket SLA, normal priority.'],
            ['saas.support_sla_hours_high', 'int', '8', 'Support ticket SLA, high priority.'],
            ['saas.support_sla_hours_urgent', 'int', '2', 'Support ticket SLA, urgent priority.'],
            ['saas.support_sla_warning_hours_before', 'int', '2', 'Hours before SLA breach a support ticket assignee is warned.'],
            ['saas.dormant_module_sustained_months', 'int', '3', 'Consecutive months of zero recorded activity before an entitled module surfaces as dormant (BR-SAA-03-007).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'SAA',
                'group_key' => explode('.', $key)[0],
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => 'text',
                'lowest_scope' => 'system',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'saas.onboarding_checklist_stalled',
            variables: [],
            defaultChannels: ['email'],
            defaultAudience: 'user',
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'saas.support_ticket_sla_breached',
            variables: [],
            defaultChannels: ['email'],
            defaultAudience: 'user',
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'saas.support_ticket_sla_approaching',
            variables: [],
            defaultChannels: ['email'],
            defaultAudience: 'user',
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'saas.release_notes',
            variables: ['module_name', 'version', 'summary'],
            defaultChannels: ['email'],
            defaultAudience: 'user',
        ));
    }

    /**
     * Book J SAA-03 §3 ⭐/BR-SAA-03-006 ⭐. Two real signals — the
     * spec's own worked example (§3): `FIN-01`'s `journal_posted`
     * (`Modules\Finance\Models\Journal.posted_at`) and `BRD-06`'s
     * `sick_bay_admission_recorded` (`Modules\Welfare\Models\SickBayAdmission.admitted_at`)
     * — not one signal per module in the catalogue, the documented
     * boundary `RiskIndicatorRegistry` already draws.
     */
    private function registerModuleAdoptionSignals(): void
    {
        ModuleAdoptionSignalRegistry::register(new ModuleAdoptionSignalDefinition(
            moduleCode: 'FIN',
            activitySignal: 'journal_posted',
            resolver: function (int $schoolId, string $periodMonth): int {
                [$start, $end] = $this->monthBounds($periodMonth);

                // withoutGlobalScopes(): this runs with no ambient
                // SchoolContext (a cross-school adoption sweep, not a
                // single school's request) — see
                // ToggleSchoolModuleAction's own docblock.
                return Journal::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->whereBetween('posted_at', [$start, $end])
                    ->count();
            },
        ));

        ModuleAdoptionSignalRegistry::register(new ModuleAdoptionSignalDefinition(
            moduleCode: 'BRD',
            activitySignal: 'sick_bay_admission_recorded',
            resolver: function (int $schoolId, string $periodMonth): int {
                [$start, $end] = $this->monthBounds($periodMonth);

                return SickBayAdmission::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->whereBetween('admitted_at', [$start, $end])
                    ->count();
            },
        ));
    }

    /**
     * Book J SAA-03 §4/BR-SAA-03-008. Three real tenant-level
     * signals: login recency across the tenant's own users, the
     * proportion of the tenant's schools' entitled modules that
     * genuinely sit dormant (reads `ModuleAdoptionScore` directly,
     * never recomputes it), and open support ticket volume.
     */
    private function registerChurnRiskIndicators(): void
    {
        ChurnRiskIndicatorRegistry::register(new ChurnRiskIndicatorDefinition(
            key: 'login_recency',
            moduleCode: 'SAA-03',
            plainLanguageDescription: 'No user at this tenant has logged in recently.',
            defaultWeight: 40,
            resolver: function (int $tenantId): ?RiskIndicatorResult {
                $lastLogin = User::where('tenant_id', $tenantId)->max('last_login_at');

                if ($lastLogin === null) {
                    return null;
                }

                $daysAgo = (int) Carbon::now()->diffInDays(Carbon::parse($lastLogin), absolute: true);

                if ($daysAgo <= 14) {
                    return null;
                }

                return new RiskIndicatorResult(
                    severityPercent: min(100.0, round(($daysAgo - 14) / 60 * 100, 2)),
                    plainLanguage: "No recorded login for {$daysAgo} days across this tenant's users",
                    source: 'users.last_login_at, most recent across the tenant',
                );
            },
        ));

        ChurnRiskIndicatorRegistry::register(new ChurnRiskIndicatorDefinition(
            key: 'dormant_module_ratio',
            moduleCode: 'SAA-03',
            plainLanguageDescription: 'A large share of entitled modules show no recorded activity this month.',
            defaultWeight: 35,
            resolver: function (int $tenantId): ?RiskIndicatorResult {
                $periodMonth = Carbon::today()->format('Y-m');
                $schoolIds = School::where('tenant_id', $tenantId)->pluck('id');

                $scores = ModuleAdoptionScore::whereIn('school_id', $schoolIds)->where('period_month', $periodMonth)->get();

                if ($scores->isEmpty()) {
                    return null;
                }

                $dormantRatio = $scores->where('is_actively_used', false)->count() / $scores->count();

                if ($dormantRatio <= 0.3) {
                    return null;
                }

                return new RiskIndicatorResult(
                    severityPercent: round($dormantRatio * 100, 2),
                    plainLanguage: sprintf('%d%% of this tenant\'s entitled modules show no activity this month', round($dormantRatio * 100)),
                    source: "module_adoption_scores, period {$periodMonth}",
                );
            },
        ));

        ChurnRiskIndicatorRegistry::register(new ChurnRiskIndicatorDefinition(
            key: 'open_support_tickets',
            moduleCode: 'SAA-03',
            plainLanguageDescription: 'This tenant has an elevated number of open support tickets.',
            defaultWeight: 25,
            resolver: function (int $tenantId): ?RiskIndicatorResult {
                $openCount = SupportTicket::where('tenant_id', $tenantId)
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->count();

                if ($openCount < 3) {
                    return null;
                }

                return new RiskIndicatorResult(
                    severityPercent: min(100.0, round($openCount / 10 * 100, 2)),
                    plainLanguage: "{$openCount} open support tickets for this tenant",
                    source: 'support_tickets, current open count',
                );
            },
        ));
    }

    /**
     * @return array{0: string, 1: string} [monthStart, monthEnd] as
     *                                     'Y-m-d H:i:s' bounds
     */
    private function monthBounds(string $periodMonth): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $periodMonth.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        return [$start->toDateTimeString(), $end->toDateTimeString()];
    }
}

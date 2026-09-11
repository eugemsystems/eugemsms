<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\IntegrityCheckRun;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Saas\Models\ModuleAdoptionScore;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SupportTicket;
use Modules\Saas\Models\TenantHealthSnapshot;

/**
 * ACT-ComputeTenantHealthSnapshot (Book J SAA-02 §2/§3 ⭐/BR-SAA-02-003
 * (AC-SAA-02-004)). Meant to run nightly per tenant — the same
 * "bookkeeping real, wiring deferred" boundary this book set already
 * draws. Every component is a REAL signal read from its owning table
 * (never recomputed elsewhere): `SAA-03`'s `ModuleAdoptionScore` and
 * `SupportTicket`, `CORE-08`'s own `IntegrityCheckRun`, `users.last_login_at`,
 * `SAA-01`'s `Subscription`. `health_score` is this pass's own
 * reasonable, documented formula — the spec asks for objective
 * component signals, not a literal scoring formula, the same honesty
 * boundary `Modules\Intelligence\Domain\Actions\ComputeLearnerRiskScoreAction`
 * already draws for its own band cutoffs — and every component stays
 * visible on the row alongside it (§3 ⭐), never folded away.
 */
final class ComputeTenantHealthSnapshotAction extends Action
{
    public function execute(int $tenantId): TenantHealthSnapshot
    {
        $schoolIds = School::where('tenant_id', $tenantId)->pluck('id');

        $activeSchools = School::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $activeLearners = Student::withoutGlobalScopes()->whereIn('school_id', $schoolIds)->where('status', 'active')->count();

        $subscription = Subscription::where('tenant_id', $tenantId)->latest('id')->first();
        $subscriptionStatus = $subscription->status ?? 'cancelled';

        $lastLogin = User::where('tenant_id', $tenantId)->max('last_login_at');
        $lastLoginDaysAgo = $lastLogin !== null ? (int) Carbon::now()->diffInDays(Carbon::parse($lastLogin), absolute: true) : null;

        $periodMonth = Carbon::today()->format('Y-m');
        $adoptionScores = ModuleAdoptionScore::whereIn('school_id', $schoolIds)->where('period_month', $periodMonth)->get();
        $adoptionPercent = $adoptionScores->isNotEmpty()
            ? round($adoptionScores->where('is_actively_used', true)->count() / $adoptionScores->count() * 100, 2)
            : null;

        $openTickets = SupportTicket::where('tenant_id', $tenantId)->whereNotIn('status', ['resolved', 'closed'])->count();

        $integrityFailures = (int) IntegrityCheckRun::whereIn('school_id', $schoolIds)
            ->where('ran_at', '>=', Carbon::now()->subDay())
            ->sum('failures_found');

        $healthScore = $this->score($subscriptionStatus, $lastLoginDaysAgo, $adoptionPercent, $openTickets, $integrityFailures);

        return $this->transaction(fn (): TenantHealthSnapshot => TenantHealthSnapshot::updateOrCreate(
            ['tenant_id' => $tenantId, 'snapshot_date' => Carbon::today()->toDateString()],
            [
                'active_schools' => $activeSchools,
                'active_learners' => $activeLearners,
                'subscription_status' => $subscriptionStatus,
                'last_login_days_ago' => $lastLoginDaysAgo,
                'module_adoption_percent' => $adoptionPercent,
                'open_support_tickets' => $openTickets,
                'integrity_check_failures' => $integrityFailures,
                'health_score' => $healthScore,
            ],
        ));
    }

    private function score(
        string $subscriptionStatus,
        ?int $lastLoginDaysAgo,
        ?float $adoptionPercent,
        int $openTickets,
        int $integrityFailures,
    ): float {
        $score = 100.0;

        if (! in_array($subscriptionStatus, ['active', 'trial'], true)) {
            $score -= 20.0;
        }

        if ($lastLoginDaysAgo !== null && $lastLoginDaysAgo > 14) {
            $score -= min(30.0, ($lastLoginDaysAgo - 14) / 2);
        }

        if ($adoptionPercent !== null && $adoptionPercent < 50.0) {
            $score -= (50.0 - $adoptionPercent) / 2;
        }

        $score -= min(20.0, $openTickets * 3.0);
        $score -= min(30.0, $integrityFailures * 10.0);

        return max(0.0, round($score, 2));
    }
}

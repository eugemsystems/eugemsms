<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Boarding\Domain\Support\DietaryAlert;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-GetDietaryAlerts (Book F BRD-04 §3/§4 ⭐/BR-BRD-04-009/011).
 * The serving terminal's own query — every active requirement at or
 * above `catering.dietary_alert_min_severity`, every time a learner
 * is scanned, not only the first meal.
 */
final class GetDietaryAlertsAction extends Action
{
    /**
     * @var array<string, int>
     */
    private const array SEVERITY_RANK = ['preference' => 0, 'moderate' => 1, 'severe' => 2, 'life_threatening' => 3];

    /**
     * @return Collection<int, DietaryAlert>
     */
    public function execute(int $studentId, string $minSeverity = 'moderate'): Collection
    {
        $minRank = self::SEVERITY_RANK[$minSeverity] ?? 1;

        return DietaryRequirement::query()
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (DietaryRequirement $r): bool => (self::SEVERITY_RANK[$r->severity] ?? 0) >= $minRank)
            ->map(fn (DietaryRequirement $r): DietaryAlert => new DietaryAlert(
                requirementType: $r->requirement_type,
                severity: $r->severity,
                description: $r->description,
                requiresEpipen: $r->requires_epipen,
                isVerified: $r->verified_by_nurse,
            ))
            ->values();
    }
}

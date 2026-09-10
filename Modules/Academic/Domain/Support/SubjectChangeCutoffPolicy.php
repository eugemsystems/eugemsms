<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\Exceptions\SubjectChangeRequiresApprovalException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Term;

/**
 * Book D ACA-02 §5/BR-ACA-02-005 (AC-ACA-02-007). Shared by
 * `EnrolSubjectAction` and `DropSubjectAction` — a change attempted
 * after `academic.subject_change_cutoff_week` already changes an
 * issued invoice and must route through approval.
 */
final class SubjectChangeCutoffPolicy
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function assertWithinCutoff(Term $term, int $schoolId): void
    {
        $cutoffWeek = (int) $this->settings->get('academic.subject_change_cutoff_week', new ScopeChain(schoolId: $schoolId));

        $termStart = $term->starts_on->copy()->startOfDay();
        $today = Carbon::now()->startOfDay();

        if ($today->lessThan($termStart)) {
            return;
        }

        $currentWeek = (int) floor($termStart->diffInDays($today, absolute: true) / 7) + 1;

        if ($currentWeek > $cutoffWeek) {
            throw SubjectChangeRequiresApprovalException::pastCutoff($cutoffWeek);
        }
    }
}

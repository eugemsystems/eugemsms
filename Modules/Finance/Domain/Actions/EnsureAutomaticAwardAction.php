<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\Support\SiblingDiscountEvaluator;
use Modules\Finance\Domain\Support\StaffChildDiscountEvaluator;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Models\Student;

/**
 * ACT-EnsureAutomaticAward (Book K FIN-07 §3/§4/BR-FIN-07-002/003/004
 * (AC-FIN-07-001/002)). Called by `AwardDiscountResolver` on every
 * billing run for every `scheme_type = 'automatic'` scheme — a
 * sibling or staff-child discount is not a one-time grant, it
 * re-evaluates against the learner's live household/employment data
 * every time. Idempotent: `firstOrCreate`/`update`, never a duplicate
 * award for the same (school, scheme, student, year) — `term_id` is
 * always null here (whole-year), consistent with these two schemes
 * never being termly.
 */
final class EnsureAutomaticAwardAction extends Action
{
    public function __construct(
        private readonly SiblingDiscountEvaluator $siblingEvaluator,
        private readonly StaffChildDiscountEvaluator $staffChildEvaluator,
    ) {}

    public function execute(Student $student, DiscountScheme $scheme, int $academicYearId): ?DiscountAward
    {
        $eligibility = match ($scheme->category) {
            'sibling' => $this->siblingEvaluator->tierFor($student, $scheme),
            'staff' => $this->staffChildEvaluator->isEligible($student, $scheme->school_id)
                ? ['percent' => (string) ($scheme->default_percent ?? '0')]
                : null,
            default => null,
        };

        $existing = DiscountAward::withoutGlobalScopes()
            ->where('school_id', $scheme->school_id)
            ->where('scheme_id', $scheme->id)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('term_id')
            ->first();

        if ($eligibility === null) {
            if ($existing !== null && $existing->status === 'active') {
                $this->transaction(fn () => $existing->update(['status' => 'ended']));
            }

            return null;
        }

        return $this->transaction(function () use ($existing, $eligibility, $student, $scheme, $academicYearId): DiscountAward {
            if ($existing !== null) {
                $existing->update(['status' => 'active', 'award_percent' => $eligibility['percent']]);

                return $existing->fresh();
            }

            return DiscountAward::create([
                'school_id' => $scheme->school_id,
                'scheme_id' => $scheme->id,
                'student_id' => $student->id,
                'academic_year_id' => $academicYearId,
                'term_id' => null,
                'award_method' => 'percentage',
                'award_percent' => $eligibility['percent'],
                'currency' => $scheme->currency,
                'effective_from' => Carbon::today()->toDateString(),
                'status' => 'active',
                'granted_by' => null,
            ]);
        });
    }
}

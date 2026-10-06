<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Modules\Finance\Models\DiscountScheme;
use Modules\People\Models\Household;
use Modules\People\Models\HouseholdMember;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentSibling;

/**
 * Book K FIN-07 §4/BR-FIN-07-002/003. "Household" has no dedicated
 * table in this codebase — siblings are students who share at least
 * one active `StudentGuardian` link, exactly the relationship `PPL-03`
 * already models. Explicit sibling links and members of a sibling-discount-eligible
 * household (PPL-01/PPL-03) count as siblings too. Rank is by `date_of_birth` ascending (the eldest
 * enrolled sibling is "1st child", never discounted); tier bands are
 * matched by rank, falling to the highest configured band for any
 * rank beyond the last one defined (the spec's own worked example:
 * "4th and beyond 20%").
 */
final class SiblingDiscountEvaluator
{
    /**
     * @return array{percent: string, rank: int}|null null when this
     *                                                student is the
     *                                                eldest (or only)
     *                                                active sibling —
     *                                                not discounted
     */
    public function tierFor(Student $student, DiscountScheme $scheme): ?array
    {
        $guardianIds = StudentGuardian::withoutGlobalScopes()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('guardian_id');

        $linkedIds = StudentSibling::withoutGlobalScopes()->where('student_id', $student->id)->pluck('sibling_student_id');

        $householdIds = HouseholdMember::withoutGlobalScopes()->where('member_type', 'student')->where('member_id', $student->id)->whereNull('left_on')
            ->whereIn('household_id', Household::withoutGlobalScopes()->where('sibling_discount_eligible', true)->select('id'))->pluck('household_id');
        $householdMemberIds = $householdIds->isEmpty() ? collect() : HouseholdMember::withoutGlobalScopes()->whereIn('household_id', $householdIds)->where('member_type', 'student')->whereNull('left_on')->pluck('member_id');

        if ($guardianIds->isEmpty() && $linkedIds->isEmpty() && $householdMemberIds->isEmpty()) {
            return null;
        }

        $householdStudentIds = StudentGuardian::withoutGlobalScopes()
            ->whereIn('guardian_id', $guardianIds)
            ->where('status', 'active')
            ->pluck('student_id')
            ->merge($linkedIds)
            ->merge($householdMemberIds)
            ->push($student->id)
            ->unique();

        $siblings = Student::withoutGlobalScopes()
            ->whereIn('id', $householdStudentIds)
            ->whereIn('status', ['enrolled', 'active', 'suspended'])
            ->orderBy('date_of_birth')
            ->get(['id', 'date_of_birth']);

        $rank = $siblings->search(fn (Student $s): bool => $s->id === $student->id);

        if ($rank === false || $rank === 0) {
            return null;
        }

        $rank++; // 0-indexed search -> 1-indexed "nth child"

        $bands = collect($scheme->tier_bands ?? [])->sortBy('nth')->values();

        if ($bands->isEmpty()) {
            return null;
        }

        $matched = $bands->last(fn (array $band): bool => (int) $band['nth'] <= $rank) ?? $bands->first();

        return ['percent' => (string) $matched['percent'], 'rank' => $rank];
    }
}

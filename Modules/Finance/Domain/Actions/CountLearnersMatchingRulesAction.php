<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\CountLearnersMatchingRulesData;
use Modules\Finance\Domain\DataObjects\LearnerRuleMatchResult;
use Modules\Finance\Domain\Support\FeeStructureResolver;
use Modules\People\Models\Student;

/**
 * ACT-CountLearnersMatchingRules (Book B FIN-02 §7). Backs
 * `Finance\Fees\StructureBuilder`'s live "matches N learners" count —
 * the spec's own "highest-value UX detail" in this module: building a
 * fee structure blind and discovering the rule was wrong after
 * invoicing 1,400 families is the failure mode this prevents. Read-only
 * and against a candidate rule set that may not be saved as real
 * `FeeStructureRule` rows yet, so it reuses `FeeStructureResolver`'s own
 * per-learner attribute computation and match logic rather than a
 * second copy of either.
 */
final class CountLearnersMatchingRulesAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly FeeStructureResolver $resolver,
    ) {}

    public function execute(CountLearnersMatchingRulesData $data): LearnerRuleMatchResult
    {
        $term = Term::withoutGlobalScopes()->findOrFail($data->termId);
        $on = Carbon::now();

        // Same "currently a real, billable student" status set as
        // `ComputeBillingRunAction::studentsInScope()` — see that
        // method's own comment for why a bare `active` filter is wrong.
        $students = Student::query()
            ->where('school_id', $data->schoolId)
            ->whereIn('status', ['enrolled', 'active', 'suspended'])
            ->get();

        $matching = [];

        foreach ($students as $student) {
            $attributes = $this->resolver->attributesFor($student, $term, $on);

            $allMatch = true;

            foreach ($data->rules as $rule) {
                if (! $this->resolver->rawRuleMatches($rule, $attributes)) {
                    $allMatch = false;

                    break;
                }
            }

            if ($allMatch) {
                $matching[] = $student;
            }
        }

        return new LearnerRuleMatchResult(
            count: count($matching),
            sample: array_map(
                fn (Student $student): array => ['id' => $student->id, 'admission_number' => $student->admission_number, 'name' => $student->fullName()],
                array_slice($matching, 0, 10),
            ),
        );
    }
}

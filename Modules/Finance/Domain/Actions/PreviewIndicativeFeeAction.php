<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\IndicativeFeePreview;
use Modules\Finance\Domain\DataObjects\PreviewIndicativeFeeData;
use Modules\Finance\Domain\Exceptions\UnsupportedBillingBasisException;
use Modules\Finance\Domain\Support\FeeLineCalculator;
use Modules\Finance\Domain\Support\FeeStructureResolver;
use Modules\People\Models\Student;

/**
 * ACT-PreviewIndicativeFee (Book D ACA-02 §7/BR-ACA-02-016). Backs
 * `POST /selections/preview-fee` and `Academic\Selection\Form`'s live
 * fee preview — "the same FIN-02 engine that will later bill them"
 * (§4), reusing `FeeStructureResolver`/`FeeLineCalculator` with the
 * proposed-subject-set overrides those two classes now carry
 * specifically for this. Read-only: nothing is persisted here, so
 * calling it repeatedly as a family experiments with subject choices
 * is free of side effects.
 */
final class PreviewIndicativeFeeAction extends Action
{
    public function __construct(
        private readonly FeeStructureResolver $resolver,
        private readonly FeeLineCalculator $calculator,
    ) {}

    public function execute(PreviewIndicativeFeeData $data): IndicativeFeePreview
    {
        $student = Student::findOrFail($data->studentId);
        $term = Term::findOrFail($data->termId);
        $on = Carbon::now();

        $proposedSubjects = Subject::withoutGlobalScopes()
            ->with('subjectGroup')
            ->whereIn('id', $data->subjectIds)
            ->get()
            ->map(fn (Subject $subject): array => ['name' => $subject->name, 'group_code' => $subject->subjectGroup?->code])
            ->values()
            ->all();

        $result = $this->resolver->resolve($student, $term, $on, subjectCountOverride: count($proposedSubjects));

        if ($result['selected'] === null) {
            return new IndicativeFeePreview(amountMinor: null, currency: null, trace: $result['trace']);
        }

        $structure = $result['selected'];
        $amountMinor = 0;
        $currency = null;

        foreach ($structure->items as $item) {
            if ($item->is_optional) {
                continue;
            }

            try {
                $lineResult = $this->calculator->compute(
                    $item,
                    $student,
                    $term,
                    $on,
                    proposedSubjects: $item->billing_basis === 'per_subject' ? $proposedSubjects : null,
                );
            } catch (UnsupportedBillingBasisException) {
                continue;
            }

            if ($lineResult === null) {
                continue;
            }

            $amountMinor += $lineResult->netMinor;
            $currency ??= $item->currency;
        }

        return new IndicativeFeePreview(amountMinor: $amountMinor, currency: $currency, trace: $result['trace']);
    }
}

<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\IndicativeFeePreview;
use Modules\Finance\Domain\Exceptions\UnsupportedBillingBasisException;
use Modules\Finance\Domain\Support\FeeLineCalculator;
use Modules\Finance\Domain\Support\FeeStructureResolver;
use Modules\People\Domain\DataObjects\BillingAttributeChangePreview;
use Modules\People\Models\Student;

/**
 * ACT-PreviewBillingAttributeChange (Book C PPL-01 §8: "the registrar
 * should see 'this will credit USD 369.23...' before they click
 * confirm"). `ChangeBillingAttributeAction` itself has no preview path
 * — this reuses `FeeStructureResolver`/`FeeLineCalculator`, the exact
 * same engine `PreviewIndicativeFeeAction` (ACA-02/FIN-02) already
 * wraps, but against an **unsaved clone** of the real learner with
 * the proposed attribute applied, so it can compare "now" against
 * "after" without persisting anything or touching
 * `ChangeBillingAttributeAction`'s own append-only history write.
 * Read-only: nothing here is saved.
 */
final class PreviewBillingAttributeChangeAction extends Action
{
    protected bool $transactional = false;

    /**
     * attribute key => students column.
     *
     * @var array<string, string>
     */
    private const array ATTRIBUTE_COLUMNS = [
        'enrolment_type' => 'enrolment_type',
        'residency' => 'residency',
        'pathway' => 'pathway',
        'grade_level' => 'grade_level_id',
        'class' => 'class_id',
        'section' => 'section_id',
    ];

    public function __construct(
        private readonly FeeStructureResolver $resolver,
        private readonly FeeLineCalculator $calculator,
    ) {}

    public function execute(int $studentId, int $termId, string $attribute, string $newValue): BillingAttributeChangePreview
    {
        $column = self::ATTRIBUTE_COLUMNS[$attribute]
            ?? throw new InvalidArgumentException("No fee-impact preview is defined for attribute [{$attribute}].");

        $student = Student::findOrFail($studentId);
        $term = Term::findOrFail($termId);
        $on = Carbon::now();

        $current = $this->indicativeFeeFor($student, $term, $on);

        $hypothetical = $student->replicate();
        $hypothetical->setAttribute($column, str_ends_with($column, '_id') ? (int) $newValue : $newValue);
        $proposed = $this->indicativeFeeFor($hypothetical, $term, $on);

        return new BillingAttributeChangePreview(
            currentAmountMinor: $current->amountMinor,
            currentCurrency: $current->currency,
            proposedAmountMinor: $proposed->amountMinor,
            proposedCurrency: $proposed->currency,
        );
    }

    private function indicativeFeeFor(Student $student, Term $term, Carbon $on): IndicativeFeePreview
    {
        $result = $this->resolver->resolve($student, $term, $on);

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
                $lineResult = $this->calculator->compute($item, $student, $term, $on);
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

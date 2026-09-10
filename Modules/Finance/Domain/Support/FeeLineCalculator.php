<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Academic\Domain\Support\SubjectEnrolmentQuery;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\FeeLineCalculationResult;
use Modules\Finance\Domain\Exceptions\UnsupportedBillingBasisException;
use Modules\Finance\Models\FeeStructureItem;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\People\Models\Student;

/**
 * Book B FIN-02 §3/§4 ⭐. Computes one structure item's gross for one
 * learner. Branches only on `billing_basis` — never on enrolment type
 * — which is the entire mechanism behind "full-time versus part-time
 * is not a branch in the code" (§4).
 *
 * `PER_UNIT`, `USAGE_BASED`, and standalone `TIERED` are not
 * implemented — see `UnsupportedBillingBasisException`.
 *
 * `$proposedSubjects` (Book D ACA-02 §7/BR-ACA-02-016) lets
 * `PreviewIndicativeFeeAction` price a *proposed* subject set that has
 * no `learner_subject_enrolments` rows yet — a plain array of
 * `{name, group_code}` shaped exactly like the live query's rows are
 * normalised to inside `perSubject()`. A plain array rather than a
 * `Collection` here sidesteps `Collection`'s non-covariant template
 * entirely; nothing beyond `count()`/iteration is needed. Omitted, the
 * live enrolment is read as usual.
 */
final class FeeLineCalculator
{
    public function __construct(
        private readonly SubjectEnrolmentQuery $subjectEnrolments,
    ) {}

    /**
     * @param  ?array<int, array{name: string, group_code: ?string}>  $proposedSubjects
     * @return ?FeeLineCalculationResult null means no line should be raised
     */
    public function compute(FeeStructureItem $item, Student $student, Term $term, CarbonInterface $on, string $prorationFactor = '1', ?array $proposedSubjects = null): ?FeeLineCalculationResult
    {
        $computed = match ($item->billing_basis) {
            'flat_per_term' => $this->flatPerTerm($item),
            'per_subject' => $this->perSubject($item, $student, $term, $on, $proposedSubjects),
            'per_month' => $this->perMonth($item, $term),
            'per_day' => $this->perDay($item, $term),
            'one_off' => $this->oneOff($item, $student),
            'per_unit', 'usage_based', 'tiered' => throw UnsupportedBillingBasisException::forBasis($item->billing_basis),
            default => throw UnsupportedBillingBasisException::forBasis($item->billing_basis),
        };

        if ($computed === null) {
            return null;
        }

        [$quantity, $unitRate, $grossMinor, $note, $sourceReference] = $computed;

        $grossMinor = $this->applyMinMax($item, $grossMinor);

        $finalGross = $grossMinor;

        if ($item->is_prorated && $prorationFactor !== '1') {
            $finalGross = (int) round($grossMinor * (float) $prorationFactor);
            $note .= sprintf(' Pro-rated at factor %s.', $prorationFactor);
        }

        return new FeeLineCalculationResult(
            quantity: $quantity,
            unitRateMinor: $unitRate,
            grossMinor: $finalGross,
            prorationFactor: $prorationFactor,
            netMinor: $finalGross,
            calculationNote: $note,
            sourceReference: $sourceReference,
        );
    }

    /**
     * @return array{0: string, 1: ?int, 2: int, 3: string, 4: ?string}
     */
    private function flatPerTerm(FeeStructureItem $item): array
    {
        $amount = $item->amount_minor ?? 0;

        return ['1', null, $amount, 'Flat per-term rate.', null];
    }

    /**
     * @param  ?array<int, array{name: string, group_code: ?string}>  $proposedSubjects
     * @return array{0: string, 1: ?int, 2: int, 3: string, 4: ?string}
     */
    private function perSubject(FeeStructureItem $item, Student $student, Term $term, CarbonInterface $on, ?array $proposedSubjects = null): array
    {
        $subjects = $proposedSubjects ?? $this->subjectEnrolments->billableSubjectsOn($student, $term, $on)
            ->map(fn ($enrolment): array => ['name' => $enrolment->subject->name, 'group_code' => $enrolment->subjectGroup?->code])
            ->values()
            ->all();

        $quantity = count($subjects);

        $rateMap = $item->subject_rate_map ?? [];
        $names = [];
        $raw = 0;

        foreach ($subjects as $subject) {
            $groupCode = $subject['group_code'];
            $rate = ($groupCode !== null ? ($rateMap["group:{$groupCode}"] ?? null) : null) ?? $item->unit_rate_minor ?? 0;
            $raw += $rate;
            $names[] = sprintf('%s(%s)', $subject['name'], $groupCode ?? 'ungrouped');
        }

        $multiplier = $this->tierMultiplier($item, $quantity);
        $gross = (int) round($raw * $multiplier);

        $note = sprintf(
            '%d subject(s): %s; tier multiplier %.2f (quantity %d)',
            $quantity,
            implode(', ', $names),
            $multiplier,
            $quantity,
        );

        return [(string) $quantity, $item->unit_rate_minor, $gross, $note, implode(',', $names)];
    }

    /**
     * @return array{0: string, 1: ?int, 2: int, 3: string, 4: ?string}
     */
    private function perMonth(FeeStructureItem $item, Term $term): array
    {
        $months = max(1, (int) $term->starts_on->diffInMonths($term->ends_on) + 1);
        $rate = $item->unit_rate_minor ?? 0;

        return [(string) $months, $rate, $months * $rate, "{$months} month(s) in term × rate.", null];
    }

    /**
     * @return array{0: string, 1: ?int, 2: int, 3: string, 4: ?string}
     */
    private function perDay(FeeStructureItem $item, Term $term): array
    {
        $days = $term->teaching_days ?? max(1, (int) $term->starts_on->diffInDays($term->ends_on) + 1);
        $rate = $item->unit_rate_minor ?? 0;

        return [(string) $days, $rate, $days * $rate, "{$days} teaching day(s) × rate.", null];
    }

    /**
     * @return ?array{0: string, 1: ?int, 2: int, 3: string, 4: ?string}
     */
    private function oneOff(FeeStructureItem $item, Student $student): ?array
    {
        $alreadyCharged = LearnerFeeLine::query()
            ->where('component_id', $item->component_id)
            ->where('billing_basis', 'one_off')
            ->whereHas('assignment', fn ($q) => $q->where('student_id', $student->id))
            ->exists();

        if ($alreadyCharged) {
            return null;
        }

        $amount = $item->amount_minor ?? $item->unit_rate_minor ?? 0;

        return ['1', null, $amount, 'First charge; no prior one-off charge found for this component.', null];
    }

    private function tierMultiplier(FeeStructureItem $item, int $quantity): float
    {
        foreach ($item->tier_bands ?? [] as $band) {
            $from = $band['from'];
            $to = $band['to'] ?? null;

            if ($quantity >= $from && ($to === null || $quantity <= $to)) {
                return (float) ($band['multiplier'] ?? 1.0);
            }
        }

        return 1.0;
    }

    private function applyMinMax(FeeStructureItem $item, int $grossMinor): int
    {
        if ($item->minimum_minor !== null && $grossMinor < $item->minimum_minor) {
            $grossMinor = $item->minimum_minor;
        }

        if ($item->maximum_minor !== null && $grossMinor > $item->maximum_minor) {
            $grossMinor = $item->maximum_minor;
        }

        return $grossMinor;
    }
}

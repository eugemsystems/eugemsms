<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Academic\Domain\Support\TermProrationCalculator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\DataObjects\ComputeBillingRunData;
use Modules\Finance\Domain\Events\BillingRunComputed;
use Modules\Finance\Domain\Events\LearnerFeeAssigned;
use Modules\Finance\Domain\Exceptions\UnsupportedBillingBasisException;
use Modules\Finance\Domain\Support\FeeLineCalculator;
use Modules\Finance\Domain\Support\FeeStructureResolver;
use Modules\Finance\Models\BillingRun;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\People\Models\Student;
use Modules\People\Models\StudentEnrolment;

/**
 * ACT-ComputeBillingRun (Book B FIN-02 §3, steps 1-10 ⭐). Resolves a
 * structure and computes every line for every learner in scope, then
 * stops at `preview` — nothing is invoiced, no journal posted
 * (AC-FIN-02-006). Ad hoc charges are deliberately NOT pulled into the
 * run here (spec step 5): bundling them into an assignment that can be
 * invoiced needs `FIN-03`'s invoice concept, which doesn't exist yet.
 * `finance.one_off_check_scope`'s `academic_year` alternative is
 * registered as a setting but not wired — `FeeLineCalculator`'s
 * one-off history check is always school-wide (the default), matching
 * BR-FIN-02-007's "full charging history across all terms and years".
 */
final class ComputeBillingRunAction extends Action
{
    public function __construct(
        private readonly FeeStructureResolver $resolver,
        private readonly FeeLineCalculator $calculator,
        private readonly TermProrationCalculator $proration,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ComputeBillingRunData $data): BillingRun
    {
        $term = Term::findOrFail($data->termId);
        $on = $data->billingDate ?? Carbon::now();

        $run = BillingRun::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'status' => 'computing',
            'computed_by' => $data->computedByUserId,
        ]);

        return $this->transaction(function () use ($run, $term, $on, $data): BillingRun {
            $students = $this->studentsInScope($data);

            $computedCount = 0;
            $exceptions = [];
            $currencyTotals = [];
            $variance = [];

            foreach ($students as $student) {
                $existing = LearnerFeeAssignment::query()
                    ->where('student_id', $student->id)
                    ->where('term_id', $term->id)
                    ->whereIn('status', ['draft', 'approved', 'invoiced'])
                    ->first();

                if ($existing !== null && $existing->status !== 'draft') {
                    $exceptions[] = ['student_id' => $student->id, 'admission_number' => $student->admission_number, 'reason' => 'existing_non_superseded_assignment'];

                    continue;
                }

                $existing?->update(['status' => 'superseded']);

                $result = $this->resolver->resolve($student, $term, $on);

                if ($result['selected'] === null) {
                    $exceptions[] = ['student_id' => $student->id, 'admission_number' => $student->admission_number, 'reason' => 'no_matching_structure', 'trace' => $result['trace']];

                    continue;
                }

                $structure = $result['selected'];
                $prorationFactor = $this->prorationFactorFor($student, $term);

                $assignment = LearnerFeeAssignment::create([
                    'school_id' => $data->schoolId,
                    'academic_year_id' => $data->academicYearId,
                    'term_id' => $term->id,
                    'student_id' => $student->id,
                    'structure_id' => $structure->id,
                    'structure_version' => $structure->version,
                    'resolution_trace' => $result['trace'],
                    'computed_at' => Carbon::now(),
                    'computed_by' => $data->computedByUserId,
                    'status' => 'draft',
                    'billing_run_id' => $run->id,
                ]);

                $netTotal = 0;

                foreach ($structure->items as $item) {
                    // BR-FIN-02-022: an optional item only charges against
                    // an explicit opt-in record, which no table stores yet
                    // — skipping every optional item is the conservative
                    // reading until that opt-in mechanism exists.
                    if ($item->is_optional) {
                        continue;
                    }

                    try {
                        $lineResult = $this->calculator->compute($item, $student, $term, $on, $prorationFactor);
                    } catch (UnsupportedBillingBasisException) {
                        continue;
                    }

                    if ($lineResult === null) {
                        continue;
                    }

                    LearnerFeeLine::create([
                        'school_id' => $data->schoolId,
                        'assignment_id' => $assignment->id,
                        'component_id' => $item->component_id,
                        'structure_item_id' => $item->id,
                        'billing_basis' => $item->billing_basis,
                        'quantity' => $lineResult->quantity,
                        'unit_rate_minor' => $lineResult->unitRateMinor,
                        'gross_minor' => $lineResult->grossMinor,
                        'proration_factor' => $lineResult->prorationFactor,
                        'discount_minor' => 0,
                        'net_minor' => $lineResult->netMinor,
                        'currency' => $item->currency,
                        'calculation_note' => $lineResult->calculationNote,
                        'source_reference' => $lineResult->sourceReference,
                    ]);

                    $netTotal += $lineResult->netMinor;
                    $currencyTotals[$item->currency] ??= ['gross_minor' => 0, 'discount_minor' => 0, 'net_minor' => 0];
                    $currencyTotals[$item->currency]['gross_minor'] += $lineResult->grossMinor;
                    $currencyTotals[$item->currency]['net_minor'] += $lineResult->netMinor;
                }

                $computedCount++;
                event(new LearnerFeeAssigned($assignment));

                $this->checkExceptions($student, $netTotal, $result['trace'], $term, $exceptions, $variance);
            }

            $variancePercent = (int) $this->settings->get('finance.billing_variance_alert_percent', new ScopeChain(schoolId: $run->school_id));

            $run->update([
                'status' => 'preview',
                'total_learners' => $students->count(),
                'computed_count' => $computedCount,
                'exception_count' => count($exceptions),
                'total_gross_minor' => array_sum(array_column($currencyTotals, 'gross_minor')),
                'total_discount_minor' => 0,
                'total_net_minor' => array_sum(array_column($currencyTotals, 'net_minor')),
                'currency_totals' => $currencyTotals,
                'variance_report' => $variance,
                'exception_report' => ['threshold_percent' => $variancePercent, 'exceptions' => $exceptions],
            ]);

            event(new BillingRunComputed($run));

            return $run;
        });
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsInScope(ComputeBillingRunData $data)
    {
        if ($data->studentIds !== null) {
            return Student::query()->whereIn('id', $data->studentIds)->get();
        }

        return Student::query()->where('school_id', $data->schoolId)->where('status', 'active')->get();
    }

    private function prorationFactorFor(Student $student, Term $term): string
    {
        $enrolment = StudentEnrolment::withoutGlobalScopes()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->orderByDesc('id')
            ->first();

        $joinedOn = $enrolment?->started_on;

        if ($joinedOn === null || $joinedOn->lessThanOrEqualTo($term->starts_on)) {
            return '1';
        }

        ['remaining' => $remaining, 'total' => $total] = $this->proration->remainingAndTotal($term, $joinedOn);

        return $total > 0 ? (string) round($remaining / $total, 6) : '0';
    }

    /**
     * @param  array<string, mixed>  $trace
     * @param  array<int, array<string, mixed>>  $exceptions
     * @param  array<int, array<string, mixed>>  $variance
     */
    private function checkExceptions(Student $student, int $netTotal, array $trace, Term $term, array &$exceptions, array &$variance): void
    {
        if ($netTotal === 0) {
            $exceptions[] = ['student_id' => $student->id, 'admission_number' => $student->admission_number, 'reason' => 'zero_value_assignment'];
        }

        if ($student->enrolment_type === 'PART_TIME' && ($trace['attributes_evaluated']['subject_count'] ?? 0) === 0) {
            $exceptions[] = ['student_id' => $student->id, 'admission_number' => $student->admission_number, 'reason' => 'part_time_zero_subjects'];
        }

        $previous = LearnerFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('term_id', '!=', $term->id)
            ->whereIn('status', ['approved', 'invoiced'])
            ->with('lines')
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return;
        }

        $previousNet = (int) $previous->lines->sum('net_minor');

        if ($previousNet === 0) {
            return;
        }

        $variancePercent = round((($netTotal - $previousNet) / $previousNet) * 100, 2);

        $variance[] = [
            'student_id' => $student->id,
            'admission_number' => $student->admission_number,
            'previous_net_minor' => $previousNet,
            'current_net_minor' => $netTotal,
            'variance_percent' => $variancePercent,
        ];

        $threshold = (int) $this->settings->get('finance.billing_variance_alert_percent', new ScopeChain(schoolId: $student->school_id));

        if (abs($variancePercent) > $threshold) {
            $exceptions[] = [
                'student_id' => $student->id,
                'admission_number' => $student->admission_number,
                'reason' => 'variance_beyond_threshold',
                'variance_percent' => $variancePercent,
            ];
        }
    }
}

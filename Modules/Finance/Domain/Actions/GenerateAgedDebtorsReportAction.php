<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\AgedDebtorRow;
use Modules\Finance\Domain\DataObjects\GenerateAgedDebtorsReportData;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Student;

/**
 * ACT-GenerateAgedDebtorsReport (Book B FIN-03 §4/§5/BR-FIN-03-014).
 * Buckets every still-owing invoice by days past `due_date` as at the
 * given date, per learner, using the school's own configurable
 * `finance.aging_buckets` boundaries (default 30/60/90/120) rather
 * than a hard-coded set.
 */
final class GenerateAgedDebtorsReportAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array<int, AgedDebtorRow>
     */
    public function execute(GenerateAgedDebtorsReportData $data): array
    {
        $boundaries = array_map(
            'intval',
            explode(',', (string) $this->settings->get('finance.aging_buckets', new ScopeChain(schoolId: $data->schoolId))),
        );

        $labels = $this->bucketLabels($boundaries);

        $studentsQuery = Student::query()->where('school_id', $data->schoolId);

        if ($data->sectionId !== null) {
            $studentsQuery->where('section_id', $data->sectionId);
        }

        if ($data->gradeLevelId !== null) {
            $studentsQuery->where('grade_level_id', $data->gradeLevelId);
        }

        if ($data->residency !== null) {
            $studentsQuery->where('residency', $data->residency);
        }

        $studentIds = $studentsQuery->pluck('id');

        $invoices = Invoice::query()
            ->whereIn('student_id', $studentIds)
            ->where('currency', $data->currency)
            ->where('balance_minor', '>', 0)
            ->with('student')
            ->get();

        $rows = [];

        foreach ($invoices->groupBy('student_id') as $studentInvoices) {
            $student = $studentInvoices->first()->student;
            $bucketMinor = array_fill_keys($labels, 0);

            foreach ($studentInvoices as $invoice) {
                $daysPastDue = (int) $invoice->due_date->diffInDays($data->asAt, absolute: false);
                $label = $this->bucketFor($daysPastDue, $boundaries, $labels);
                $bucketMinor[$label] += $invoice->balance_minor;
            }

            $rows[] = new AgedDebtorRow(
                studentId: $student->id,
                admissionNumber: $student->admission_number,
                studentName: $student->fullName(),
                bucketMinor: $bucketMinor,
                totalMinor: array_sum($bucketMinor),
            );
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $boundaries
     * @return array<int, string>
     */
    private function bucketLabels(array $boundaries): array
    {
        $labels = ['current'];

        foreach ($boundaries as $index => $boundary) {
            $previous = $boundaries[$index - 1] ?? 0;
            $labels[] = ($previous + 1)."-{$boundary}";
        }

        $labels[] = $boundaries[count($boundaries) - 1].'+';

        return $labels;
    }

    /**
     * @param  array<int, int>  $boundaries
     * @param  array<int, string>  $labels
     */
    private function bucketFor(int $daysPastDue, array $boundaries, array $labels): string
    {
        if ($daysPastDue <= 0) {
            return $labels[0];
        }

        foreach ($boundaries as $index => $boundary) {
            if ($daysPastDue <= $boundary) {
                return $labels[$index + 1];
            }
        }

        return $labels[count($labels) - 1];
    }
}

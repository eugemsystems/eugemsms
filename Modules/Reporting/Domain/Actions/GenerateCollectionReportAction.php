<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\GradeLevel;
use Modules\Finance\Models\Invoice;
use Modules\People\Models\Student;
use Modules\Reporting\Domain\DataObjects\GenerateManagementReportData;

/**
 * ACT-GenerateCollectionReport (Book H3 FIN-12 §3). Fees billed against fees collected, by grade
 * level, for invoices issued in the period: the net amount billed, what has been paid, what is still
 * owed and the collection rate. Voided invoices are excluded. Amounts are the invoices' own stored
 * figures (the ledger is where they were posted), in one currency per run.
 */
final class GenerateCollectionReportAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{rows: list<array{grade_level: string, learners: int, billed_minor: int, paid_minor: int, outstanding_minor: int, rate_percent: float|null}>, total_billed_minor: int, total_paid_minor: int, total_outstanding_minor: int, rate_percent: float|null}
     */
    public function execute(GenerateManagementReportData $data): array
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('currency', $data->currency)
            ->where('status', '!=', 'voided')
            ->whereDate('issue_date', '>=', $data->periodStart->toDateString())
            ->whereDate('issue_date', '<=', $data->periodEnd->toDateString())
            ->get(['student_id', 'net_minor', 'paid_minor', 'balance_minor']);

        $students = Student::withoutGlobalScopes()->whereIn('id', $invoices->pluck('student_id'))->get(['id', 'grade_level_id'])->keyBy('id');
        $levels = GradeLevel::withoutGlobalScopes()->where('school_id', $data->schoolId)->get()->keyBy('id');
        $groups = [];

        foreach ($invoices as $invoice) {
            $student = $students->get($invoice->student_id);
            $levelId = $student !== null ? (int) $student->grade_level_id : 0;
            $groups[$levelId] ??= ['learners' => [], 'billed' => 0, 'paid' => 0, 'owed' => 0];
            $groups[$levelId]['learners'][$invoice->student_id] = true;
            $groups[$levelId]['billed'] += $invoice->net_minor;
            $groups[$levelId]['paid'] += $invoice->paid_minor;
            $groups[$levelId]['owed'] += $invoice->balance_minor;
        }

        uksort($groups, function (int $a, int $b) use ($levels): int {
            $levelA = $levels->get($a);
            $levelB = $levels->get($b);

            return ($levelA !== null ? $levelA->ordinal : 999) <=> ($levelB !== null ? $levelB->ordinal : 999);
        });

        $rows = [];

        foreach ($groups as $levelId => $sums) {
            $level = $levels->get($levelId);
            $rows[] = [
                'grade_level' => $level !== null ? $level->name : 'Unassigned',
                'learners' => count($sums['learners']),
                'billed_minor' => $sums['billed'],
                'paid_minor' => $sums['paid'],
                'outstanding_minor' => $sums['owed'],
                'rate_percent' => $sums['billed'] > 0 ? round($sums['paid'] / $sums['billed'] * 100, 1) : null,
            ];
        }

        $billed = array_sum(array_column($rows, 'billed_minor'));
        $paid = array_sum(array_column($rows, 'paid_minor'));

        return [
            'rows' => $rows,
            'total_billed_minor' => $billed,
            'total_paid_minor' => $paid,
            'total_outstanding_minor' => array_sum(array_column($rows, 'outstanding_minor')),
            'rate_percent' => $billed > 0 ? round($paid / $billed * 100, 1) : null,
        ];
    }
}

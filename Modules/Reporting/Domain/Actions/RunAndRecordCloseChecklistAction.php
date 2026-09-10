<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Sessions\RunPeriodCloseChecklistAction;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Reporting\Domain\DataObjects\RunAndRecordCloseChecklistData;
use Modules\Reporting\Domain\Events\CloseCheckFailed;
use Modules\Reporting\Domain\Events\CloseChecklistRun;
use Modules\Reporting\Models\PeriodCloseChecklist;

/**
 * ACT-RunAndRecordCloseChecklist (Book H3 FIN-12 §4 ⭐/BR-FIN-12-009/
 * 010). The durable RECORD of a checklist run — the actual check
 * execution is `Modules\Core`'s own real-time
 * `RunPeriodCloseChecklistAction` (Book A CORE-03), called here
 * unchanged and simply persisted. `overall_status` is
 * `passed_with_acknowledgements` only once every failing warning has
 * a real acknowledgement on file for THIS run; a blocking failure
 * always yields `failed`, full stop — see `AcknowledgeCloseCheckAction`
 * for why no acknowledgement can ever change that.
 */
final class RunAndRecordCloseChecklistAction extends Action
{
    public function __construct(
        private readonly RunPeriodCloseChecklistAction $runChecklist,
    ) {}

    public function execute(RunAndRecordCloseChecklistData $data): PeriodCloseChecklist
    {
        $term = Term::withoutGlobalScopes()->findOrFail($data->termId);
        $type = PeriodType::from($data->periodType);

        $result = $this->runChecklist->execute($term, $type);

        $blockingFailures = 0;
        $warnings = 0;

        foreach ($result->items as $item) {
            if ($item->passed) {
                continue;
            }

            if ($item->blocking) {
                $blockingFailures++;
            } else {
                $warnings++;
            }
        }

        // 'passed_with_acknowledgements' is reachable only once every
        // warning has a real, written acknowledgement on file — never
        // at run time, before anyone has acknowledged anything.
        // BR-FIN-12-011's own LOCK gate (`TransitionPeriodStateAction`,
        // Book A CORE-03) cares only about blocking failures, so an
        // unacknowledged warning never blocks a lock either way.
        $overallStatus = $blockingFailures > 0 ? 'failed' : 'passed';

        $checklist = $this->transaction(fn (): PeriodCloseChecklist => PeriodCloseChecklist::create([
            'school_id' => $term->school_id,
            'term_id' => $term->id,
            'period_type' => $data->periodType,
            'run_at' => Carbon::now(),
            'run_by' => $data->runByUserId,
            'overall_status' => $overallStatus,
            'blocking_failures' => $blockingFailures,
            'warnings' => $warnings,
            'results' => array_map(fn (ChecklistItemResult $item): array => [
                'code' => $item->code,
                'label' => $item->label,
                'passed' => $item->passed,
                'blocking' => $item->blocking,
                'message' => $item->message,
                'details' => $item->details,
            ], $result->items),
        ]));

        foreach ($result->failures() as $failure) {
            event(new CloseCheckFailed($checklist, $failure));
        }

        event(new CloseChecklistRun($checklist));

        return $checklist;
    }
}

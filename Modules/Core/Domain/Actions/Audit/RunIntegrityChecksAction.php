<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Audit;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Audit\RunIntegrityChecksData;
use Modules\Core\Domain\Events\Audit\IntegrityCheckCompleted;
use Modules\Core\Domain\Registry\IntegrityCheckRegistry;
use Modules\Core\Models\IntegrityCheckRun;

/**
 * ACT-RunIntegrityChecks (Book A CORE-08 §4/BR-CORE-08-008). Runs every
 * registered, available check (or the subset named in `checkTypes`),
 * recording one `integrity_check_runs` row per check. A failed
 * `audit_chain` check additionally raises a critical security event —
 * BR-CORE-08-008's "alerts the vendor immediately", not just a row in
 * a table nobody is looking at yet.
 */
final class RunIntegrityChecksAction extends Action
{
    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @return array<int, IntegrityCheckRun>
     */
    public function execute(RunIntegrityChecksData $data): array
    {
        $runs = [];

        foreach (IntegrityCheckRegistry::all() as $checkType => $check) {
            if ($data->checkTypes !== null && ! in_array($checkType, $data->checkTypes, true)) {
                continue;
            }

            if (! $check->isAvailable()) {
                continue;
            }

            $startedAt = microtime(true);
            $result = $check->run($data->schoolId);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $run = $this->transaction(fn (): IntegrityCheckRun => IntegrityCheckRun::create([
                'school_id' => $data->schoolId,
                'check_type' => $checkType,
                'status' => $result->status,
                'records_checked' => $result->recordsChecked,
                'failures_found' => $result->failuresFound,
                'failure_details' => $result->failureDetails,
                'duration_ms' => $durationMs,
                'ran_at' => Carbon::now(),
            ]));

            event(new IntegrityCheckCompleted($run));

            if ($checkType === 'audit_chain' && ! $result->passed()) {
                $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                    eventType: 'financial_audit_chain_broken',
                    severity: 'critical',
                    description: 'The financial audit hash chain failed verification — possible tampering.',
                    schoolId: $data->schoolId,
                    context: ['failures' => $result->failureDetails],
                ));
            }

            $runs[] = $run;
        }

        return $runs;
    }
}

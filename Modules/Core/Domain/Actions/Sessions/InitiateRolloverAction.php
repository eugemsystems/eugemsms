<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\InitiateRolloverData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Registry\RolloverHandlerRegistry;
use Modules\Core\Domain\Support\RolloverStatus;
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\Term;

/**
 * ACT-InitiateRollover (Book A CORE-03 §4). BR-CORE-03-018: validation
 * runs completely, for every handler, before execution begins — this
 * action does exactly that and nothing else. BR-CORE-03-022: only one
 * roll-over may be `validating`/`running` per school at a time.
 */
final class InitiateRolloverAction extends Action
{
    public function execute(InitiateRolloverData $data): PeriodRollover
    {
        $fromTerm = Term::withoutGlobalScopes()->findOrFail($data->fromTermId);
        $toTerm = Term::withoutGlobalScopes()->findOrFail($data->toTermId);

        $alreadyRunning = PeriodRollover::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->whereIn('status', [RolloverStatus::Validating, RolloverStatus::Running])
            ->exists();

        if ($alreadyRunning) {
            throw new InvalidStateTransitionException(
                'A roll-over is already in progress for this school.',
                ['school_id' => $data->schoolId],
            );
        }

        return $this->transaction(function () use ($data, $fromTerm, $toTerm): PeriodRollover {
            $rollover = PeriodRollover::create([
                'school_id' => $data->schoolId,
                'from_term_id' => $fromTerm->id,
                'to_term_id' => $toTerm->id,
                'status' => RolloverStatus::Validating,
                'initiated_by' => $data->initiatedByUserId,
            ]);

            $report = [];
            $allPassed = true;

            foreach (RolloverHandlerRegistry::available() as $handler) {
                $result = $handler->validate($fromTerm, $toTerm);

                $report[] = [
                    'handler' => $handler::class,
                    'module' => $handler->moduleCode(),
                    'blocking' => $handler->isBlocking(),
                    'passed' => $result->passed,
                    'errors' => $result->errors,
                ];

                if (! $result->passed && $handler->isBlocking()) {
                    $allPassed = false;
                }
            }

            $rollover->validation_report = $report;
            $rollover->status = $allPassed ? RolloverStatus::Pending : RolloverStatus::Failed;
            $rollover->save();

            return $rollover;
        });
    }
}

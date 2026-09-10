<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Boarding\Domain\Actions\OpenMissingLearnerIncidentAction;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;
use Modules\Security\Domain\DataObjects\MusterRosterEntry;
use Modules\Security\Models\EmergencyDrill;
use Modules\Security\Models\MusterMark;

/**
 * ACT-CompleteMuster (Book H2 OPS-06 §3 ⭐⭐/BR-OPS-06-010/011/
 * AC-OPS-06-003). Whoever the live roster says should be on site but
 * has no `MusterMark` is unaccounted — for each one who is a learner,
 * this opens a real `BRD-02` `MissingLearnerIncident` through the
 * same `OpenMissingLearnerIncidentAction` an overdue exeat already
 * uses, no roll call, default escalation profile. Non-learners
 * (staff, visitors, contractors) still count toward
 * `unaccounted_count` but have no `BRD-02` incident to open — that
 * module tracks learners only.
 */
final class CompleteMusterAction extends Action
{
    public function __construct(
        private readonly AssembleMusterRollAction $assembleMusterRoll,
        private readonly OpenMissingLearnerIncidentAction $openMissingLearnerIncident,
    ) {}

    public function execute(int $drillId, int $termId, ?int $evacuationSeconds = null): EmergencyDrill
    {
        $drill = EmergencyDrill::findOrFail($drillId);
        $roster = $this->assembleMusterRoll->execute($drill->school_id);

        $markedKeys = MusterMark::where('drill_id', $drill->id)
            ->get()
            ->map(fn (MusterMark $mark): string => "{$mark->person_type}:{$mark->person_id}")
            ->flip();

        $unaccounted = $roster->filter(fn (MusterRosterEntry $entry): bool => ! $markedKeys->has("{$entry->personType}:{$entry->personId}"));

        return $this->transaction(function () use ($drill, $roster, $unaccounted, $termId, $evacuationSeconds): EmergencyDrill {
            foreach ($unaccounted as $entry) {
                if ($entry->personType !== 'student') {
                    continue;
                }

                $student = Student::find($entry->personId);

                if ($student !== null) {
                    $this->openMissingLearnerIncident->executeForOverdueExeat($drill->school_id, $termId, $student);
                }
            }

            $drill->update([
                'mustered_headcount' => $roster->count() - $unaccounted->count(),
                'unaccounted_count' => $unaccounted->count(),
                'evacuation_seconds' => $evacuationSeconds ?? $drill->evacuation_seconds,
            ]);

            return $drill;
        });
    }
}

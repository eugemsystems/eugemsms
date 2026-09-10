<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Events\ExeatOverdue;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;

/**
 * ACT-CheckOverdueExeat (Book F BRD-03 §4/BR-BRD-03-015/AC-BRD-03-006).
 * Notifies immediately at `returns_by`, then — once elapsed minutes
 * pass `boarding.overdue_opens_missing_incident_after_minutes` (default
 * 180) — opens a `BRD-02` missing-learner incident with no roll call
 * attached. Meant to run on a short schedule against every
 * `departed` exeat past its `returns_by`; wiring that schedule entry
 * is a deployment step, mirroring `AdvanceEscalationLadderAction`'s
 * own note.
 */
final class CheckOverdueExeatAction extends Action
{
    public function __construct(
        private readonly OpenMissingLearnerIncidentAction $openIncident,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $exeatId): Exeat
    {
        $exeat = Exeat::findOrFail($exeatId);

        if ($exeat->status !== 'departed' && $exeat->status !== 'overdue') {
            return $exeat;
        }

        $now = Carbon::now();

        if ($now->lessThan($exeat->returns_by)) {
            return $exeat;
        }

        if ($exeat->status === 'departed') {
            $exeat = $this->transaction(fn (): Exeat => tap($exeat)->update(['status' => 'overdue', 'overdue_notified_at' => $now]));

            event(new ExeatOverdue($exeat));
        }

        $minutesOverdue = (int) $exeat->returns_by->diffInMinutes($now);
        $threshold = (int) $this->settings->get('boarding.overdue_opens_missing_incident_after_minutes', new ScopeChain(schoolId: $exeat->school_id));

        if ($minutesOverdue >= $threshold) {
            $student = Student::findOrFail($exeat->student_id);
            $this->openIncident->executeForOverdueExeat($exeat->school_id, $exeat->term_id, $student);
        }

        return $exeat;
    }
}

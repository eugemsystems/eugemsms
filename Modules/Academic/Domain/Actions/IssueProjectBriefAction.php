<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\IssueProjectBriefData;
use Modules\Academic\Domain\Events\ProjectBriefIssued;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\ProjectBrief;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-IssueProjectBrief (Book E ACA-06 §5/BR-ACA-06-004). Creates a
 * `learner_project` for every learner with an active enrolment in
 * the brief's subject and grade level, at this moment — a learner
 * enrolling afterwards gets theirs from
 * `AutoCreateProjectOnLateEnrolmentListener` (BR-ACA-06-005) instead.
 */
final class IssueProjectBriefAction extends Action
{
    public function execute(IssueProjectBriefData $data): ProjectBrief
    {
        $brief = ProjectBrief::findOrFail($data->briefId);

        if ($brief->status !== 'approved') {
            throw new InvalidStateTransitionException(
                "A project brief must be approved before it can be issued (currently {$brief->status}).",
                ['brief_id' => $brief->id, 'status' => $brief->status],
            );
        }

        $enrolments = LearnerSubjectEnrolment::query()
            ->where('school_id', $brief->school_id)
            ->where('subject_id', $brief->subject_id)
            ->where('status', 'active')
            ->whereHas('student', fn ($q) => $q->where('grade_level_id', $brief->grade_level_id))
            ->get();

        return $this->transaction(function () use ($brief, $enrolments): ProjectBrief {
            $brief->update([
                'status' => 'issued',
                'issued_at' => Carbon::now(),
            ]);

            foreach ($enrolments as $enrolment) {
                LearnerProject::firstOrCreate(
                    [
                        'school_id' => $brief->school_id,
                        'brief_id' => $brief->id,
                        'student_id' => $enrolment->student_id,
                    ],
                    [
                        'academic_year_id' => $brief->academic_year_id,
                        'subject_id' => $brief->subject_id,
                        'status' => 'assigned',
                        'version' => 1,
                    ],
                );
            }

            event(new ProjectBriefIssued($brief));

            return $brief;
        });
    }
}

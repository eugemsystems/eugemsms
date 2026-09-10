<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateProjectBriefData;
use Modules\Academic\Domain\Exceptions\DuplicateProjectBriefException;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectMilestone;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateProjectBrief (Book E ACA-06 §2/§5/§6/BR-ACA-06-002/003/008).
 * Drafts a brief — `ApproveProjectBriefAction` and `IssueProjectBriefAction`
 * are separate steps; a teacher cannot set a project unilaterally.
 */
final class CreateProjectBriefAction extends Action
{
    public function execute(CreateProjectBriefData $data): ProjectBrief
    {
        $subject = Subject::findOrFail($data->subjectId);

        if (! $subject->requires_sbp) {
            throw new InvalidArgumentException(
                "Subject #{$subject->id} does not require SBP/continuous assessment (BR-ACA-06-003).",
            );
        }

        $instrument = AssessmentInstrument::findOrFail($data->instrumentId);

        $existing = ProjectBrief::query()
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('subject_id', $data->subjectId)
            ->where('grade_level_id', $data->gradeLevelId)
            ->where('instrument_id', $data->instrumentId)
            ->whereNotIn('status', ['archived'])
            ->count();

        if ($existing >= $instrument->projects_per_subject_per_year && ! $data->overrideProjectLimit) {
            throw DuplicateProjectBriefException::forSubjectAndLevel($data->subjectId, $data->gradeLevelId, $data->academicYearId);
        }

        if ($data->overrideProjectLimit && ($data->overrideReason === null || mb_strlen($data->overrideReason) < 10)) {
            throw new InvalidArgumentException('Overriding the projects-per-subject-per-year limit requires a recorded reason of at least 10 characters.');
        }

        return $this->transaction(function () use ($data): ProjectBrief {
            $brief = ProjectBrief::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'instrument_id' => $data->instrumentId,
                'subject_id' => $data->subjectId,
                'grade_level_id' => $data->gradeLevelId,
                'title' => $data->title,
                'description' => $data->description,
                'learning_objectives' => $data->learningObjectives,
                'heritage_link' => $data->heritageLink,
                'deliverables' => $data->deliverables,
                'resources' => $data->resources,
                'starts_on' => $data->startsOn,
                'due_on' => $data->dueOn,
                'max_mark' => $data->maxMark,
                'rubric_id' => $data->rubricId,
                'brief_document_id' => $data->briefDocumentId,
                'status' => 'draft',
                'created_by' => $data->createdBy,
            ]);

            foreach ($data->milestones as $milestone) {
                ProjectMilestone::create([
                    'school_id' => $data->schoolId,
                    'brief_id' => $brief->id,
                    'sequence' => $milestone->sequence,
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    'due_on' => $milestone->dueOn,
                    'weight_percent' => $milestone->weightPercent,
                    'requires_evidence' => $milestone->requiresEvidence,
                ]);
            }

            return $brief;
        });
    }
}

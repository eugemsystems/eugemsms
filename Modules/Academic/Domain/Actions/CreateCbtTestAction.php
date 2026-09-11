<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Domain\DataObjects\CreateCbtTestData;
use Modules\Academic\Domain\Support\RuleBasedQuestionAssembler;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;

/**
 * ACT-CreateCbtTest (Book K ACA-09 §2/§4/BR-ACA-09-005/010 ⭐). The
 * concrete question set is resolved ONCE here, for both assembly
 * methods — see the `cbt_tests` migration's docblock for why
 * `question_ids` is always populated, not just for `manual`. When
 * `assessmentTypeId` is given, the underlying `Assessment` is
 * provisioned immediately too, mirroring `ACA-08`'s
 * `CreateAssignmentAction`.
 */
final class CreateCbtTestAction extends Action
{
    public function __construct(
        private readonly RuleBasedQuestionAssembler $assembler,
        private readonly CreateAssessmentAction $createAssessment,
    ) {}

    public function execute(CreateCbtTestData $data): CbtTest
    {
        if (! in_array($data->assemblyMethod, ['manual', 'rule_based'], true)) {
            throw new InvalidArgumentException("Unknown assembly method [{$data->assemblyMethod}].");
        }

        $questionIds = $data->assemblyMethod === 'manual'
            ? $this->validatedManualQuestionIds($data)
            : $this->assembler->assemble($data->schoolId, $data->subjectId, $data->assemblyRules ?? []);

        $maxMarkTotal = (float) QuestionBankItem::query()->whereIn('id', $questionIds)->sum('max_mark');

        return $this->transaction(function () use ($data, $questionIds, $maxMarkTotal): CbtTest {
            $assessmentId = null;

            if ($data->assessmentTypeId !== null) {
                $assessmentType = AssessmentType::findOrFail($data->assessmentTypeId);
                $term = Term::findOrFail($data->termId);

                $assessment = $this->createAssessment->execute(new CreateAssessmentData(
                    schoolId: $data->schoolId,
                    academicYearId: $term->academic_year_id,
                    termId: $term->id,
                    assessmentTypeId: $assessmentType->id,
                    subjectId: $data->subjectId,
                    title: $data->title,
                    maxMark: $maxMarkTotal,
                    weightPercent: (float) $assessmentType->default_weight_percent,
                    createdByUserId: $data->createdByUserId,
                ));

                $assessmentId = $assessment->id;
            }

            QuestionBankItem::query()->whereIn('id', $questionIds)->increment('usage_count');

            return CbtTest::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'title' => $data->title,
                'subject_id' => $data->subjectId,
                'assessment_type_id' => $data->assessmentTypeId,
                'assessment_id' => $assessmentId,
                'assembly_method' => $data->assemblyMethod,
                'assembly_rules' => $data->assemblyRules,
                'question_ids' => $questionIds,
                'randomise_question_order' => $data->randomiseQuestionOrder,
                'randomise_option_order' => $data->randomiseOptionOrder,
                'duration_minutes' => $data->durationMinutes,
                'opens_at' => $data->opensAt,
                'closes_at' => $data->closesAt,
                'browser_focus_monitoring' => $data->browserFocusMonitoring,
                'max_tab_switches' => $data->maxTabSwitches,
                'status' => 'draft',
            ]);
        });
    }

    /**
     * @return array<int, int>
     */
    private function validatedManualQuestionIds(CreateCbtTestData $data): array
    {
        if ($data->questionIds === null || $data->questionIds === []) {
            throw new InvalidArgumentException('A manually assembled test requires at least one question.');
        }

        return $data->questionIds;
    }
}

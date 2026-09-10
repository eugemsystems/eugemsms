<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ProcessExaminationResultsData;
use Modules\Academic\Domain\Exceptions\PaperComponentWeightMismatchException;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ProcessExaminationResults (Book E ACA-07 §4/§8 ⭐/BR-ACA-07-004/
 * 016/AC-ACA-07-011). Paper marks aggregate into `ACA-05` as an
 * `examination`-category `Assessment`/`AssessmentMark` pair — never a
 * second, parallel result system (the rule's own wording). Refuses,
 * naming the subject and the shortfall, when a subject/level's paper
 * weights don't total 100% (checked here, not at paper creation,
 * since a partial paper set is normal mid-setup).
 */
final class ProcessExaminationResultsAction extends Action
{
    public function execute(ProcessExaminationResultsData $data): ExaminationSession
    {
        $session = ExaminationSession::findOrFail($data->sessionId);

        if (! in_array($session->status, ['in_progress', 'marking', 'moderation'], true)) {
            throw new InvalidStateTransitionException(
                "Session #{$session->id} must be in progress, marking, or moderation to process results (currently {$session->status}).",
                ['session_id' => $session->id, 'status' => $session->status],
            );
        }

        $papers = ExaminationPaper::query()->where('session_id', $session->id)->get();
        $bySubjectLevel = $papers->groupBy(fn (ExaminationPaper $p): string => "{$p->subject_id}:{$p->grade_level_id}");

        foreach ($bySubjectLevel as $group) {
            $total = round((float) $group->sum(fn (ExaminationPaper $p): float => (float) $p->weight_percent), 2);

            if (abs($total - 100.0) > 0.01) {
                throw PaperComponentWeightMismatchException::forTotal($group->first()->subject_id, $total);
            }
        }

        return $this->transaction(function () use ($session, $papers): ExaminationSession {
            $examType = AssessmentType::query()
                ->where('school_id', $session->school_id)
                ->where('is_examination', true)
                ->first() ?? AssessmentType::create([
                    'school_id' => $session->school_id,
                    'code' => 'EXAM',
                    'name' => 'Examination',
                    'category' => 'examination',
                    'default_weight_percent' => '0.00',
                    'appears_on_report_card' => true,
                    'is_examination' => true,
                ]);

            foreach ($papers as $paper) {
                $assessment = Assessment::updateOrCreate(
                    [
                        'school_id' => $session->school_id,
                        'academic_year_id' => $session->academic_year_id,
                        'term_id' => $session->term_id,
                        'subject_id' => $paper->subject_id,
                        'grade_level_id' => $paper->grade_level_id,
                        'assessment_type_id' => $examType->id,
                        'title' => "{$session->name}: {$paper->paper_name}",
                    ],
                    [
                        'max_mark' => $paper->max_mark,
                        'weight_percent' => $paper->weight_percent,
                        'status' => 'published',
                        'created_by' => $session->created_by,
                        'published_at' => Carbon::now(),
                    ],
                );

                $marks = ExaminationMark::query()
                    ->where('school_id', $session->school_id)
                    ->where('paper_id', $paper->id)
                    ->whereIn('status', ['final', 'moderated'])
                    ->get();

                foreach ($marks as $mark) {
                    $settledMark = $mark->moderated_mark ?? $mark->raw_mark;
                    $percent = $mark->is_absent || $settledMark === null
                        ? null
                        : round(((float) $settledMark / (float) $paper->max_mark) * 100, 2);

                    AssessmentMark::updateOrCreate(
                        ['school_id' => $session->school_id, 'assessment_id' => $assessment->id, 'student_id' => $mark->student_id],
                        [
                            'term_id' => $session->term_id,
                            'raw_mark' => $settledMark,
                            'percent' => $percent,
                            'is_absent' => $mark->is_absent,
                            'version' => 1,
                            'entered_by' => $session->created_by,
                            'entered_at' => Carbon::now(),
                        ],
                    );
                }
            }

            $session->update(['status' => 'results_ready']);

            return $session;
        });
    }
}

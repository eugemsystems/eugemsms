<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * ACT-BuildResultsAnalytics (Book D ACA-05 §6, `academic.result.view`).
 * Read-only roll-ups of computed results for one term: by subject (mean, pass
 * rate, ten-point distribution), by class, by teacher, and each subject's
 * mean across the school's recent terms. Figures are facts about a cohort for
 * a person to read; nothing here ranks a teacher.
 */
final class BuildResultsAnalyticsAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{subjects: array<int, array<string, mixed>>, classes: array<int, array<string, mixed>>, teachers: array<int, array<string, mixed>>, trend: array<int, array<string, mixed>>}
     */
    public function execute(int $termId, ?int $classId = null): array
    {
        $classStudentIds = $classId === null ? null : TermResult::query()->where('term_id', $termId)->where('class_id', $classId)->pluck('student_id');

        $rows = TermSubjectResult::query()
            ->where('term_id', $termId)->whereNotNull('final_percent')
            ->when($classStudentIds !== null, fn ($q) => $q->whereIn('student_id', $classStudentIds))
            ->get();

        $subjectNames = Subject::query()->whereIn('id', $rows->pluck('subject_id'))->pluck('name', 'id');

        $subjects = $rows->groupBy('subject_id')->map(function ($group, $subjectId) use ($subjectNames): array {
            $values = $group->map(fn (TermSubjectResult $r): float => (float) $r->final_percent);
            $distribution = array_fill(0, 10, 0);

            foreach ($values as $value) {
                $distribution[min(9, (int) floor($value / 10))]++;
            }

            return [
                'subject_id' => (int) $subjectId,
                'subject' => (string) ($subjectNames[$subjectId] ?? "#{$subjectId}"),
                'learners' => $values->count(),
                'mean' => round((float) $values->avg(), 1),
                'pass_rate' => round($values->filter(fn (float $v): bool => $v >= 50.0)->count() / max(1, $values->count()) * 100, 1),
                'distribution' => $distribution,
            ];
        })->sortBy('subject')->values()->all();

        $results = TermResult::query()->where('term_id', $termId)->whereNotNull('average_percent')
            ->when($classId !== null, fn ($q) => $q->where('class_id', $classId))->get();
        $classNames = SchoolClass::query()->whereIn('id', $results->pluck('class_id'))->pluck('name', 'id');

        $classes = $results->groupBy('class_id')->map(fn ($group, $id): array => [
            'class_id' => (int) $id,
            'class' => (string) ($classNames[$id] ?? "#{$id}"),
            'learners' => $group->count(),
            'mean' => round((float) $group->avg(fn (TermResult $r): float => (float) $r->average_percent), 1),
        ])->sortBy('class')->values()->all();

        $staffNames = Staff::query()->whereIn('id', $rows->pluck('teacher_staff_id')->filter())->get()->mapWithKeys(fn (Staff $s): array => [$s->id => $s->fullName()]);

        $teachers = $rows->filter(fn (TermSubjectResult $r): bool => $r->teacher_staff_id !== null)->groupBy('teacher_staff_id')->map(fn ($group, $id): array => [
            'staff_id' => (int) $id,
            'teacher' => (string) ($staffNames[$id] ?? "#{$id}"),
            'learners' => $group->count(),
            'mean' => round((float) $group->avg(fn (TermSubjectResult $r): float => (float) $r->final_percent), 1),
        ])->sortBy('teacher')->values()->all();

        $recentTerms = Term::query()->orderByDesc('starts_on')->limit(6)->get()->reverse();
        $trend = [];

        foreach ($recentTerms as $term) {
            $mean = TermSubjectResult::query()->where('term_id', $term->id)->whereNotNull('final_percent')->avg('final_percent');

            if ($mean !== null) {
                $trend[] = ['term_id' => $term->id, 'term' => $term->name, 'mean' => round((float) $mean, 1)];
            }
        }

        return ['subjects' => $subjects, 'classes' => $classes, 'teachers' => $teachers, 'trend' => $trend];
    }
}

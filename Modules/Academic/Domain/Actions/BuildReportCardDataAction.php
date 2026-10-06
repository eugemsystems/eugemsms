<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * ACT-BuildReportCardData (Book D ACA-05 §6, BR-ACA-05-012/013/016). Turns one
 * learner's computed term result into the plain data array a report-card
 * template renders — read only, no side effects, so the same stored figures
 * always give the same document.
 */
final class BuildReportCardDataAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(TermResult $result, int $version = 1): array
    {
        $student = Student::findOrFail($result->student_id);
        $term = Term::findOrFail($result->term_id);
        $year = AcademicYear::findOrFail($result->academic_year_id);
        $class = SchoolClass::findOrFail($result->class_id);
        $school = School::findOrFail($result->school_id);
        $scope = new ScopeChain(schoolId: $result->school_id);

        $subjectResults = TermSubjectResult::query()->where('student_id', $result->student_id)->where('term_id', $result->term_id)->get();
        $names = Subject::query()->whereIn('id', $subjectResults->pluck('subject_id'))->pluck('name', 'id');

        $subjects = $subjectResults
            ->map(fn (TermSubjectResult $r): array => [
                'name' => (string) ($names[$r->subject_id] ?? "#{$r->subject_id}"),
                'final_percent' => $r->final_percent === null ? '' : number_format((float) $r->final_percent, 1),
                'grade' => (string) $r->grade,
                'class_average' => $r->subject_average === null ? '' : number_format((float) $r->subject_average, 1),
                'position' => $r->class_position === null ? '' : "{$r->class_position}/{$r->class_size}",
                'comment' => (string) $r->teacher_comment,
            ])
            ->sortBy('name')->values()->all();

        return [
            'school' => ['name' => $school->name],
            'student' => ['name' => $student->fullName(), 'admission_number' => $student->admission_number],
            'class' => ['name' => $class->name],
            'term' => ['name' => $term->name],
            'year' => ['name' => $year->name],
            'summary' => [
                'average_percent' => $result->average_percent === null ? '' : number_format((float) $result->average_percent, 1),
                'subjects_taken' => $result->subjects_taken,
                'subjects_passed' => $result->subjects_passed,
                'class_position' => (string) $result->class_position,
                'class_size' => (string) $result->class_size,
                'level_position' => (string) $result->level_position,
                'level_size' => (string) $result->level_size,
                'attendance_percent' => $result->attendance_percent === null ? '' : number_format((float) $result->attendance_percent, 1),
                'conduct_grade' => (string) $result->conduct_grade,
                'promotion' => str_replace('_', ' ', (string) $result->promotion_recommendation),
                'class_teacher_comment' => (string) $result->class_teacher_comment,
                'head_comment' => (string) $result->head_comment,
            ],
            'show_positions' => (bool) $this->settings->get('academic.show_positions_on_report', $scope),
            'show_class_average' => (bool) $this->settings->get('academic.show_class_average_on_report', $scope),
            'version' => $version,
            'subjects' => $subjects,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\AcademicYear;
use Modules\People\Domain\DataObjects\CreateAlumniRecordData;
use Modules\People\Domain\Exceptions\AlumniRecordAlreadyExistsException;
use Modules\People\Domain\Support\AcademicSummarySnapshotBuilder;
use Modules\People\Models\AlumniHouseGroup;
use Modules\People\Models\Alumnus;
use Modules\People\Models\Student;

/**
 * ACT-CreateAlumniRecord (Book K PPL-06 §3 ⭐/BR-PPL-06-001/002/003).
 * Fires automatically on graduation (see
 * `CreateAlumniRecordOnGraduationListener`) — this Action itself is
 * also directly callable, but ONLY for a genuinely graduated student;
 * it does not build a path for adding a pre-system-adoption external
 * alumnus (a documented gap, same shape as other Book K modules'
 * "not built this pass" boundaries). `academic_summary_snapshot` is
 * built once, here, by `AcademicSummarySnapshotBuilder`, and never
 * touched again (BR-PPL-06-002/AC-PPL-06-002) — the original PPL-01
 * student record's own read-only-ness on `graduated` status is
 * already enforced by `StudentStatusMachine` (BR-PPL-06-003), not
 * duplicated here.
 */
final class CreateAlumniRecordAction extends Action
{
    public function __construct(
        private readonly AcademicSummarySnapshotBuilder $snapshotBuilder,
    ) {}

    public function execute(CreateAlumniRecordData $data): Alumnus
    {
        $student = Student::findOrFail($data->studentId);

        if (Alumnus::where('student_id', $student->id)->exists()) {
            throw AlumniRecordAlreadyExistsException::forStudent($student->id);
        }

        $currentYear = AcademicYear::query()
            ->where('school_id', $student->school_id)
            ->where('is_current', true)
            ->first();
        $graduationYear = $currentYear?->starts_on->year ?? (int) now()->year;

        return $this->transaction(function () use ($student, $graduationYear): Alumnus {
            AlumniHouseGroup::firstOrCreate(
                ['school_id' => $student->school_id, 'graduation_year' => $graduationYear],
                ['group_name' => "Class of {$graduationYear}"],
            );

            return Alumnus::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'admission_number' => $student->admission_number,
                'graduation_year' => $graduationYear,
                'final_grade_level_id' => $student->grade_level_id,
                'final_house_id' => $student->house_id,
                'academic_summary_snapshot' => $this->snapshotBuilder->build($student->id),
                'is_notable' => false,
                'status' => 'active',
            ]);
        });
    }
}

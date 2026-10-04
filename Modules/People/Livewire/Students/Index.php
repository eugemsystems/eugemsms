<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `People\Students\Index` (Book C PPL-01 §8, `students.view`). Filters
 * on every billing attribute per the spec's own screen description —
 * `enrolment_type`/`residency` are `NOT NULL` on every learner
 * (BR-PPL-01-003), so these filters always have a real value to match.
 */
#[Title('Students')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.view');
    }

    public function render(): View
    {
        $query = Student::where('school_id', $this->school->id)->with(['gradeLevel', 'schoolClass']);

        return view('people::students.index', [
            'students' => $this->paginateDataTable($query, $this->tableColumns()),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'admission_number' => ['label' => __('Admission #'), 'sortable' => true, 'searchable' => true],
            'first_name' => ['label' => __('First name'), 'searchable' => true],
            'last_name' => ['label' => __('Last name'), 'sortable' => true, 'searchable' => true],
            'grade_level_id' => [
                'label' => __('Grade level'), 'sortable' => true, 'filter' => 'select',
                'options' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->pluck('name', 'id')->all(),
            ],
            'enrolment_type' => [
                'label' => __('Enrolment type'), 'sortable' => true, 'filter' => 'select',
                'options' => ['FULL_TIME' => __('Full time'), 'PART_TIME' => __('Part time')],
            ],
            'residency' => [
                'label' => __('Residency'), 'sortable' => true, 'filter' => 'select',
                'options' => ['DAY' => __('Day'), 'BOARDER' => __('Boarder'), 'WEEKLY_BOARDER' => __('Weekly boarder')],
            ],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'applicant' => __('Applicant'), 'enrolled' => __('Enrolled'), 'active' => __('Active'),
                    'suspended' => __('Suspended'), 'transferred' => __('Transferred'), 'graduated' => __('Graduated'),
                    'withdrawn' => __('Withdrawn'), 'deceased' => __('Deceased'), 'archived' => __('Archived'),
                ],
            ],
        ];
    }
}

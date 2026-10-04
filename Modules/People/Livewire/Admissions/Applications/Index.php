<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Applications;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Application;
use Modules\People\Models\Intake;

/**
 * `Admissions\Applications\Index` (Book C PPL-02 §5, `people.admissions.application_view`).
 * Also stands in for the spec's own "Waiting list" screen — filtering
 * this same table to `status = waitlisted` (sorted by
 * `waitlist_position`) is the same data a separate `Admissions\Waitlist\Index`
 * would show, so this pass doesn't duplicate the table.
 */
#[Title('Applications')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.application_view');
    }

    public function render(): View
    {
        $query = Application::where('school_id', $this->school->id)->with(['intake', 'requestedGradeLevel']);

        return view('people::admissions.applications.index', [
            'applications' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'application_number' => ['label' => __('Application #'), 'sortable' => true, 'searchable' => true],
            'first_name' => ['label' => __('First name'), 'searchable' => true],
            'last_name' => ['label' => __('Last name'), 'sortable' => true, 'searchable' => true],
            'intake_id' => [
                'label' => __('Intake'), 'sortable' => true, 'filter' => 'select',
                'options' => Intake::where('school_id', $this->school->id)->pluck('name', 'id')->all(),
            ],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'submitted' => __('Submitted'), 'fee_pending' => __('Fee pending'), 'under_review' => __('Under review'),
                    'exam_scheduled' => __('Exam scheduled'), 'exam_completed' => __('Exam completed'),
                    'interview_scheduled' => __('Interview scheduled'), 'interview_completed' => __('Interview completed'),
                    'offered' => __('Offered'), 'accepted' => __('Accepted'), 'deposit_paid' => __('Deposit paid'),
                    'enrolled' => __('Enrolled'), 'waitlisted' => __('Waitlisted'), 'declined' => __('Declined'),
                    'withdrawn' => __('Withdrawn'), 'expired' => __('Expired'),
                ],
            ],
            'priority_score' => ['label' => __('Priority'), 'sortable' => true],
            'submitted_at' => ['label' => __('Submitted'), 'sortable' => true],
        ];
    }
}

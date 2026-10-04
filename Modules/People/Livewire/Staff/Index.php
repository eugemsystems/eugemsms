<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * `People\Staff\Index` (Book C PPL-04 §5, `people.staff.view`).
 */
#[Title('Staff')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.view');
    }

    public function render(): View
    {
        $query = Staff::where('school_id', $this->school->id)->with('department', 'post');

        return view('people::staff.index', [
            'staff' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'staff_number' => ['label' => __('Staff #'), 'sortable' => true, 'searchable' => true],
            'first_name' => ['label' => __('First name'), 'searchable' => true],
            'last_name' => ['label' => __('Last name'), 'sortable' => true, 'searchable' => true],
            'staff_category' => [
                'label' => __('Category'), 'sortable' => true, 'filter' => 'select',
                'options' => ['teaching' => __('Teaching'), 'administration' => __('Administration'), 'boarding' => __('Boarding'), 'catering' => __('Catering'), 'maintenance' => __('Maintenance'), 'transport' => __('Transport'), 'security' => __('Security'), 'health' => __('Health'), 'farm' => __('Farm'), 'ancillary' => __('Ancillary')],
            ],
            'department_id' => [
                'label' => __('Department'), 'filter' => 'select',
                'options' => Department::where('school_id', $this->school->id)->pluck('name', 'id')->all(),
            ],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['probation' => __('Probation'), 'active' => __('Active'), 'on_leave' => __('On leave'), 'suspended' => __('Suspended'), 'notice' => __('Notice'), 'exited' => __('Exited'), 'archived' => __('Archived')],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Guardians;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;

/**
 * `People\Guardians\Index` (Book C PPL-03 §8, `people.guardians.view`).
 */
#[Title('Guardians')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.view');
    }

    public function render(): View
    {
        $query = Guardian::where('school_id', $this->school->id)->where('status', '!=', 'merged');

        return view('people::guardians.index', [
            'guardians' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'first_name' => ['label' => __('First name'), 'sortable' => true, 'searchable' => true],
            'last_name' => ['label' => __('Last name'), 'sortable' => true, 'searchable' => true],
            'organisation_name' => ['label' => __('Organisation'), 'searchable' => true],
            'guardian_type' => [
                'label' => __('Type'), 'sortable' => true, 'filter' => 'select',
                'options' => ['individual' => __('Individual'), 'organisation' => __('Organisation')],
            ],
            'primary_phone' => ['label' => __('Phone'), 'searchable' => true],
            'email' => ['label' => __('Email'), 'searchable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['active' => __('Active'), 'inactive' => __('Inactive')],
            ],
        ];
    }
}

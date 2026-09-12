<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Templates;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;

/**
 * `Core\Templates\Index` (Book A CORE-06 §6, `core.template.view`).
 * Lists only the currently ACTIVE version of each (template_type,
 * section) — editing supersedes a row rather than mutating it
 * (BR-CORE-06-007), so every prior version stays in the database but
 * out of this list; `Templates\Versions` is where those live.
 */
#[Title('Document templates')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.template.view');
    }

    public function render(): View
    {
        $query = DocumentTemplate::query()->where('school_id', $this->school->id)->where('is_active', true);

        return view('core::templates.index', [
            'templates' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'template_type' => ['label' => __('Type'), 'column' => 'template_type', 'sortable' => true, 'searchable' => true],
            'name' => ['label' => __('Name'), 'column' => 'name', 'sortable' => true, 'searchable' => true],
            'version' => ['label' => __('Version'), 'column' => 'version', 'sortable' => true],
            'is_default' => [
                'label' => __('Default'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Default'), '0' => __('Variant')],
            ],
        ];
    }
}

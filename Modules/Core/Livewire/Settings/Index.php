<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SettingDefinition;

/**
 * `Core\Settings\Index` (Book A CORE-04 §5). Every registered
 * `SettingDefinition`, with the value currently in effect for this
 * school (walked via `SettingResolver`, not read as a raw column — the
 * whole point of BR-CORE-04-002's resolution chain). `core.settings.view`
 * is not yet enforced (CORE-05 gap, same as every other screen so far).
 */
#[Title('Settings')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function render(): View
    {
        $resolver = app(SettingResolver::class);
        $chain = new ScopeChain(schoolId: $this->school->id, tenantId: $this->school->tenant_id);

        $definitions = $this->paginateDataTable(SettingDefinition::query()->orderBy('module_code')->orderBy('sort_order'), $this->tableColumns());

        $resolved = [];

        foreach ($definitions->items() as $definition) {
            $resolved[$definition->key] = $resolver->get($definition->key, $chain);
        }

        return view('core::settings.index', [
            'definitions' => $definitions,
            'resolved' => $resolved,
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'key' => ['label' => __('Key'), 'sortable' => true, 'searchable' => true],
            'label' => ['label' => __('Label'), 'sortable' => true, 'searchable' => true],
            'module_code' => ['label' => __('Module'), 'sortable' => true, 'filter' => 'text'],
            'data_type' => [
                'label' => __('Type'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'string' => 'string', 'int' => 'int', 'float' => 'float', 'bool' => 'bool',
                    'json' => 'json', 'array' => 'array', 'enum' => 'enum', 'money' => 'money',
                    'date' => 'date', 'time' => 'time',
                ],
            ],
            'value' => ['label' => __('Effective value')],
        ];
    }
}

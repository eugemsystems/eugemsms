<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\CustomFields;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Settings\DeactivateCustomFieldAction;
use Modules\Core\Domain\DataObjects\Settings\DeactivateCustomFieldData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\School;

/**
 * `Core\CustomFields\Index` (Book A CORE-04 §5). There is no "edit"
 * action for a custom field's own definition — only
 * `DeactivateCustomFieldAction` exists (deactivate, or delete outright
 * once it has zero recorded values, BR-CORE-04-010) — so this screen
 * offers Deactivate/Delete, not Edit; changing a field means retiring
 * it and defining a new one via `CustomFields\Builder`.
 */
#[Title('Custom fields')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function deactivate(int $definitionId): void
    {
        $this->run($definitionId, deleteIfUnused: false, successMessage: __('Field deactivated.'));
    }

    public function delete(int $definitionId): void
    {
        $this->run($definitionId, deleteIfUnused: true, successMessage: __('Field deleted.'));
    }

    private function run(int $definitionId, bool $deleteIfUnused, string $successMessage): void
    {
        try {
            app(DeactivateCustomFieldAction::class)->execute(new DeactivateCustomFieldData(
                definitionId: $definitionId,
                actingUserId: (int) Auth::id(),
                deleteIfUnused: $deleteIfUnused,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast($successMessage);
    }

    public function render(): View
    {
        $query = CustomFieldDefinition::query()->where('school_id', $this->school->id)->orderBy('entity_type')->orderBy('sort_order');

        return view('core::custom-fields.index', [
            'definitions' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'entity_type' => ['label' => __('Entity'), 'sortable' => true, 'searchable' => true],
            'key' => ['label' => __('Key'), 'sortable' => true, 'searchable' => true],
            'label' => ['label' => __('Label'), 'sortable' => true, 'searchable' => true],
            'data_type' => ['label' => __('Type'), 'sortable' => true],
            'is_required' => [
                'label' => __('Required'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
            'is_active' => [
                'label' => __('Active'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
        ];
    }
}

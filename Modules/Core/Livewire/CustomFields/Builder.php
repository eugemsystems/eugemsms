<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\CustomFields;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Settings\DefineCustomFieldAction;
use Modules\Core\Domain\DataObjects\Settings\DefineCustomFieldData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Core\CustomFields\Builder` (Book A CORE-04 §5). Create-only —
 * `DefineCustomFieldAction` has no update counterpart (see
 * `CustomFields\Index`'s own docblock for why).
 */
#[Title('New custom field')]
#[Layout('layouts.app')]
final class Builder extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public string $entityType = '';

    public string $key = '';

    public string $label = '';

    public string $description = '';

    public string $dataType = 'text';

    public string $optionsText = '';

    public string $validationRules = '';

    public bool $isRequired = false;

    public bool $isSearchable = false;

    public bool $isExposedInApi = true;

    public bool $isPrintable = false;

    public string $groupLabel = '';

    public int $sortOrder = 0;

    /**
     * @var array<int, string>
     */
    public array $dataTypes = [
        'text', 'textarea', 'number', 'decimal', 'date', 'datetime', 'bool',
        'select', 'multiselect', 'file', 'money', 'email', 'phone',
    ];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function create(): void
    {
        $options = null;

        if (in_array($this->dataType, ['select', 'multiselect'], true) && trim($this->optionsText) !== '') {
            $options = array_values(array_filter(array_map('trim', explode(',', $this->optionsText))));
        }

        try {
            app(DefineCustomFieldAction::class)->execute(new DefineCustomFieldData(
                schoolId: $this->school->id,
                entityType: $this->entityType,
                key: $this->key,
                label: $this->label,
                dataType: $this->dataType,
                actingUserId: (int) Auth::id(),
                description: $this->description === '' ? null : $this->description,
                options: $options,
                validationRules: $this->validationRules === '' ? null : $this->validationRules,
                isRequired: $this->isRequired,
                isSearchable: $this->isSearchable,
                isExposedInApi: $this->isExposedInApi,
                isPrintable: $this->isPrintable,
                groupLabel: $this->groupLabel === '' ? null : $this->groupLabel,
                sortOrder: $this->sortOrder,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Custom field created.'));

        $this->redirect(route('custom-fields.index', $this->school), navigate: true);
    }

    public function render(): View
    {
        return view('core::custom-fields.builder');
    }
}

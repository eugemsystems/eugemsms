<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Linen;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateIssuableItemAction;
use Modules\Boarding\Domain\DataObjects\CreateIssuableItemData;
use Modules\Boarding\Models\IssuableItem;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Linen\Items` (Book F BRD-05 §4, `linen.manage`). List + create the
 * issuable-item catalogue.
 */
#[Title('Issuable items catalogue')]
#[Layout('layouts.app')]
final class Items extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $category = 'bedding';

    public bool $isReturnable = true;

    public ?int $replacementCostMinor = null;

    public ?int $expectedLifespanTerms = null;

    public bool $requiresTagging = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.linen.view');
    }

    public function create(): void
    {
        $this->authorizePermission('boarding.linen.manage');

        $this->validate(['code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:120']]);

        app(CreateIssuableItemAction::class)->execute(new CreateIssuableItemData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            category: $this->category,
            currency: $this->school->base_currency,
            isReturnable: $this->isReturnable,
            replacementCostMinor: $this->replacementCostMinor,
            expectedLifespanTerms: $this->expectedLifespanTerms,
            requiresTagging: $this->requiresTagging,
        ));

        $this->reset(['code', 'name', 'replacementCostMinor', 'expectedLifespanTerms', 'requiresTagging']);
        $this->toast(__('Item added to the catalogue.'));
    }

    public function render(): View
    {
        return view('boarding::linen.items', [
            'items' => IssuableItem::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

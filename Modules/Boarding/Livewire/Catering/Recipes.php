<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Catering;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateRecipeAction;
use Modules\Boarding\Domain\DataObjects\CreateRecipeData;
use Modules\Boarding\Domain\DataObjects\RecipeIngredientInput;
use Modules\Boarding\Models\Recipe;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Catering\Recipes` (Book F BRD-04 §6, `catering.recipe.manage`).
 * List + create. `inventory_item_id` is a plain numeric field, not a
 * picker — `FIN-09` (Book H, the real inventory item master) is not
 * built in this codebase yet (§0.3's "planning-only mode"), so there
 * is no catalogue to pick from; the id is accepted as given and
 * costing stays `null` end-to-end regardless.
 */
#[Title('Recipes')]
#[Layout('layouts.app')]
final class Recipes extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $category = 'staple';

    public int $baseServings = 100;

    public bool $isVegetarian = false;

    /** @var array<int, array{inventoryItemId: ?int, quantity: ?float, unit: string}> */
    public array $ingredients = [['inventoryItemId' => null, 'quantity' => null, 'unit' => 'kg']];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.catering.recipe.manage');
    }

    public function addIngredientRow(): void
    {
        $this->ingredients[] = ['inventoryItemId' => null, 'quantity' => null, 'unit' => 'kg'];
    }

    public function create(): void
    {
        $this->validate(['code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:150'], 'baseServings' => ['required', 'integer', 'min:1']]);

        $ingredients = [];

        foreach ($this->ingredients as $row) {
            if ($row['inventoryItemId'] === null || $row['quantity'] === null) {
                continue;
            }

            $ingredients[] = new RecipeIngredientInput(
                inventoryItemId: (int) $row['inventoryItemId'],
                quantity: (float) $row['quantity'],
                unit: $row['unit'],
            );
        }

        app(CreateRecipeAction::class)->execute(new CreateRecipeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            category: $this->category,
            baseServings: $this->baseServings,
            ingredients: $ingredients,
            isVegetarian: $this->isVegetarian,
        ));

        $this->reset(['code', 'name', 'isVegetarian']);
        $this->ingredients = [['inventoryItemId' => null, 'quantity' => null, 'unit' => 'kg']];
        $this->toast(__('Recipe created.'));
    }

    public function render(): View
    {
        return view('boarding::catering.recipes', [
            'recipes' => Recipe::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Catering;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateMenuCycleAction;
use Modules\Boarding\Domain\Actions\SetMenuDayAction;
use Modules\Boarding\Domain\DataObjects\CreateMenuCycleData;
use Modules\Boarding\Domain\DataObjects\SetMenuDayData;
use Modules\Boarding\Models\MenuCycle;
use Modules\Boarding\Models\MenuDay;
use Modules\Boarding\Models\Recipe;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Catering\MenuCycles` (Book F BRD-04 §6, `catering.menu.manage`).
 * Folds the spec's separate "Menu planner" screen in here: pick a
 * cycle, set each day/meal's recipes in a grid, the same fold
 * `Curriculum\Frameworks` uses for its own banner.
 */
#[Title('Menu cycles')]
#[Layout('layouts.app')]
final class MenuCycles extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $selectedCycleId = null;

    public string $cycleName = '';

    public int $cycleLengthDays = 7;

    public int $planCycleDay = 1;

    public string $planMeal = 'lunch';

    /** @var array<int, int> */
    public array $planRecipeIds = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.catering.menu.view');
    }

    public function createCycle(): void
    {
        $this->authorizePermission('boarding.catering.menu.manage');

        $year = $this->school->currentAcademicYear();

        if ($year === null || trim($this->cycleName) === '') {
            $this->toast(__('A current academic year and a name are required.'), 'danger');

            return;
        }

        $cycle = app(CreateMenuCycleAction::class)->execute(new CreateMenuCycleData(
            schoolId: $this->school->id,
            academicYearId: $year->id,
            name: $this->cycleName,
            cycleLengthDays: $this->cycleLengthDays,
        ));

        $this->reset(['cycleName']);
        $this->selectedCycleId = $cycle->id;
        $this->toast(__('Menu cycle created.'));
    }

    public function setDay(): void
    {
        $this->authorizePermission('boarding.catering.menu.manage');

        if ($this->selectedCycleId === null || $this->planRecipeIds === []) {
            $this->toast(__('Pick at least one recipe.'), 'danger');

            return;
        }

        app(SetMenuDayAction::class)->execute(new SetMenuDayData(
            cycleId: $this->selectedCycleId,
            cycleDay: $this->planCycleDay,
            meal: $this->planMeal,
            recipeIds: $this->planRecipeIds,
        ));

        $this->reset(['planRecipeIds']);
        $this->toast(__('Menu day set.'));
    }

    public function render(): View
    {
        return view('boarding::catering.menu-cycles', [
            'cycles' => MenuCycle::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'recipes' => Recipe::where('school_id', $this->school->id)->where('is_active', true)->get(),
            'menuDays' => $this->selectedCycleId !== null
                ? MenuDay::where('cycle_id', $this->selectedCycleId)->orderBy('cycle_day')->get()
                : collect(),
        ]);
    }
}

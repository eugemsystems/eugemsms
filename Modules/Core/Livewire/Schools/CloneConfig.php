<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Schools;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\CloneSchoolConfigurationAction;
use Modules\Core\Domain\DataObjects\Schools\CloneConfigData;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Core\Schools\Clone` (Book A CORE-02 §5). Copies another of the
 * tenant's schools' sections/grade levels/houses into this one — named
 * `CloneConfig` here since `Clone` collides with the PHP `clone` keyword.
 */
#[Title('Clone school setup')]
#[Layout('layouts.app')]
final class CloneConfig extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public ?int $sourceSchoolId = null;

    public bool $cloneSections = true;

    public bool $cloneGradeLevels = true;

    public bool $cloneHouses = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function clone(): void
    {
        $this->validate(['sourceSchoolId' => ['required', 'integer']]);

        $result = app(CloneSchoolConfigurationAction::class)->execute(new CloneConfigData(
            sourceSchoolId: $this->sourceSchoolId,
            targetSchoolId: $this->school->id,
            actingUserId: (int) Auth::id(),
            cloneSections: $this->cloneSections,
            cloneGradeLevels: $this->cloneGradeLevels,
            cloneHouses: $this->cloneHouses,
        ));

        $this->toast(__(':sections sections, :levels grade levels, and :houses houses copied.', [
            'sections' => $result->sectionsCreated,
            'levels' => $result->gradeLevelsCreated,
            'houses' => $result->housesCreated,
        ]));
    }

    public function render(): View
    {
        return view('core::schools.clone', [
            'candidateSchools' => Auth::user()?->schools()
                ->where('schools.id', '!=', $this->school->id)
                ->orderBy('name')
                ->get() ?? collect(),
        ]);
    }
}

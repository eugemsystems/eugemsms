<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Houses;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\CreateHouseAction;
use Modules\Core\Domain\DataObjects\Schools\CreateHouseData;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\House;
use Modules\Core\Models\School;

/**
 * `Core\Houses\Index` (Book A CORE-02 §5).
 */
#[Title('Houses')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $code = '';

    public string $name = '';

    public ?string $colour = '#696cff';

    public ?string $motto = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function create(): void
    {
        app(CreateHouseAction::class)->execute(new CreateHouseData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            colour: $this->colour,
            motto: $this->motto,
        ));

        $this->reset(['code', 'name', 'motto', 'showCreateModal']);
        $this->colour = '#696cff';

        $this->toast(__('House created.'));
    }

    public function render(): View
    {
        return view('core::houses.index', [
            'houses' => House::query()->where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

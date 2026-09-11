<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Houses;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\CreateHouseAction;
use Modules\Core\Domain\Actions\Schools\DeleteHouseAction;
use Modules\Core\Domain\Actions\Schools\UpdateHouseAction;
use Modules\Core\Domain\DataObjects\Schools\CreateHouseData;
use Modules\Core\Domain\DataObjects\Schools\DeleteHouseData;
use Modules\Core\Domain\DataObjects\Schools\UpdateHouseData;
use Modules\Core\Domain\Exceptions\DomainException;
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

    public ?int $editingHouseId = null;

    public string $code = '';

    public string $name = '';

    public ?string $colour = '#696cff';

    public ?string $motto = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingHouseId', 'code', 'name', 'motto']);
        $this->colour = '#696cff';
        $this->showCreateModal = true;
    }

    public function openEditModal(int $houseId): void
    {
        $house = House::where('school_id', $this->school->id)->findOrFail($houseId);

        $this->editingHouseId = $house->id;
        $this->code = $house->code;
        $this->name = $house->name;
        $this->colour = $house->colour;
        $this->motto = $house->motto;
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        if ($this->editingHouseId !== null) {
            try {
                app(UpdateHouseAction::class)->execute(new UpdateHouseData(
                    schoolId: $this->school->id,
                    houseId: $this->editingHouseId,
                    code: $this->code,
                    name: $this->name,
                    colour: $this->colour,
                    motto: $this->motto,
                ));
            } catch (DomainException $e) {
                $this->toast($e->getMessage(), 'danger');

                return;
            }

            $this->reset(['code', 'name', 'motto', 'editingHouseId', 'showCreateModal']);
            $this->colour = '#696cff';

            $this->toast(__('House updated.'));

            return;
        }

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

    public function delete(int $houseId): void
    {
        try {
            app(DeleteHouseAction::class)->execute(new DeleteHouseData(
                schoolId: $this->school->id,
                houseId: $houseId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('House deleted.'));
    }

    public function render(): View
    {
        return view('core::houses.index', [
            'houses' => House::query()->where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

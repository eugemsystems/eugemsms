<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Movement;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateMovementCheckpointAction;
use Modules\Boarding\Domain\DataObjects\CreateMovementCheckpointData;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Movement\Checkpoints` (Book F BRD-02 §6, `boarding.movement.manage`).
 * List + create, via the new gap-filling `CreateMovementCheckpointAction`
 * (see that action's own docblock).
 */
#[Title('Movement checkpoints')]
#[Layout('layouts.app')]
final class Checkpoints extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $checkpointType = 'gate';

    public bool $isBoundary = false;

    public string $hardwareDeviceId = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.movement.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'checkpointType' => ['required', 'in:gate,building,zone,dining,sanatorium,transport'],
        ]);

        app(CreateMovementCheckpointAction::class)->execute(new CreateMovementCheckpointData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            checkpointType: $this->checkpointType,
            isBoundary: $this->isBoundary,
            hardwareDeviceId: $this->hardwareDeviceId !== '' ? $this->hardwareDeviceId : null,
        ));

        $this->reset(['code', 'name', 'isBoundary', 'hardwareDeviceId']);
        $this->toast(__('Checkpoint created.'));
    }

    public function render(): View
    {
        return view('boarding::movement.checkpoints', [
            'checkpoints' => MovementCheckpoint::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

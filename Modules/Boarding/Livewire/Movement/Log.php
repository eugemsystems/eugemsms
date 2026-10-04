<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Movement;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\RecordCheckpointMovementAction;
use Modules\Boarding\Domain\DataObjects\RecordCheckpointMovementData;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\MovementLogEntry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Movement\Log` (Book F BRD-02 §6, `boarding.movement.view` to
 * browse, `boarding.movement.manage` to record a manual scan).
 * Append-only (BR-BRD-02-017) — no edit/delete control exists here
 * because none exists in `MovementLogEntry` at the database grant
 * level. A hardware failure never blocks this: `method: manual` is
 * always on this form regardless of whether a reader exists.
 */
#[Title('Movement log')]
#[Layout('layouts.app')]
final class Log extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $studentSearch = '';

    public ?int $checkpointId = null;

    public string $direction = 'out';

    public bool $hasActiveExeat = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.movement.view');
    }

    public function record(): void
    {
        $this->authorizePermission('boarding.movement.manage');

        if ($this->studentId === null || $this->checkpointId === null) {
            $this->toast(__('Pick a learner and a checkpoint.'), 'danger');

            return;
        }

        $entry = app(RecordCheckpointMovementAction::class)->execute(new RecordCheckpointMovementData(
            studentId: $this->studentId,
            checkpointId: $this->checkpointId,
            direction: $this->direction,
            method: 'manual',
            recordedByUserId: (int) Auth::id(),
            hasActiveExeat: $this->hasActiveExeat,
        ));

        $this->reset(['studentId', 'studentSearch', 'hasActiveExeat']);

        if (! $entry->is_authorised) {
            $this->toast(__('Recorded — unauthorised boundary crossing, security alerted.'), 'danger');

            return;
        }

        $this->toast(__('Movement recorded.'));
    }

    public function render(): View
    {
        $searchResults = $this->studentSearch !== ''
            ? Student::where('school_id', $this->school->id)
                ->where(fn ($q) => $q->where('first_name', 'like', "%{$this->studentSearch}%")->orWhere('last_name', 'like', "%{$this->studentSearch}%"))
                ->limit(10)->get()
            : collect();

        return view('boarding::movement.log', [
            'checkpoints' => MovementCheckpoint::where('school_id', $this->school->id)->where('is_active', true)->get(),
            'entries' => MovementLogEntry::where('school_id', $this->school->id)->with('student', 'checkpoint')->orderByDesc('occurred_at')->limit(50)->get(),
            'searchResults' => $searchResults,
        ]);
    }
}

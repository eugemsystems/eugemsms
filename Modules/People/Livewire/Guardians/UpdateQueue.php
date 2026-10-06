<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Guardians;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\DecideGuardianContactUpdateAction;
use Modules\People\Domain\DataObjects\DecideGuardianContactUpdateData;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianContactUpdate;

/**
 * `People\Guardians\UpdateQueue` (Book C PPL-03 §7, `people.guardians.update`).
 * Changes of phone, email or address a guardian has asked for. They control
 * notification delivery and account recovery, so each waits here until staff
 * approve it (BR-PPL-03-021). The current and requested values are shown side
 * by side.
 */
#[Title('Contact update queue')]
#[Layout('layouts.app')]
final class UpdateQueue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $rejectingId = null;

    public string $note = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.update');
    }

    public function approve(int $updateId): void
    {
        $this->decide($updateId, true, null);
    }

    public function startReject(int $updateId): void
    {
        $this->authorizePermission('people.guardians.update');
        $this->rejectingId = $updateId;
        $this->note = '';
    }

    public function reject(): void
    {
        if ($this->rejectingId === null) {
            return;
        }

        $this->decide($this->rejectingId, false, trim($this->note) === '' ? null : $this->note);
        $this->reset('rejectingId', 'note');
    }

    private function decide(int $updateId, bool $approve, ?string $note): void
    {
        $this->authorizePermission('people.guardians.update');

        $update = GuardianContactUpdate::query()->findOrFail($updateId);

        try {
            app(DecideGuardianContactUpdateAction::class)->execute(new DecideGuardianContactUpdateData($update->id, (int) auth()->id(), $approve, $note));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($approve ? __('Approved and applied.') : __('Rejected.'));
    }

    public function render(): View
    {
        $pending = GuardianContactUpdate::query()->where('status', 'pending')->orderBy('id')->get();

        return view('people::guardians.update-queue', ['pending' => $pending, 'guardians' => Guardian::query()->whereIn('id', $pending->pluck('guardian_id'))->get()->keyBy('id')]);
    }
}

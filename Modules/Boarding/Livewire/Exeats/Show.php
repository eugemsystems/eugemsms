<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Exeats;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\ApproveExeatAction;
use Modules\Boarding\Domain\Actions\RejectExeatAction;
use Modules\Boarding\Domain\DataObjects\ApproveExeatData;
use Modules\Boarding\Domain\DataObjects\RejectExeatData;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exeats\Show` (Book F BRD-03 §6, `boarding.exeat.view` to browse,
 * `boarding.exeat.approve` to decide). Request, approval, pass
 * verification code, and movement — actual departure/return capture
 * stays on `Gate\Terminal` (the spec's own deliberate split), so this
 * screen reads the gate's own record rather than duplicating entry.
 */
#[Title('Exeat detail')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Exeat $exeat;

    public string $rejectionReason = '';

    public function mount(School $school, Exeat $exeat): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.exeat.view');
        $this->exeat = $exeat;
    }

    public function approve(): void
    {
        $this->authorizePermission('boarding.exeat.approve');

        try {
            app(ApproveExeatAction::class)->execute(new ApproveExeatData(
                exeatId: $this->exeat->id,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->exeat->refresh();
        $this->toast(__('Exeat approved — pass issued.'));
    }

    public function reject(): void
    {
        $this->authorizePermission('boarding.exeat.approve');

        if (trim($this->rejectionReason) === '') {
            $this->toast(__('A rejection reason is required.'), 'danger');

            return;
        }

        app(RejectExeatAction::class)->execute(new RejectExeatData(
            exeatId: $this->exeat->id,
            rejectionReason: $this->rejectionReason,
            rejectedByUserId: (int) Auth::id(),
        ));

        $this->exeat->refresh();
        $this->toast(__('Exeat rejected.'));
    }

    public function render(): View
    {
        $this->exeat->load('student', 'exeatType', 'collectingGuardian');

        return view('boarding::exeats.show', [
            'attempts' => CollectionAttempt::where('exeat_id', $this->exeat->id)->orderByDesc('occurred_at')->get(),
        ]);
    }
}

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
use Modules\Boarding\Models\Exeat;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exeats\Approvals` (Book F BRD-03 §6, `boarding.exeat.approve`). The
 * batched queue — same approve/reject actions as `Exeats\Show`, laid
 * out for working through several requests quickly rather than one
 * at a time.
 */
#[Title('Exeat approval queue')]
#[Layout('layouts.app')]
final class Approvals extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public array $rejectionReasons = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.exeat.approve');
    }

    public function approve(int $exeatId): void
    {
        try {
            app(ApproveExeatAction::class)->execute(new ApproveExeatData(
                exeatId: $exeatId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Approved — pass issued.'));
    }

    public function reject(int $exeatId): void
    {
        $reason = trim($this->rejectionReasons[$exeatId] ?? '');

        if ($reason === '') {
            $this->toast(__('A rejection reason is required.'), 'danger');

            return;
        }

        app(RejectExeatAction::class)->execute(new RejectExeatData(
            exeatId: $exeatId,
            rejectionReason: $reason,
            rejectedByUserId: (int) Auth::id(),
        ));

        unset($this->rejectionReasons[$exeatId]);
        $this->toast(__('Rejected.'));
    }

    public function render(): View
    {
        return view('boarding::exeats.approvals', [
            'pending' => Exeat::where('school_id', $this->school->id)->where('status', 'pending')->with('student', 'exeatType')->orderBy('departs_at')->get(),
        ]);
    }
}

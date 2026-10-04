<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Visitors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\SignInVisitorAction;
use Modules\Boarding\Domain\Actions\SignOutVisitorAction;
use Modules\Boarding\Domain\DataObjects\SignInVisitorData;
use Modules\Boarding\Domain\DataObjects\SignOutVisitorData;
use Modules\Boarding\Domain\Exceptions\VisitorBlacklistedException;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Visitors\Terminal` (Book F BRD-03 §6, `boarding.visitor.manage`).
 * Sign in/out. A blacklisted visitor is refused outright — this
 * screen shows that refusal as a hard toast error with security
 * already alerted server-side (`SignInVisitorAction`'s own
 * `RecordSecurityEventAction` call); there is no "sign in anyway"
 * control because none exists in the Action layer.
 */
#[Title('Visitor terminal')]
#[Layout('layouts.app')]
final class Terminal extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $fullName = '';

    public string $visitPurpose = 'parent_visit';

    public string $idType = '';

    public string $idNumber = '';

    public string $phone = '';

    public string $vehicleRegistration = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.visitor.manage');
    }

    public function signIn(): void
    {
        if (trim($this->fullName) === '') {
            $this->toast(__('Name is required.'), 'danger');

            return;
        }

        try {
            app(SignInVisitorAction::class)->execute(new SignInVisitorData(
                schoolId: $this->school->id,
                fullName: $this->fullName,
                visitPurpose: $this->visitPurpose,
                gateStaffUserId: (int) Auth::id(),
                idType: $this->idType !== '' ? $this->idType : null,
                idNumber: $this->idNumber !== '' ? $this->idNumber : null,
                phone: $this->phone !== '' ? $this->phone : null,
                vehicleRegistration: $this->vehicleRegistration !== '' ? $this->vehicleRegistration : null,
            ));
        } catch (VisitorBlacklistedException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['fullName', 'idType', 'idNumber', 'phone', 'vehicleRegistration']);
        $this->toast(__('Signed in.'));
    }

    public function signOut(int $visitorLogId): void
    {
        app(SignOutVisitorAction::class)->execute(new SignOutVisitorData(
            visitorLogId: $visitorLogId,
            gateStaffUserId: (int) Auth::id(),
        ));

        $this->toast(__('Signed out.'));
    }

    public function render(): View
    {
        return view('boarding::visitors.terminal', [
            'onSite' => VisitorLogEntry::where('school_id', $this->school->id)->whereNull('signed_out_at')->with('visitor')->orderByDesc('signed_in_at')->get(),
        ]);
    }
}

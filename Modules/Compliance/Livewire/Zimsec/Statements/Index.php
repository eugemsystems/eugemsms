<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Zimsec\Statements;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\ConfirmStatementOfEntryAction;
use Modules\Compliance\Domain\Actions\DistributeStatementsOfEntryAction;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Zimsec\Statements` (Book H3 CMP-01 §4, `zimsec.manage`).
 * Distribution (one document per candidate, uploaded once and never
 * regenerated) and the guardian's own confirmation of receipt
 * (BR-CMP-01-009) are two distinct steps, shown on the same screen.
 */
#[Title('ZIMSEC statements of entry')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $registrationId = 0;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('zimsec.manage');

        $this->registrationId = (int) (ZimsecRegistration::where('school_id', $school->id)->orderByDesc('id')->first()?->id);
    }

    public function distribute(): void
    {
        $this->authorizePermission('zimsec.manage');

        $distributed = app(DistributeStatementsOfEntryAction::class)->execute($this->registrationId);

        $this->toast(__(':count statement(s) distributed.', ['count' => $distributed->count()]));
    }

    public function confirmReceipt(int $candidateId): void
    {
        $this->authorizePermission('zimsec.manage');

        app(ConfirmStatementOfEntryAction::class)->execute($candidateId);

        $this->toast(__('Receipt confirmed.'));
    }

    public function render(): View
    {
        $candidates = $this->registrationId !== 0
            ? ZimsecCandidate::where('registration_id', $this->registrationId)->orderBy('surname')->get()
            : collect();

        return view('compliance::zimsec.statements.index', [
            'registrations' => ZimsecRegistration::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'candidates' => $candidates,
        ]);
    }
}

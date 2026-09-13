<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Till;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\RevealAndCloseTillSessionAction;
use Modules\Finance\Domain\DataObjects\RevealAndCloseTillSessionData;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\TillSession;

/**
 * `Finance\Till\VarianceApproval` (Book B FIN-04 §3/§5/BR-FIN-04-004,
 * `finance.till.supervise`, dangerous). Lists every till session stuck
 * in `declaring` for this school — a cashier's `CashUp::reveal()`
 * lands a session here whenever `RevealAndCloseTillSessionAction`
 * throws `VarianceSignOffRequiredException`. A supervisor opens one
 * under their own login and finishes the same reveal call themselves,
 * supplying `supervisedByUserId = Auth::id()` — the action itself
 * rejects a supervisor who is the session's own cashier
 * (`supervisorMustDiffer`), so this can't be self-approved.
 */
#[Title('Variance approval')]
#[Layout('layouts.app')]
final class VarianceApproval extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSystemAccounts;

    public ?int $reviewingSessionId = null;

    public string $varianceReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.till.supervise');
    }

    public function review(int $tillSessionId): void
    {
        $this->reviewingSessionId = $tillSessionId;
        $this->varianceReason = '';
        $this->resetErrorBag();
    }

    public function approve(): void
    {
        $this->validate([
            'varianceReason' => ['required', 'string', 'max:500'],
        ]);

        try {
            app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
                tillSessionId: (int) $this->reviewingSessionId,
                closedByUserId: (int) Auth::id(),
                cashOverShortAccountId: $this->requireSystemAccount('cash_over_short', __('Cash Over/Short')),
                varianceReason: $this->varianceReason,
                supervisedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->addError('varianceReason', $e->getMessage());

            return;
        }

        $this->reviewingSessionId = null;
        $this->varianceReason = '';
    }

    /**
     * @return Collection<int, TillSession>
     */
    public function pendingSessions(): Collection
    {
        return TillSession::where('status', 'declaring')
            ->with(['till', 'cashier'])
            ->orderBy('opened_at')
            ->get();
    }

    public function render(): View
    {
        return view('finance::till.variance-approval');
    }
}

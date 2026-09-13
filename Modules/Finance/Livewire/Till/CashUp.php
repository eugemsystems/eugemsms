<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Till;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\DeclareTillCountAction;
use Modules\Finance\Domain\Actions\RevealAndCloseTillSessionAction;
use Modules\Finance\Domain\DataObjects\DeclareTillCountData;
use Modules\Finance\Domain\DataObjects\RevealAndCloseTillSessionData;
use Modules\Finance\Domain\Exceptions\VarianceSignOffRequiredException;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\TillSession;

/**
 * `Finance\Till\CashUp` (Book B FIN-04 §3 ⭐/BR-FIN-04-003/004,
 * `finance.till.operate`). Two steps, strictly in order:
 * `DeclareTillCountAction` records the cashier's blind physical count
 * (the form never shows `expected_closing`), then `reveal()` calls
 * `RevealAndCloseTillSessionAction`, which computes and compares.
 *
 * A variance beyond tolerance leaves the session in `declaring` — this
 * screen does not let the cashier supply their own supervisor sign-off
 * (BR-FIN-04-004 requires a genuinely different user). Instead it
 * points them at `Finance\Till\VarianceApproval`, which a user holding
 * `finance.till.supervise` opens under their own login and finishes
 * the reveal themselves — real `Auth::id()` identity, not a
 * self-reported dropdown pick.
 */
#[Title('Cash up till')]
#[Layout('layouts.app')]
final class CashUp extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSystemAccounts;

    public TillSession $tillSession;

    /**
     * @var array<string, string>
     */
    public array $declaredClosing = [];

    public string $varianceReason = '';

    public bool $needsSupervisorSignOff = false;

    public function mount(School $school, TillSession $tillSession): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.till.operate');

        abort_unless($tillSession->cashier_id === Auth::id(), 403, __('This is not your own till session.'));
        abort_unless(in_array($tillSession->status, ['open', 'declaring'], true), 403, __('This till session is already closed.'));

        $this->tillSession = $tillSession;
        $this->needsSupervisorSignOff = $tillSession->status === 'declaring';

        foreach (array_keys($tillSession->opening_float) as $currency) {
            $this->declaredClosing[$currency] = '';
        }
    }

    public function declare(): void
    {
        $counts = [];

        foreach ($this->declaredClosing as $currency => $amount) {
            if ($amount !== '' && is_numeric($amount)) {
                $counts[$currency] = (int) round((float) $amount * 100);
            }
        }

        try {
            $this->tillSession = app(DeclareTillCountAction::class)->execute(new DeclareTillCountData(
                tillSessionId: $this->tillSession->id,
                declaredClosing: $counts,
                declaredByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->addError('declaredClosing', $e->getMessage());
        }
    }

    public function reveal(): void
    {
        try {
            $this->tillSession = app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
                tillSessionId: $this->tillSession->id,
                closedByUserId: (int) Auth::id(),
                cashOverShortAccountId: $this->requireSystemAccount('cash_over_short', __('Cash Over/Short')),
                varianceReason: $this->varianceReason !== '' ? $this->varianceReason : null,
            ));
        } catch (VarianceSignOffRequiredException $e) {
            $this->needsSupervisorSignOff = true;
            $this->addError('varianceReason', $e->getMessage());

            return;
        } catch (DomainException $e) {
            $this->addError('varianceReason', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.till.sessions', ['school' => $this->school], navigate: true);
    }

    public function render(): View
    {
        return view('finance::till.cash-up');
    }
}

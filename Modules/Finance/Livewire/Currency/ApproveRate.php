<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Currency;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApproveExchangeRateAction;
use Modules\Finance\Domain\Actions\RejectExchangeRateAction;
use Modules\Finance\Domain\Actions\SimulateRateChangeAction;
use Modules\Finance\Domain\DataObjects\ApproveExchangeRateData;
use Modules\Finance\Domain\DataObjects\RateChangeSimulation;
use Modules\Finance\Domain\DataObjects\RejectExchangeRateData;
use Modules\Finance\Domain\DataObjects\SimulateRateChangeData;
use Modules\Finance\Models\ExchangeRate;

/**
 * `Finance\Currency\ApproveRate` (Book B FIN-06 §6, `finance.rate.approve`)
 * — every rate still `pending` because its source requires approval
 * (BR-FIN-06-005). Shows the impact simulation for a rate BEFORE
 * approving it (BR-FIN-06-013/AC-FIN-06-005), rather than sending the
 * approver to a separate screen to look it up.
 */
#[Title('Approve exchange rates')]
#[Layout('layouts.app')]
final class ApproveRate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $simulatingRateId = null;

    public ?int $rejectingRateId = null;

    public string $rejectReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.rate.approve');
    }

    public function simulate(int $rateId): void
    {
        $this->simulatingRateId = $this->simulatingRateId === $rateId ? null : $rateId;
    }

    public function openReject(int $rateId): void
    {
        $this->rejectingRateId = $rateId;
        $this->rejectReason = '';
        $this->resetErrorBag();
    }

    public function approve(int $rateId): void
    {
        try {
            app(ApproveExchangeRateAction::class)->execute(new ApproveExchangeRateData(
                exchangeRateId: $rateId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Rate approved and now active.'));
    }

    public function reject(): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:10', 'max:255']]);

        try {
            app(RejectExchangeRateAction::class)->execute(new RejectExchangeRateData(
                exchangeRateId: (int) $this->rejectingRateId,
                rejectedByUserId: (int) Auth::id(),
                reason: $this->rejectReason,
            ));
        } catch (DomainException $e) {
            $this->addError('rejectReason', $e->getMessage());

            return;
        }

        $this->rejectingRateId = null;
        $this->toast(__('Rate rejected.'));
    }

    public function render(): View
    {
        $pending = ExchangeRate::query()->where('status', 'pending')->with('source', 'capturedBy')->orderBy('effective_from')->get();

        $simulation = null;

        if ($this->simulatingRateId !== null) {
            $rate = $pending->firstWhere('id', $this->simulatingRateId);

            if ($rate !== null) {
                $simulation = $this->simulateFor($rate);
            }
        }

        return view('finance::currency.approve-rate', [
            'pending' => $pending,
            'simulation' => $simulation,
        ]);
    }

    private function simulateFor(ExchangeRate $rate): ?RateChangeSimulation
    {
        $baseCurrency = $this->school->base_currency;

        if ($rate->from_currency === $baseCurrency) {
            $foreignCurrency = $rate->to_currency;
            $proposedRate = (string) $rate->inverse_rate;
        } elseif ($rate->to_currency === $baseCurrency) {
            $foreignCurrency = $rate->from_currency;
            $proposedRate = (string) $rate->rate;
        } else {
            // Neither side of this pair is the school's base currency —
            // it doesn't feed the GL's own base-currency revaluation, so
            // there is nothing to simulate against `RevaluationCalculator`.
            return null;
        }

        return app(SimulateRateChangeAction::class)->execute(new SimulateRateChangeData(
            schoolId: $this->school->id,
            foreignCurrency: $foreignCurrency,
            proposedRate: $proposedRate,
            asAt: now(),
        ));
    }
}

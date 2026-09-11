<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Sessions\ReopenPeriodAction;
use Modules\Core\Domain\Actions\Sessions\RequestPeriodReopenAction;
use Modules\Core\Domain\Actions\Sessions\RunPeriodCloseChecklistAction;
use Modules\Core\Domain\Actions\Sessions\TransitionPeriodStateAction;
use Modules\Core\Domain\DataObjects\Sessions\ReopenPeriodData;
use Modules\Core\Domain\DataObjects\Sessions\RequestPeriodReopenData;
use Modules\Core\Domain\DataObjects\Sessions\TransitionPeriodData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\PeriodReopenRequest;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * `Core\Sessions\PeriodControl` (Book A CORE-03 §5/§7). Drives one
 * term's academic-or-financial state machine through
 * `TransitionPeriodStateAction` — the single gateway; nothing here ever
 * writes `academic_state`/`financial_state` directly. `Locked → Open` is
 * deliberately not a direct button: BR-CORE-03 requires a *different*
 * user to approve a reopen than the one who requested it
 * (`core.period.reopen` / `core.period.approve_reopen` are meant to be
 * separate permissions — not yet enforced, CORE-05 gap), so that leg
 * goes through `RequestPeriodReopenAction` + `ReopenPeriodAction`
 * instead of the raw transition.
 */
#[Title('Period control')]
#[Layout('layouts.app')]
final class PeriodControl extends Component
{
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public Term $term;

    public PeriodType $type;

    public bool $showReasonModal = false;

    public string $reason = '';

    public bool $showRequestModal = false;

    public string $requestReason = '';

    public function mount(School $school, Term $term, string $periodType): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);

        abort_unless($term->school_id === $school->id, 404);

        $this->term = $term;
        $this->type = PeriodType::tryFrom($periodType) ?? abort(404);
    }

    /**
     * @return array<int, PeriodState>
     */
    public function availableTransitions(): array
    {
        $state = $this->term->stateFor($this->type);

        return match ($state) {
            PeriodState::Planned => [PeriodState::Open],
            PeriodState::Open => [PeriodState::SoftClosed],
            PeriodState::SoftClosed => [PeriodState::Locked, PeriodState::Open],
            PeriodState::Locked => [PeriodState::Archived],
            PeriodState::Archived => [],
        };
    }

    public function transitionTo(string $toState): void
    {
        $target = PeriodState::from($toState);

        // SoftClosed → Open is the one direct transition that requires a
        // written reason (assertReasonGiven in the action) — collect it
        // via the modal instead of transitioning blind.
        if ($target === PeriodState::Open && $this->term->stateFor($this->type) === PeriodState::SoftClosed) {
            $this->showReasonModal = true;

            return;
        }

        $this->runTransition($target, null);
    }

    public function confirmReasonedTransition(): void
    {
        $this->runTransition(PeriodState::Open, $this->reason);
    }

    private function runTransition(PeriodState $target, ?string $reason): void
    {
        try {
            app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
                termId: $this->term->id,
                periodType: $this->type,
                toState: $target,
                performedByUserId: (int) Auth::id(),
                reason: $reason,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->term->refresh();
        $this->reset(['showReasonModal', 'reason']);
        $this->resetErrorBag();

        $this->toast(__('Period state updated.'));
    }

    public function requestReopen(): void
    {
        try {
            app(RequestPeriodReopenAction::class)->execute(new RequestPeriodReopenData(
                termId: $this->term->id,
                periodType: $this->type,
                reason: $this->requestReason,
                requestedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['showRequestModal', 'requestReason']);

        $this->toast(__('Reopen requested — a different user must approve it.'));
    }

    public function approveReopen(int $requestId): void
    {
        try {
            app(ReopenPeriodAction::class)->execute(new ReopenPeriodData(
                requestId: $requestId,
                approvedByUserId: (int) Auth::id(),
                ipAddress: request()->ip(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->term->refresh();

        $this->toast(__('Period reopened.'));
    }

    public function render(): View
    {
        $pendingRequest = PeriodReopenRequest::where('term_id', $this->term->id)
            ->where('period_type', $this->type->value)
            ->where('status', 'pending')
            ->latest('requested_at')
            ->first();

        $checklist = $this->term->stateFor($this->type) === PeriodState::SoftClosed
            ? app(RunPeriodCloseChecklistAction::class)->execute($this->term, $this->type)
            : null;

        return view('core::sessions.period-control', [
            'currentState' => $this->term->stateFor($this->type),
            'transitions' => $this->availableTransitions(),
            'pendingRequest' => $pendingRequest,
            'checklist' => $checklist,
        ]);
    }
}

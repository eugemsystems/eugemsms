<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Sessions\ExecuteRolloverAction;
use Modules\Core\Domain\Actions\Sessions\InitiateRolloverAction;
use Modules\Core\Domain\Actions\Sessions\RollbackRolloverAction;
use Modules\Core\Domain\DataObjects\Sessions\ExecuteRolloverData;
use Modules\Core\Domain\DataObjects\Sessions\InitiateRolloverData;
use Modules\Core\Domain\DataObjects\Sessions\RollbackRolloverData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * `Core\Sessions\RolloverWizard` (Book A CORE-03 §5). Drives one
 * school's term-to-term rollover through `InitiateRolloverAction`
 * (validates only) then `ExecuteRolloverAction` (runs the registered
 * handlers, hash-chained and reversible within 30 days via
 * `RollbackRolloverAction` — see that action's own docblock). At most
 * one roll-over may be in flight per school (BR-CORE-03-022); while one
 * is `pending`/`validating`/`running` this screen shows its progress
 * instead of the term picker.
 */
#[Title('Roll over a term')]
#[Layout('layouts.app')]
final class RolloverWizard extends Component
{
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $fromTermId = '';

    public string $toTermId = '';

    public bool $showRollbackModal = false;

    public string $rollbackReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
    }

    public function initiate(): void
    {
        if ($this->fromTermId === '' || $this->toTermId === '') {
            $this->addError('toTermId', __('Choose both a from-term and a to-term.'));

            return;
        }

        if ($this->fromTermId === $this->toTermId) {
            $this->addError('toTermId', __('The from-term and to-term must be different.'));

            return;
        }

        try {
            app(InitiateRolloverAction::class)->execute(new InitiateRolloverData(
                schoolId: $this->school->id,
                fromTermId: (int) $this->fromTermId,
                toTermId: (int) $this->toTermId,
                initiatedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['fromTermId', 'toTermId']);

        $this->toast(__('Rollover validation started.'));
    }

    public function execute(int $rolloverId): void
    {
        try {
            app(ExecuteRolloverAction::class)->execute(new ExecuteRolloverData(
                rolloverId: $rolloverId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Rollover executed.'));
    }

    public function rollback(int $rolloverId): void
    {
        try {
            app(RollbackRolloverAction::class)->execute(new RollbackRolloverData(
                rolloverId: $rolloverId,
                performedByUserId: (int) Auth::id(),
                reason: $this->rollbackReason,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['showRollbackModal', 'rollbackReason']);

        $this->toast(__('Rollover rolled back.'));
    }

    public function render(): View
    {
        $activeRollover = PeriodRollover::where('school_id', $this->school->id)
            ->whereIn('status', ['pending', 'validating', 'running', 'failed'])
            ->with(['fromTerm', 'toTerm'])
            ->latest('id')
            ->first();

        $terms = Term::where('school_id', $this->school->id)->orderByDesc('starts_on')->get();

        return view('core::sessions.rollover-wizard', [
            'terms' => $terms,
            'activeRollover' => $activeRollover,
        ]);
    }
}

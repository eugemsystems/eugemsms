<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Currency;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ReverseFxRevaluationAction;
use Modules\Finance\Domain\Actions\RunFxRevaluationAction;
use Modules\Finance\Domain\DataObjects\ReverseFxRevaluationData;
use Modules\Finance\Domain\DataObjects\RunFxRevaluationData;
use Modules\Finance\Models\FxRevaluation;

/**
 * `Finance\Currency\Revaluation` (Book B FIN-06 §6, `finance.fx.revalue`)
 * — period-end restatement of every revaluable balance-sheet account to
 * the closing rate (BR-FIN-06-010..012). "The step most systems skip":
 * skipping it silently overstates the balance sheet term after term.
 * Reversal goes through a full journal reversal, never deletion.
 */
#[Title('FX revaluation')]
#[Layout('layouts.app')]
final class Revaluation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $revaluationDate;

    public ?int $reversingRevaluationId = null;

    public string $reverseReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.fx.revalue');
        $this->revaluationDate = now()->toDateString();
    }

    public function run(): void
    {
        $this->validate(['revaluationDate' => ['required', 'date']]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('revaluationDate', __('No active academic year/term is set for this school.'));

            return;
        }

        try {
            $revaluation = app(RunFxRevaluationAction::class)->execute(new RunFxRevaluationData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                revaluationDate: Carbon::parse($this->revaluationDate),
                performedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->addError('revaluationDate', $e->getMessage());

            return;
        }

        $this->toast($revaluation->journal_id !== null
            ? __('Revaluation posted — net :amount.', ['amount' => number_format($revaluation->netMinor() / 100, 2)])
            : __('Revaluation run — nothing needed restating.'));
    }

    public function openReverse(int $revaluationId): void
    {
        $this->reversingRevaluationId = $revaluationId;
        $this->reverseReason = '';
        $this->resetErrorBag();
    }

    public function reverse(): void
    {
        $this->validate(['reverseReason' => ['required', 'string', 'min:15', 'max:500']]);

        try {
            app(ReverseFxRevaluationAction::class)->execute(new ReverseFxRevaluationData(
                revaluationId: (int) $this->reversingRevaluationId,
                reason: $this->reverseReason,
                reversedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->addError('reverseReason', $e->getMessage());

            return;
        }

        $this->reversingRevaluationId = null;
        $this->toast(__('Revaluation reversed.'));
    }

    public function render(): View
    {
        return view('finance::currency.revaluation', [
            'revaluations' => FxRevaluation::query()->with('term', 'performedBy')->orderByDesc('revaluation_date')->get(),
        ]);
    }
}

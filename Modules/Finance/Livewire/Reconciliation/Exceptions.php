<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Reconciliation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
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
use Modules\Finance\Domain\Actions\ConvertBankLineToSuspenseAction;
use Modules\Finance\Domain\Actions\ReviewReconciliationExceptionAction;
use Modules\Finance\Domain\DataObjects\ConvertBankLineToSuspenseData;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\ReconciliationRun;

/**
 * `Finance\Reconciliation\Exceptions` (Book B FIN-05 §5 ⭐/BR-FIN-05-013,
 * `finance.reconciliation.resolve`). "Nothing auto-resolves... a human
 * clears each one, and the clearing is recorded" — see
 * `ReviewReconciliationExceptionAction`'s own docblock for why that
 * recording had nowhere to go before this screen. `BANK_NO_RECEIPT` is
 * the one exception class with a real, already-built resolution path
 * one click away (`ConvertBankLineToSuspenseAction`, FIN-05's own
 * suspense conversion) — every other class is closed with a note only,
 * since the spec's own resolution guidance for them ("investigate
 * urgently", "correct by reversal") is a judgement call, not a system
 * action this pass can safely automate.
 */
#[Title('Reconciliation exceptions')]
#[Layout('layouts.app')]
final class Exceptions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use ResolvesSystemAccounts;
    use Toasts;

    public ?int $resolvingRunId = null;

    public ?int $resolvingIndex = null;

    public string $resolutionNote = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.reconciliation.resolve');
    }

    public function startResolving(int $runId, int $index): void
    {
        $this->resolvingRunId = $runId;
        $this->resolvingIndex = $index;
        $this->resolutionNote = '';
        $this->resetErrorBag();
    }

    public function cancelResolving(): void
    {
        $this->resolvingRunId = null;
        $this->resolvingIndex = null;
    }

    public function resolve(): void
    {
        $this->validate(['resolutionNote' => ['required', 'string', 'min:3', 'max:500']]);

        app(ReviewReconciliationExceptionAction::class)->execute(
            reconciliationRunId: (int) $this->resolvingRunId,
            exceptionIndex: (int) $this->resolvingIndex,
            resolutionNote: $this->resolutionNote,
            reviewedByUserId: (int) Auth::id(),
        );

        $this->resolvingRunId = null;
        $this->resolvingIndex = null;
        $this->toast(__('Exception resolved.'));
    }

    public function convertToSuspense(int $runId, int $index, int $bankStatementLineId): void
    {
        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No active academic year/term is set for this school.'), 'danger');

            return;
        }

        try {
            app(ConvertBankLineToSuspenseAction::class)->execute(new ConvertBankLineToSuspenseData(
                bankStatementLineId: $bankStatementLineId,
                academicYearId: $yearId,
                termId: $termId,
                convertedByUserId: (int) Auth::id(),
                suspenseAccountId: $this->requireSystemAccount('suspense', __('Suspense')),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        app(ReviewReconciliationExceptionAction::class)->execute(
            reconciliationRunId: $runId,
            exceptionIndex: $index,
            resolutionNote: __('Converted to a suspense item.'),
            reviewedByUserId: (int) Auth::id(),
        );

        $this->toast(__('Converted and resolved.'));
    }

    public function render(): View
    {
        return view('finance::reconciliation.exceptions', [
            'runs' => ReconciliationRun::where('school_id', $this->school->id)->where('exception_count', '>', 0)->orderByDesc('ran_at')->get(),
        ]);
    }
}

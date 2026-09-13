<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Billing;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApproveBillingRunAction;
use Modules\Finance\Domain\Actions\CommitBillingRunAction;
use Modules\Finance\Domain\DataObjects\ApproveBillingRunData;
use Modules\Finance\Domain\DataObjects\CommitBillingRunData;
use Modules\Finance\Models\BillingRun;
use Modules\Finance\Models\LearnerFeeAssignment;

/**
 * `Finance\Billing\Preview` (Book B FIN-02 §7, `finance.billing.run`) —
 * per-learner table, variance column, exception tab, drill to trace,
 * and the approve/commit actions (BR-FIN-02-013 ⭐, each behind its own,
 * narrower permission — `finance.billing.approve`/`.commit` — since a
 * well-run school holds those separately, per §9's own note).
 */
#[Title('Billing run preview')]
#[Layout('layouts.app')]
final class Preview extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public BillingRun $billingRun;

    public string $activeTab = 'learners';

    public ?int $expandedAssignmentId = null;

    public function mount(School $school, BillingRun $billingRun): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.billing.run');
        $this->billingRun = $billingRun;
    }

    public function canApprove(): bool
    {
        return $this->billingRun->status === 'preview' && $this->userHolds('finance.billing.approve');
    }

    public function canCommit(): bool
    {
        return $this->billingRun->status === 'approved' && $this->userHolds('finance.billing.commit');
    }

    private function userHolds(string $permissionName): bool
    {
        $user = Auth::user();

        return $user !== null
            && app(PermissionScopeResolver::class)->has($user, $permissionName, PermissionScope::Own, $this->school->id);
    }

    public function approve(): void
    {
        $this->authorizePermission('finance.billing.approve');

        try {
            $this->billingRun = app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData(
                billingRunId: $this->billingRun->id,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Billing run approved.'));
    }

    public function commit(): void
    {
        $this->authorizePermission('finance.billing.commit');

        try {
            $this->billingRun = app(CommitBillingRunAction::class)->execute(new CommitBillingRunData(
                billingRunId: $this->billingRun->id,
                committedByUserId: (int) Auth::id(),
                effectiveAt: now(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Billing run committed — invoices raised.'));
    }

    public function toggleTrace(int $assignmentId): void
    {
        $this->expandedAssignmentId = $this->expandedAssignmentId === $assignmentId ? null : $assignmentId;
    }

    public function render(): View
    {
        $assignments = LearnerFeeAssignment::query()
            ->where('billing_run_id', $this->billingRun->id)
            ->with('student', 'lines.component')
            ->get();

        return view('finance::billing.preview', [
            'assignments' => $assignments,
        ]);
    }
}

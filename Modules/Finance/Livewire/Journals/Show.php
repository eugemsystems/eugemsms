<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Journals;

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
use Modules\Finance\Domain\Actions\ApproveManualJournalAction;
use Modules\Finance\Domain\DataObjects\ApproveManualJournalData;
use Modules\Finance\Models\Journal;

/**
 * `Finance\Journals\Show` (Book B FIN-01 §8, `finance.journal.view`) —
 * one journal's header and lines. Offers Approve (draft, different user
 * than the creator, `finance.journal.approve`) and links to
 * `Journals\Reverse` (posted, not already reversed,
 * `finance.journal.reverse`) — both gated the same way the underlying
 * Actions themselves gate, so the button simply doesn't appear rather
 * than appearing and failing.
 */
#[Title('Journal')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Journal $journal;

    public function mount(School $school, Journal $journal): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.journal.view');
        $this->journal = $journal->load('lines.account', 'lines.costCentre', 'postedBy', 'approvedBy', 'reversesJournal', 'reversedByJournal');
    }

    public function canApprove(): bool
    {
        return $this->journal->isDraft()
            && $this->journal->posted_by !== Auth::id()
            && $this->userCan('finance.journal.approve');
    }

    public function canReverse(): bool
    {
        return $this->journal->status === 'posted'
            && ! $this->journal->isReversed()
            && $this->userCan('finance.journal.reverse');
    }

    private function userCan(string $permissionName): bool
    {
        $user = Auth::user();

        return $user !== null
            && app(PermissionScopeResolver::class)->has($user, $permissionName, PermissionScope::Own, $this->school->id);
    }

    public function approve(): void
    {
        $this->authorizePermission('finance.journal.approve');

        try {
            $this->journal = app(ApproveManualJournalAction::class)->execute(new ApproveManualJournalData(
                journalId: $this->journal->id,
                approvedByUserId: (int) Auth::id(),
            ))->load('lines.account', 'lines.costCentre', 'postedBy', 'approvedBy');
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Journal approved and posted.'));
    }

    public function render(): View
    {
        return view('finance::journals.show');
    }
}

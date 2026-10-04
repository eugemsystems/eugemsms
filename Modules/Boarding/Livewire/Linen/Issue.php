<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Linen;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\ApproveIssuedItemDamageChargeAction;
use Modules\Boarding\Domain\Actions\ApproveLostItemChargeAction;
use Modules\Boarding\Domain\Actions\IssueItemToLearnerAction;
use Modules\Boarding\Domain\Actions\ReportItemLostAction;
use Modules\Boarding\Domain\Actions\ReturnIssuedItemAction;
use Modules\Boarding\Domain\DataObjects\ApproveIssuedItemChargeData;
use Modules\Boarding\Domain\DataObjects\IssueItemToLearnerData;
use Modules\Boarding\Domain\DataObjects\ReturnIssuedItemData;
use Modules\Boarding\Models\IssuableItem;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * `Linen\Issue` (Book F BRD-05 §4, `linen.manage`). Issue, return,
 * report-lost, and approve-charge, scoped to one learner at a time —
 * also stands in for the spec's separate "Learner items" screen,
 * since picking a learner here already shows their full item list.
 */
#[Title('Issue & return')]
#[Layout('layouts.app')]
final class Issue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $studentId = null;

    public ?int $issuableItemId = null;

    public string $conditionAtIssue = 'new';

    public int $quantity = 1;

    public string $tagReference = '';

    public ?int $approveFeeComponentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.linen.view');
    }

    public function issue(): void
    {
        $this->authorizePermission('boarding.linen.manage');

        if ($this->studentId === null || $this->issuableItemId === null) {
            $this->toast(__('Pick a learner and an item.'), 'danger');

            return;
        }

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        try {
            app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
                schoolId: $this->school->id,
                termId: $term->id,
                studentId: $this->studentId,
                issuableItemId: $this->issuableItemId,
                conditionAtIssue: $this->conditionAtIssue,
                issuedByUserId: (int) Auth::id(),
                issuedOn: Carbon::now(),
                quantity: $this->quantity,
                tagReference: $this->tagReference !== '' ? $this->tagReference : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['issuableItemId', 'tagReference']);
        $this->toast(__('Item issued.'));
    }

    public function markReturned(int $learnerIssuedItemId, string $condition): void
    {
        $this->authorizePermission('boarding.linen.manage');

        app(ReturnIssuedItemAction::class)->execute(new ReturnIssuedItemData(
            learnerIssuedItemId: $learnerIssuedItemId,
            conditionAtReturn: $condition,
            receivedByUserId: (int) Auth::id(),
            returnedOn: Carbon::now(),
        ));

        $this->toast(__('Return recorded.'));
    }

    public function reportLost(int $learnerIssuedItemId): void
    {
        $this->authorizePermission('boarding.linen.manage');

        app(ReportItemLostAction::class)->execute($learnerIssuedItemId);
        $this->toast(__('Reported lost — pending approval for a replacement charge.'));
    }

    public function approveDamageCharge(int $learnerIssuedItemId): void
    {
        $this->authorizePermission('boarding.linen.manage');

        if ($this->approveFeeComponentId === null) {
            $this->toast(__('Pick a fee component.'), 'danger');

            return;
        }

        app(ApproveIssuedItemDamageChargeAction::class)->execute(new ApproveIssuedItemChargeData(
            learnerIssuedItemId: $learnerIssuedItemId,
            feeComponentId: $this->approveFeeComponentId,
            approvedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Damage charge approved.'));
    }

    public function approveLostCharge(int $learnerIssuedItemId): void
    {
        $this->authorizePermission('boarding.linen.manage');

        if ($this->approveFeeComponentId === null) {
            $this->toast(__('Pick a fee component.'), 'danger');

            return;
        }

        app(ApproveLostItemChargeAction::class)->execute(new ApproveIssuedItemChargeData(
            learnerIssuedItemId: $learnerIssuedItemId,
            feeComponentId: $this->approveFeeComponentId,
            approvedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Replacement charge approved.'));
    }

    public function render(): View
    {
        return view('boarding::linen.issue', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(),
            'issuableItems' => IssuableItem::where('school_id', $this->school->id)->get(),
            'feeComponents' => FeeComponent::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name']),
            'issuedItems' => $this->studentId !== null
                ? LearnerIssuedItem::where('student_id', $this->studentId)->with('issuableItem')->orderByDesc('id')->get()
                : collect(),
        ]);
    }
}

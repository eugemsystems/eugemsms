<?php

declare(strict_types=1);

namespace Modules\Facilities\Livewire\Hire;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Facilities\Domain\Actions\ApproveExternalHireAction;
use Modules\Facilities\Domain\Actions\AssessDamageAndRefundDepositAction;
use Modules\Facilities\Domain\Actions\CompleteBookingAction;
use Modules\Facilities\Domain\Actions\ConfirmBookingAction;
use Modules\Facilities\Domain\Actions\RecordHireDepositAction;
use Modules\Facilities\Domain\DataObjects\AssessDamageAndRefundDepositData;
use Modules\Facilities\Domain\DataObjects\ConfirmBookingData;
use Modules\Facilities\Domain\DataObjects\RecordHireDepositData;
use Modules\Facilities\Models\ResourceBooking;
use Modules\Finance\Models\Account;

/**
 * `Hire\Index` (Book H2 OPS-05 §4 ⭐/BR-OPS-05-003/005/006,
 * `facilities.hire.manage`). Folds the spec's own, separate
 * "Approvals" screen (`facilities.approve`) into this one external
 * hire lifecycle: the only thing that screen would ever approve in
 * this book is an external hire, and that is this screen's entire
 * subject — see `.ai/rules/facilities.md`. One list, one action bar:
 * approve, record deposit, confirm (contract + deposit both
 * required), complete, assess damage and refund.
 */
#[Title('External hire')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $selectedBookingId = null;

    public string $depositAmountMinor = '';

    public ?int $cashAccountId = null;

    public ?int $depositsHeldLiabilityAccountId = null;

    public string $damageDeductedMinor = '0';

    public ?int $damageRecoveryIncomeAccountId = null;

    public ?string $damageAssessmentNote = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('facilities.hire.manage');
    }

    public function select(int $bookingId): void
    {
        $this->selectedBookingId = $bookingId;
    }

    public function approve(int $bookingId): void
    {
        app(ApproveExternalHireAction::class)->execute($bookingId, (int) auth()->id());
        $this->toast(__('External hire approved.'));
    }

    public function recordDeposit(): void
    {
        if ($this->selectedBookingId === null) {
            return;
        }

        $this->validate([
            'depositAmountMinor' => ['required', 'integer', 'gt:0'],
            'cashAccountId' => ['required', 'integer'],
            'depositsHeldLiabilityAccountId' => ['required', 'integer'],
        ]);

        app(RecordHireDepositAction::class)->execute($this->selectedBookingId, new RecordHireDepositData(
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            amountMinor: (int) $this->depositAmountMinor,
            currency: $this->school->base_currency,
            cashAccountId: (int) $this->cashAccountId,
            depositsHeldLiabilityAccountId: (int) $this->depositsHeldLiabilityAccountId,
            performedByUserId: (int) auth()->id(),
        ));

        $this->reset(['depositAmountMinor']);
        $this->toast(__('Deposit recorded as a refundable liability.'));
    }

    public function confirm(int $bookingId): void
    {
        try {
            app(ConfirmBookingAction::class)->execute($bookingId, new ConfirmBookingData(
                academicYearId: (int) SessionContext::yearId(),
                confirmedByUserId: (int) auth()->id(),
                contractFileId: null,
            ));
        } catch (ValidationException|InvalidStateTransitionException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Booking confirmed.'));
    }

    public function complete(int $bookingId): void
    {
        app(CompleteBookingAction::class)->execute($bookingId);
        $this->toast(__('Booking completed.'));
    }

    public function assessAndRefund(): void
    {
        if ($this->selectedBookingId === null) {
            return;
        }

        try {
            app(AssessDamageAndRefundDepositAction::class)->execute($this->selectedBookingId, new AssessDamageAndRefundDepositData(
                academicYearId: (int) SessionContext::yearId(),
                termId: (int) SessionContext::termId(),
                currency: $this->school->base_currency,
                depositsHeldLiabilityAccountId: (int) $this->depositsHeldLiabilityAccountId,
                cashAccountId: (int) $this->cashAccountId,
                performedByUserId: (int) auth()->id(),
                damageDeductedMinor: (int) $this->damageDeductedMinor,
                damageRecoveryIncomeAccountId: $this->damageRecoveryIncomeAccountId,
                damageAssessmentNote: $this->damageAssessmentNote,
            ));
        } catch (ValidationException|InvalidStateTransitionException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['damageDeductedMinor', 'damageAssessmentNote']);
        $this->damageDeductedMinor = '0';
        $this->toast(__('Deposit assessed and refunded.'));
    }

    public function render(): View
    {
        return view('facilities::hire.index', [
            'bookings' => ResourceBooking::with('resource')->where('school_id', $this->school->id)->where('booking_type', 'external')->orderByDesc('starts_at')->limit(30)->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}

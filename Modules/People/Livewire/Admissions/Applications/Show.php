<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Applications;

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
use Modules\Finance\Models\Account;
use Modules\People\Domain\Actions\AcceptOfferAction;
use Modules\People\Domain\Actions\DeclineApplicationAction;
use Modules\People\Domain\Actions\ExpireOfferAction;
use Modules\People\Domain\Actions\OfferApplicationAction;
use Modules\People\Domain\Actions\PayAcceptanceDepositAction;
use Modules\People\Domain\Actions\PayApplicationFeeAction;
use Modules\People\Domain\DataObjects\AcceptOfferData;
use Modules\People\Domain\DataObjects\DeclineApplicationData;
use Modules\People\Domain\DataObjects\ExpireOfferData;
use Modules\People\Domain\DataObjects\OfferApplicationData;
use Modules\People\Domain\DataObjects\PayAcceptanceDepositData;
use Modules\People\Domain\DataObjects\PayApplicationFeeData;
use Modules\People\Models\Application;

/**
 * `Admissions\Applications\Show` (Book C PPL-02 §5, `people.admissions.application_view`
 * to open; each mutating action below checks its own, more sensitive
 * permission inline, per `AuthorizesPermissions`'s own standard). One
 * screen hosts the whole pipeline's action bar rather than a screen
 * per transition — the same "lifecycle screen" shape as Finance's own
 * `Till\CashUp`, since each transition here is a single-step action
 * with no surface big enough to earn its own route.
 */
#[Title('Application')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public Application $application;

    public string $feeTenderType = 'cash';

    public ?int $feeBankAccountId = null;

    public ?int $feeIncomeAccountId = null;

    public int $offerValidDays = 14;

    public string $declineReason = '';

    public string $depositTenderType = 'cash';

    public ?int $depositBankAccountId = null;

    public ?int $depositRefundableAccountId = null;

    public function mount(School $school, Application $application): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.admissions.application_view');

        abort_unless($application->school_id === $school->id, 404);

        $this->application = $application->load('guardians', 'intake', 'requestedGradeLevel');
    }

    public function payFee(): void
    {
        $this->authorizePermission('people.admissions.application_review');

        $this->validate([
            'feeTenderType' => ['required', 'string'],
            'feeBankAccountId' => ['required', 'integer'],
            'feeIncomeAccountId' => ['required', 'integer'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->addError('feeTenderType', __('No active term is set for this school.'));

            return;
        }

        try {
            app(PayApplicationFeeAction::class)->execute(new PayApplicationFeeData(
                applicationId: $this->application->id,
                termId: $termId,
                tenderType: $this->feeTenderType,
                bankAccountId: (int) $this->feeBankAccountId,
                incomeAccountId: (int) $this->feeIncomeAccountId,
                receivedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->refreshApplication();
        $this->toast(__('Application fee receipted.'));
    }

    public function makeOffer(): void
    {
        $this->authorizePermission('people.admissions.offer_make');

        $this->validate(['offerValidDays' => ['required', 'integer', 'min:1']]);

        try {
            app(OfferApplicationAction::class)->execute(new OfferApplicationData(
                applicationId: $this->application->id,
                offeredByUserId: (int) Auth::id(),
                offerValidDays: $this->offerValidDays,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->refreshApplication();
        $this->toast(__('Offer made.'));
    }

    public function expireOffer(): void
    {
        $this->authorizePermission('people.admissions.offer_make');

        try {
            app(ExpireOfferAction::class)->execute(new ExpireOfferData(applicationId: $this->application->id));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->refreshApplication();
        $this->toast(__('Offer expired; next waitlisted applicant promoted if one existed.'));
    }

    public function acceptOffer(): void
    {
        $this->authorizePermission('people.admissions.application_review');

        try {
            app(AcceptOfferAction::class)->execute(new AcceptOfferData(
                applicationId: $this->application->id,
                acceptedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->refreshApplication();
        $this->toast(__('Offer accepted.'));
    }

    public function declineApplication(): void
    {
        $this->authorizePermission('people.admissions.application_review');

        $this->validate(['declineReason' => ['required', 'string', 'max:120']]);

        try {
            app(DeclineApplicationAction::class)->execute(new DeclineApplicationData(
                applicationId: $this->application->id,
                reason: $this->declineReason,
                declinedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->refreshApplication();
        $this->toast(__('Application declined.'));
    }

    public function payDeposit(): void
    {
        $this->authorizePermission('people.admissions.application_review');

        $this->validate([
            'depositTenderType' => ['required', 'string'],
            'depositBankAccountId' => ['required', 'integer'],
            'depositRefundableAccountId' => ['required', 'integer'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->addError('depositTenderType', __('No active term is set for this school.'));

            return;
        }

        try {
            app(PayAcceptanceDepositAction::class)->execute(new PayAcceptanceDepositData(
                applicationId: $this->application->id,
                termId: $termId,
                tenderType: $this->depositTenderType,
                bankAccountId: (int) $this->depositBankAccountId,
                refundableDepositsAccountId: (int) $this->depositRefundableAccountId,
                receivedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->refreshApplication();
        $this->toast(__('Acceptance deposit receipted.'));
    }

    private function refreshApplication(): void
    {
        $this->application = $this->application->fresh(['guardians', 'intake', 'requestedGradeLevel']);
    }

    public function render(): View
    {
        return view('people::admissions.applications.show', [
            'accounts' => Account::where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}

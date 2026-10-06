<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Alumni\Donations;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\People\Domain\Actions\RecordDonationAction;
use Modules\People\Domain\DataObjects\RecordDonationData;
use Modules\People\Models\BursaryEndowment;
use Modules\People\Models\CapitalCampaign;
use Modules\People\Models\Donation;
use Modules\People\Models\Pledge;

/**
 * `Alumni\Donations\Record` (Book K PPL-06 §5, `alumni.donation.record`).
 * Posts `Dr Bank / Cr Donation Income` (or a restricted fund account) to the
 * ledger (BR-PPL-06-007). The year and term come from the school's current
 * period, never the form; the bank and income accounts must be the school's
 * own postable accounts; a donation to a pledge counts toward that pledge's
 * campaign automatically.
 */
#[Title('Record a donation')]
#[Layout('layouts.app')]
final class Record extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $donorName = '';

    public string $amount = '';

    public string $currency = 'USD';

    public ?int $bankAccountId = null;

    public ?int $incomeAccountId = null;

    public ?int $campaignId = null;

    public ?int $pledgeId = null;

    public ?int $endowmentId = null;

    public bool $isRestricted = false;

    public string $restrictionPurpose = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('alumni.donation.record');
    }

    public function record(): void
    {
        $this->authorizePermission('alumni.donation.record');
        $this->resetErrorBag();

        $this->validate(['donorName' => ['required', 'string', 'max:200'], 'amount' => ['required', 'numeric', 'gt:0'], 'bankAccountId' => ['required', 'integer'], 'restrictionPurpose' => ['nullable', 'string', 'max:255']]);

        $year = AcademicYear::query()->where('is_current', true)->first();
        $term = $year === null ? null : Term::query()->where('academic_year_id', $year->id)->where('starts_on', '<=', now())->orderByDesc('starts_on')->first();

        if ($year === null || $term === null) {
            $this->addError('donorName', __('There is no current academic year and term to post this donation to.'));

            return;
        }

        try {
            app(RecordDonationAction::class)->execute(new RecordDonationData(
                schoolId: $this->school->id, academicYearId: $year->id, termId: $term->id, donorName: $this->donorName,
                amountMinor: (int) round((float) $this->amount * 100), currency: strtoupper($this->currency),
                bankAccountId: (int) $this->bankAccountId, recordedByUserId: (int) auth()->id(),
                campaignId: $this->campaignId, pledgeId: $this->pledgeId, bursaryEndowmentId: $this->endowmentId, incomeAccountId: $this->incomeAccountId,
                isRestricted: $this->isRestricted, restrictionPurpose: $this->restrictionPurpose === '' ? null : $this->restrictionPurpose,
            ));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('donorName', $exception->getMessage());

            return;
        }

        $this->reset('donorName', 'amount', 'pledgeId', 'campaignId', 'endowmentId', 'isRestricted', 'restrictionPurpose');
        $this->toast(__('Donation recorded and posted to the ledger.'));
    }

    public function render(): View
    {
        return view('people::alumni.donations', [
            'accounts' => Account::query()->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
            'campaigns' => CapitalCampaign::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'pledges' => Pledge::query()->whereNotIn('status', ['lapsed'])->orderBy('donor_name')->limit(300)->get(['id', 'donor_name', 'currency', 'pledged_amount_minor']),
            'endowments' => BursaryEndowment::query()->where('status', 'active')->orderBy('donor_name')->get(),
            'recent' => Donation::query()->orderByDesc('id')->limit(15)->get(),
        ]);
    }
}

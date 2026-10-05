<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Zimsec\Fees;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\BillZimsecEntryFeesAction;
use Modules\Compliance\Domain\Actions\RecordZimsecFeeCollectionAction;
use Modules\Compliance\Domain\Actions\RecordZimsecRemittanceAction;
use Modules\Compliance\Domain\DataObjects\BillZimsecEntryFeesData;
use Modules\Compliance\Domain\DataObjects\RecordZimsecFeeCollectionData;
use Modules\Compliance\Domain\DataObjects\RecordZimsecRemittanceData;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;

/**
 * `Compliance\Zimsec\Fees` (Book H3 CMP-01 §4 ⭐, `zimsec.manage`).
 * Billed/collected/remitted and the shortfall BR-CMP-01-007 exists to
 * surface before it becomes "a recurring source of unexplained
 * deficit" — `RecordZimsecRemittanceAction` itself already refuses a
 * remittance exceeding what's been collected; this screen's own job is
 * showing the running numbers, not re-deriving that guard.
 */
#[Title('ZIMSEC entry fees')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $registrationId = 0;

    public int $feeComponentId = 0;

    public string $collectionAmount = '';

    public string $remittanceAmount = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('zimsec.manage');

        $this->registrationId = (int) (ZimsecRegistration::where('school_id', $school->id)->orderByDesc('id')->first()?->id);
    }

    public function billFees(): void
    {
        $this->authorizePermission('zimsec.manage');

        $this->validate(['feeComponentId' => ['required', 'integer', 'min:1']]);

        $year = $this->school->currentAcademicYear();
        $term = $year?->currentTerm();

        if ($year === null || $term === null) {
            $this->toast(__('No current academic year/term to bill against.'), 'danger');

            return;
        }

        $billed = app(BillZimsecEntryFeesAction::class)->execute(new BillZimsecEntryFeesData(
            registrationId: $this->registrationId,
            feeComponentId: $this->feeComponentId,
            academicYearId: $year->id,
            termId: $term->id,
            raisedByUserId: (int) auth()->id(),
        ));

        $this->toast(__(':count candidate(s) billed.', ['count' => $billed->count()]));
    }

    public function recordCollection(): void
    {
        $this->authorizePermission('zimsec.manage');

        $this->validate(['collectionAmount' => ['required', 'numeric', 'min:0.01']]);

        app(RecordZimsecFeeCollectionAction::class)->execute(new RecordZimsecFeeCollectionData(
            registrationId: $this->registrationId,
            amountMinor: (int) round(((float) $this->collectionAmount) * 100),
            recordedByUserId: (int) auth()->id(),
        ));

        $this->reset(['collectionAmount']);
        $this->toast(__('Collection recorded.'));
    }

    public function recordRemittance(): void
    {
        $this->authorizePermission('zimsec.manage');

        $this->validate(['remittanceAmount' => ['required', 'numeric', 'min:0.01']]);

        try {
            app(RecordZimsecRemittanceAction::class)->execute(new RecordZimsecRemittanceData(
                registrationId: $this->registrationId,
                amountMinor: (int) round(((float) $this->remittanceAmount) * 100),
                recordedByUserId: (int) auth()->id(),
            ));
        } catch (InvalidStateTransitionException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['remittanceAmount']);
        $this->toast(__('Remittance recorded.'));
    }

    public function render(): View
    {
        return view('compliance::zimsec.fees.index', [
            'registrations' => ZimsecRegistration::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'selected' => $this->registrationId !== 0 ? ZimsecRegistration::find($this->registrationId) : null,
            'feeComponents' => FeeComponent::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}

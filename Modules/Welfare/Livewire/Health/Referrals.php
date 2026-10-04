<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\ChargeReferralCostAction;
use Modules\Welfare\Domain\Actions\MakeExternalReferralAction;
use Modules\Welfare\Domain\Actions\RecordReferralReturnAction;
use Modules\Welfare\Domain\DataObjects\MakeExternalReferralData;
use Modules\Welfare\Models\ExternalReferral;

/**
 * `Health\Referrals` (Book G BRD-06 §5, `health.referral.manage` —
 * Tier 3). One lifecycle screen hosting make → return → charge, the
 * same fold `Damages\Index` uses for report → approve → dispute.
 * Closes the `hospital` roll-status stub (BR-BRD-06-017).
 */
#[Title('External referrals')]
#[Layout('layouts.app')]
final class Referrals extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $referralType = 'hospital';

    public string $facilityName = '';

    public string $reason = '';

    public string $urgency = 'routine';

    public ?string $transportMethod = null;

    public ?bool $guardianPresent = null;

    public ?int $returningReferralId = null;

    public ?string $returnOutcome = null;

    public ?int $chargingReferralId = null;

    public ?int $costMinor = null;

    public ?int $feeComponentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.referral.manage');
    }

    public function make(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'referralType' => ['required', 'string'],
            'facilityName' => ['required', 'string'],
            'reason' => ['required', 'string'],
            'urgency' => ['required', 'string'],
        ]);

        app(MakeExternalReferralAction::class)->execute(new MakeExternalReferralData(
            schoolId: $this->school->id,
            studentId: (int) $this->studentId,
            referralType: $this->referralType,
            facilityName: $this->facilityName,
            reason: $this->reason,
            urgency: $this->urgency,
            referredAt: Carbon::now(),
            referredByUserId: (int) Auth::id(),
            transportMethod: $this->transportMethod,
            guardianPresent: $this->guardianPresent,
        ));

        $this->reset(['facilityName', 'reason', 'transportMethod', 'guardianPresent']);
        $this->toast(__('Referral made — guardian notified.'));
    }

    public function recordReturn(int $referralId): void
    {
        app(RecordReferralReturnAction::class)->execute($referralId, $this->returnOutcome);

        $this->reset(['returningReferralId', 'returnOutcome']);
        $this->toast(__('Return recorded.'));
    }

    public function charge(int $referralId): void
    {
        if ($this->costMinor === null || $this->feeComponentId === null) {
            $this->toast(__('A fee component and cost are required.'), 'danger');

            return;
        }

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        try {
            app(ChargeReferralCostAction::class)->execute(
                referralId: $referralId,
                termId: $term->id,
                academicYearId: (int) $term->academic_year_id,
                feeComponentId: $this->feeComponentId,
                costMinor: (int) $this->costMinor,
                currency: $this->school->base_currency,
                approvedByUserId: (int) Auth::id(),
            );
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['chargingReferralId', 'costMinor', 'feeComponentId']);
        $this->toast(__('Charge raised through FIN-02.'));
    }

    public function render(): View
    {
        return view('welfare::health.referrals', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'referrals' => ExternalReferral::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('referred_at')->limit(100)->get(),
            'feeComponents' => FeeComponent::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

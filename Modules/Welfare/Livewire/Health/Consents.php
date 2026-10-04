<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\Actions\GrantMedicalConsentAction;
use Modules\Welfare\Domain\Actions\WithdrawMedicalConsentAction;
use Modules\Welfare\Domain\DataObjects\GrantMedicalConsentData;
use Modules\Welfare\Models\MedicalConsent;

/**
 * `Health\Consents` (Book G BRD-06 §5, `health.consent.manage` —
 * Tier 3). Grant/withdraw. `GrantMedicalConsentAction` itself refuses a
 * guardian with no active `may_authorise_medical` relationship
 * (BR-BRD-06-011).
 */
#[Title('Medical consents')]
#[Layout('layouts.app')]
final class Consents extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public ?int $guardianId = null;

    public string $consentType = 'otc_medication';

    public ?string $scopeDetail = null;

    public bool $granted = true;

    public string $grantedVia = 'form';

    public string $effectiveFrom = '';

    public ?string $effectiveTo = null;

    public ?int $withdrawingConsentId = null;

    public string $withdrawReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.consent.manage');

        $this->effectiveFrom = now()->toDateString();
    }

    public function grant(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'guardianId' => ['required', 'integer'],
            'consentType' => ['required', 'string'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        try {
            app(GrantMedicalConsentAction::class)->execute(new GrantMedicalConsentData(
                schoolId: $this->school->id,
                studentId: (int) $this->studentId,
                guardianId: (int) $this->guardianId,
                consentType: $this->consentType,
                granted: $this->granted,
                grantedAt: Carbon::now(),
                grantedVia: $this->grantedVia,
                effectiveFrom: Carbon::parse($this->effectiveFrom),
                scopeDetail: $this->scopeDetail,
                effectiveTo: $this->effectiveTo !== null && $this->effectiveTo !== '' ? Carbon::parse($this->effectiveTo) : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['scopeDetail', 'effectiveTo']);
        $this->toast(__('Consent recorded.'));
    }

    public function withdraw(int $consentId): void
    {
        if (trim($this->withdrawReason) === '') {
            $this->toast(__('A withdrawal reason is required.'), 'danger');

            return;
        }

        app(WithdrawMedicalConsentAction::class)->execute($consentId, $this->withdrawReason);

        $this->reset(['withdrawingConsentId', 'withdrawReason']);
        $this->toast(__('Consent withdrawn.'));
    }

    public function render(): View
    {
        return view('welfare::health.consents', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'guardians' => $this->studentId !== null
                ? Guardian::whereIn('id', StudentGuardian::where('student_id', $this->studentId)->where('status', 'active')->pluck('guardian_id'))->get()
                : Guardian::where('school_id', $this->school->id)->limit(300)->get(['id', 'first_name', 'last_name']),
            'consents' => MedicalConsent::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('id')->limit(100)->get(),
        ]);
    }
}

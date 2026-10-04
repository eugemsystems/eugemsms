<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Insurance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CheckInsuranceExpiryAction;
use Modules\Stores\Domain\Actions\CheckUnderInsuranceAction;
use Modules\Stores\Domain\Actions\RecordInsurancePolicyAction;
use Modules\Stores\Domain\DataObjects\RecordInsurancePolicyData;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\AssetInsurance;

/**
 * `Assets\Insurance\Index` (Book H1 FIN-10 §5, `assets.insurance.manage`).
 * Under-insurance is compared against NET BOOK VALUE of the covered
 * category, never original cost (BR-FIN-10-016) — read live from
 * `CheckUnderInsuranceAction`, never a stored flag.
 */
#[Title('Insurance')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $policyNumber = '';

    public string $insurer = '';

    public string $policyType = 'all_risk';

    public ?int $categoryId = null;

    public string $sumInsuredMinor = '';

    public string $premiumMinor = '';

    public string $startsOn;

    public string $expiresOn;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('assets.insurance.manage');
        $this->startsOn = now()->toDateString();
        $this->expiresOn = now()->addYear()->toDateString();
    }

    public function record(): void
    {
        $this->validate([
            'policyNumber' => ['required', 'string', 'max:60'],
            'insurer' => ['required', 'string', 'max:200'],
            'categoryId' => ['required', 'integer'],
            'sumInsuredMinor' => ['required', 'integer', 'min:0'],
            'premiumMinor' => ['required', 'integer', 'min:0'],
            'startsOn' => ['required', 'date'],
            'expiresOn' => ['required', 'date', 'after:startsOn'],
        ]);

        try {
            app(RecordInsurancePolicyAction::class)->execute(new RecordInsurancePolicyData(
                schoolId: $this->school->id,
                policyNumber: $this->policyNumber,
                insurer: $this->insurer,
                policyType: $this->policyType,
                sumInsuredMinor: (int) $this->sumInsuredMinor,
                currency: 'USD',
                premiumMinor: (int) $this->premiumMinor,
                startsOn: Carbon::parse($this->startsOn),
                expiresOn: Carbon::parse($this->expiresOn),
                categoryId: $this->categoryId,
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset(['policyNumber', 'insurer', 'sumInsuredMinor', 'premiumMinor']);
        $this->toast(__('Policy recorded.'));
    }

    public function render(): View
    {
        return view('stores::assets.insurance.index', [
            'categories' => AssetCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
            'policies' => AssetInsurance::where('school_id', $this->school->id)->orderByDesc('expires_on')->get(),
            'underInsured' => app(CheckUnderInsuranceAction::class)->execute($this->school->id)->pluck('id'),
            'expiring' => app(CheckInsuranceExpiryAction::class)->execute($this->school->id)->pluck('id'),
        ]);
    }
}

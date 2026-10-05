<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Meters;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Domain\Actions\CreateMeterAction;
use Modules\Utilities\Domain\DataObjects\CreateMeterData;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\UtilityAccount;

/**
 * `Meters\Index` (Book H2 OPS-04 §6 ⭐/BR-OPS-04-009, `utilities.manage`).
 * Scope, cost centre and current balance — the submetered consumption
 * this screen registers is what later allocates to a real cost centre.
 */
#[Title('Meters')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $utilityAccountId = null;

    public string $meterNumber = '';

    public string $meterType = 'electricity_prepaid';

    public string $location = '';

    public string $servesScope = 'whole_school';

    public ?int $scopeId = null;

    public ?int $costCentreId = null;

    public string $unit = 'kWh';

    public string $multiplier = '1';

    public ?string $lowBalanceThreshold = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('utilities.manage');
    }

    public function create(): void
    {
        $this->validate([
            'utilityAccountId' => ['required', 'integer'],
            'meterNumber' => ['required', 'string'],
            'meterType' => ['required', 'string'],
            'location' => ['required', 'string'],
            'servesScope' => ['required', 'string'],
            'unit' => ['required', 'string'],
        ]);

        app(CreateMeterAction::class)->execute(new CreateMeterData(
            schoolId: $this->school->id,
            utilityAccountId: (int) $this->utilityAccountId,
            meterNumber: $this->meterNumber,
            meterType: $this->meterType,
            location: $this->location,
            servesScope: $this->servesScope,
            unit: $this->unit,
            scopeId: $this->scopeId,
            costCentreId: $this->costCentreId,
            multiplier: (float) $this->multiplier,
            lowBalanceThreshold: $this->lowBalanceThreshold !== null && $this->lowBalanceThreshold !== '' ? (float) $this->lowBalanceThreshold : null,
        ));

        $this->reset(['meterNumber', 'location', 'scopeId', 'costCentreId', 'lowBalanceThreshold']);
        $this->toast(__('Meter registered.'));
    }

    public function render(): View
    {
        return view('utilities::meters.index', [
            'meters' => Meter::with('utilityAccount')->where('school_id', $this->school->id)->orderBy('meter_number')->get(),
            'utilityAccounts' => UtilityAccount::where('school_id', $this->school->id)->orderBy('provider')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Compliance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Transport\Domain\Actions\CheckVehicleComplianceExpiryAction;
use Modules\Transport\Domain\Actions\RaiseComplianceRenewalWorkOrderAction;
use Modules\Transport\Domain\Actions\RegisterVehicleComplianceAction;
use Modules\Transport\Domain\DataObjects\RegisterVehicleComplianceData;
use Modules\Transport\Models\Vehicle;
use Modules\Transport\Models\VehicleCompliance;

/**
 * `Compliance\Index` 🇿🇼 (Book H2 OPS-01 §5/BR-OPS-01-002/003,
 * `transport.manage`). The alert window itself
 * (`transport.compliance_alert_days`, default `[60,30,7]`) is
 * versioned, effective-dated configuration read live through
 * `SettingResolver` — never a hard-coded `[60, 30, 7]` literal in this
 * screen, per CLAUDE.md's "every statutory/regulatory figure is
 * versioned configuration" rule. The seven compliance types
 * themselves (vehicle licence, ZINARA, certificate of fitness,
 * insurance, passenger insurance, route permit, radio licence) come
 * straight from the spec's own `vehicle_compliance.compliance_type`
 * enum (BR-OPS-01-002) and are not independently re-derived here.
 */
#[Title('Vehicle compliance')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $vehicleId = null;

    public string $complianceType = 'vehicle_licence';

    public string $expiresOn = '';

    public ?string $referenceNumber = null;

    public ?string $issuedOn = null;

    public ?int $costMinor = null;

    public ?string $issuingAuthority = null;

    /** @var array<int, string> */
    public array $renewalCostCentreId = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('transport.manage');
        $this->expiresOn = now()->addYear()->toDateString();
    }

    public function register(): void
    {
        $this->validate([
            'vehicleId' => ['required', 'integer'],
            'complianceType' => ['required', 'string'],
            'expiresOn' => ['required', 'date'],
        ]);

        app(RegisterVehicleComplianceAction::class)->execute(new RegisterVehicleComplianceData(
            schoolId: $this->school->id,
            vehicleId: (int) $this->vehicleId,
            complianceType: $this->complianceType,
            expiresOn: Carbon::parse($this->expiresOn),
            referenceNumber: $this->referenceNumber,
            issuedOn: $this->issuedOn !== null && $this->issuedOn !== '' ? Carbon::parse($this->issuedOn) : null,
            costMinor: $this->costMinor,
            currency: $this->costMinor !== null ? $this->school->base_currency : null,
            issuingAuthority: $this->issuingAuthority,
        ));

        $this->reset(['referenceNumber', 'issuedOn', 'costMinor', 'issuingAuthority']);
        $this->toast(__('Compliance record registered.'));
    }

    public function checkExpiry(): void
    {
        $expiring = app(CheckVehicleComplianceExpiryAction::class)->execute($this->school->id);
        $this->toast(__(':count record(s) in the alert window; expired records flipped to expired.', ['count' => $expiring->count()]));
    }

    public function raiseRenewal(int $complianceId): void
    {
        $costCentreId = $this->renewalCostCentreId[$complianceId] ?? '';

        if ($costCentreId === '') {
            $this->toast(__('A cost centre is required to raise a renewal work order.'), 'danger');

            return;
        }

        try {
            $workOrder = app(RaiseComplianceRenewalWorkOrderAction::class)->execute($complianceId, (int) $costCentreId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Renewal work order :number raised.', ['number' => $workOrder->work_order_number]));
    }

    public function render(): View
    {
        $scope = new ScopeChain(schoolId: $this->school->id);
        $alertDays = app(SettingResolver::class)->get('transport.compliance_alert_days', $scope);

        return view('transport::compliance.index', [
            'records' => VehicleCompliance::with('vehicle')->where('school_id', $this->school->id)->orderBy('expires_on')->get(),
            'vehicles' => Vehicle::where('school_id', $this->school->id)->orderBy('fleet_number')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'alertDays' => $alertDays,
        ]);
    }
}

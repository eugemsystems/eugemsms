<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Devices;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Domain\Actions\CheckCertificateExpiryAction;
use Modules\Fiscal\Domain\Actions\RegisterFiscalDeviceAction;
use Modules\Fiscal\Domain\DataObjects\RegisterFiscalDeviceData;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * `Fiscal\Devices\Index` (Book H3 FIN-13 §3/§7 ⭐⭐, `fiscal.device.manage`).
 * Folds the spec's own separate "Certificate lifecycle" screen in —
 * expiry, environment and status are shown on each device's own row
 * rather than a second route, since no `RenewCertificateAction`
 * exists distinct from `RegisterFiscalDeviceAction` itself (a real
 * ZIMRA renewal is registering a fresh device; `environment` is
 * fixed forever on an existing one per BR-FIN-13-016, so there is no
 * "edit" to give a separate screen). `CheckCertificateExpiryAction`'s
 * own on-demand scan (`.certificate_alert_days`, uncronned) is the
 * honest stand-in for a scheduled alert, the same pattern this book's
 * other periodic scans already use.
 */
#[Title('Fiscal devices')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $deviceId = '';

    public string $deviceSerial = '';

    public string $taxpayerName = '';

    public string $taxpayerTin = '';

    public string $vatNumber = '';

    public string $environment = 'sandbox';

    public string $apiBaseUrl = '';

    public string $deviceBranchId = '';

    public int $flaggedCount = 0;

    public bool $checked = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.device.manage');
    }

    public function register(): void
    {
        $this->validate([
            'deviceId' => ['required', 'string', 'max:40'],
            'deviceSerial' => ['required', 'string', 'max:60'],
            'taxpayerName' => ['required', 'string', 'max:200'],
            'taxpayerTin' => ['required', 'string', 'max:30'],
            'environment' => ['required', 'in:sandbox,production'],
            'apiBaseUrl' => ['required', 'string', 'max:255'],
        ]);

        app(RegisterFiscalDeviceAction::class)->execute(new RegisterFiscalDeviceData(
            schoolId: $this->school->id,
            deviceId: $this->deviceId,
            deviceSerial: $this->deviceSerial,
            taxpayerName: $this->taxpayerName,
            taxpayerTin: $this->taxpayerTin,
            environment: $this->environment,
            apiBaseUrl: $this->apiBaseUrl,
            deviceBranchId: $this->deviceBranchId !== '' ? $this->deviceBranchId : null,
            vatNumber: $this->vatNumber !== '' ? $this->vatNumber : null,
        ));

        $this->reset(['deviceId', 'deviceSerial', 'taxpayerName', 'taxpayerTin', 'vatNumber', 'apiBaseUrl', 'deviceBranchId']);
        $this->toast(__('Device registered and active.'));
    }

    public function checkExpiry(): void
    {
        $flagged = app(CheckCertificateExpiryAction::class)->execute($this->school->id);
        $this->flaggedCount = $flagged->count();
        $this->checked = true;
    }

    public function render(): View
    {
        return view('fiscal::devices.index', [
            'devices' => FiscalDevice::where('school_id', $this->school->id)->orderByDesc('id')->get(),
        ]);
    }
}

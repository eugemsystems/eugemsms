<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Integrations\Hardware;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\MarkOfflineHardwareDevicesAction;
use Modules\Intelligence\Domain\Actions\RegisterHardwareDeviceAction;
use Modules\Intelligence\Domain\Actions\RotateApiClientKeyAction;
use Modules\Intelligence\Domain\Registry\HardwareScanRouteRegistry;
use Modules\Intelligence\Models\HardwareDevice;

/**
 * `Intelligence\Integrations\Hardware\Index` (Book J INT-04 §3/§5,
 * `integration.hardware.manage`). Each device gets its own narrowly
 * scoped credential (BR-INT-04-007) — the key is shown once at
 * registration or rotation and never again. A device going offline
 * never blocks manual marking in the owning module (BR-INT-04-009), so
 * this screen is monitoring only: status and heartbeat, nothing that
 * gates a roll call.
 */
#[Title('Hardware devices')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public const array DEVICE_TYPES = ['rfid_reader', 'biometric', 'qr_scanner', 'gate_terminal'];

    public string $deviceType = 'rfid_reader';

    public string $purpose = '';

    public string $location = '';

    public ?string $revealedKey = null;

    public ?string $revealedFor = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('integration.hardware.manage');
    }

    public function registerDevice(): void
    {
        $this->authorizePermission('integration.hardware.manage');
        $this->resetErrorBag();

        $this->validate(['location' => ['nullable', 'string', 'max:150']]);

        try {
            $registered = app(RegisterHardwareDeviceAction::class)->execute(
                $this->school->id, $this->deviceType, $this->purpose,
                $this->location === '' ? null : $this->location, (int) auth()->id(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('purpose', $exception->getMessage());

            return;
        }

        $this->revealedKey = $registered['plaintextKey'];
        $this->revealedFor = $registered['device']->ulid;
        $this->reset('location');
    }

    public function rotate(int $deviceId): void
    {
        $this->authorizePermission('integration.hardware.manage');

        $device = HardwareDevice::where('school_id', $this->school->id)->findOrFail($deviceId);

        try {
            $rotated = app(RotateApiClientKeyAction::class)->execute((int) $device->api_client_id);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->revealedKey = $rotated['plaintextKey'];
        $this->revealedFor = $device->ulid;
    }

    public function markSilentOffline(): void
    {
        $this->authorizePermission('integration.hardware.manage');

        $marked = app(MarkOfflineHardwareDevicesAction::class)->execute($this->school->id);

        $this->toast(__(':count device(s) marked offline. Manual marking is unaffected.', ['count' => count($marked)]));
    }

    public function dismissKey(): void
    {
        $this->revealedKey = null;
        $this->revealedFor = null;
    }

    public function render(): View
    {
        return view('intelligence::integrations.hardware', [
            'devices' => HardwareDevice::where('school_id', $this->school->id)->orderBy('location')->limit(200)->get(),
            'purposes' => HardwareScanRouteRegistry::purposes(),
            'deviceTypes' => self::DEVICE_TYPES,
        ]);
    }
}

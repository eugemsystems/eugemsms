<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\DataObjects\RegisterFiscalDeviceData;
use Modules\Fiscal\Domain\Events\FiscalDeviceRegistered;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * ACT-RegisterFiscalDevice (Book H3 FIN-13 §3/BR-FIN-13-014/015/016).
 * CSR generation and device registration in one pass (a real ZIMRA
 * onboarding is a human-mediated, multi-day process; this models the
 * two API calls that bookend it). `private_key_ref` is a key-store
 * reference the driver returns — never a raw key stored inline
 * (BR-FIN-13-015). `environment` is fixed at registration and never
 * changes on an existing device (BR-FIN-13-016) — hiring a production
 * device requires registering a new one, not flipping a flag.
 */
final class RegisterFiscalDeviceAction extends Action
{
    public function __construct(
        private readonly FiscalGatewayDriver $driver,
    ) {}

    public function execute(RegisterFiscalDeviceData $data): FiscalDevice
    {
        $device = $this->transaction(fn (): FiscalDevice => FiscalDevice::create([
            'school_id' => $data->schoolId,
            'device_id' => $data->deviceId,
            'device_serial' => $data->deviceSerial,
            'device_branch_id' => $data->deviceBranchId,
            'taxpayer_name' => $data->taxpayerName,
            'taxpayer_tin' => $data->taxpayerTin,
            'vat_number' => $data->vatNumber,
            'environment' => $data->environment,
            'api_base_url' => $data->apiBaseUrl,
            'status' => 'registering',
            'is_active' => false,
        ]));

        $csr = $this->driver->generateCsr($device);
        $device->update(['csr_generated_at' => Carbon::now(), 'private_key_ref' => $csr->privateKeyRef]);

        $registration = $this->driver->registerDevice($device);

        $device->update([
            'certificate_pem' => $registration->certificatePem,
            'certificate_issued_at' => $registration->certificateIssuedAt,
            'certificate_expires_at' => $registration->certificateExpiresAt,
            'applicable_taxes' => $registration->applicableTaxes,
            'taxpayer_day_max_hours' => $registration->taxpayerDayMaxHours,
            'operating_mode' => $registration->operatingMode,
            'last_config_sync_at' => Carbon::now(),
            'status' => 'active',
            'is_active' => true,
        ]);

        event(new FiscalDeviceRegistered($device->fresh()));

        return $device->fresh();
    }
}

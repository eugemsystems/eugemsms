<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Support;

use Illuminate\Support\Carbon;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\DataObjects\Gateway\CsrResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\DeviceRegistrationResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FdmsDayResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FdmsReceiptResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FiscalHealthStatus;
use Modules\Fiscal\Domain\Exceptions\FdmsUnreachableException;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * The only `FiscalGatewayDriver` implementation in this pass — bound
 * directly in `FiscalServiceProvider::register()` (unlike
 * `Modules\Finance`'s `PaymentGatewayDriverRegistry`, a per-key
 * registry isn't warranted here: ZIMRA FDMS is the one fiscalisation
 * authority a Zimbabwean school integrates with, not a choice of
 * competing gateways). A real ZIMRA driver needs live sandbox
 * credentials and a documented API this build has neither of —
 * fabricating one against unverifiable behaviour would be worse than
 * not building it. Swapping in a real driver later is one line in
 * that `register()` call — nothing here changes shape.
 *
 * Deterministic and inspectable rather than a mock, mirroring
 * `Modules\Finance\Domain\Support\FakePaymentGatewayDriver`'s own
 * idiom exactly. Two independent controls, both a test sets directly:
 * `$device->last_ping_status = 'offline'` simulates FDMS being
 * unreachable for EVERY driver call against that device (`openDay`,
 * `closeDay`, `ping`, `submitReceipt`) — the realistic "the internet
 * is down" scenario. `$payload['_simulate']` (set via
 * `RouteReceiptForFiscalisationData::$simulate`) controls a single
 * receipt submission specifically: `'unreachable'` throws
 * `FdmsUnreachableException` same as the device-level flag;
 * `'reject'` returns a normal-but-rejected `FdmsReceiptResult` (FDMS
 * reached, receipt refused — a different failure mode entirely);
 * anything else, including no key at all, accepts deterministically.
 */
final class FakeFiscalGatewayDriver implements FiscalGatewayDriver
{
    public function key(): string
    {
        return 'fake';
    }

    public function generateCsr(FiscalDevice $device): CsrResult
    {
        return new CsrResult(
            csrContent: "-----BEGIN CERTIFICATE REQUEST-----\nFAKE-CSR-{$device->device_id}\n-----END CERTIFICATE REQUEST-----",
            privateKeyRef: "keystore://fiscal/{$device->school_id}/{$device->device_id}",
        );
    }

    public function registerDevice(FiscalDevice $device): DeviceRegistrationResult
    {
        return new DeviceRegistrationResult(
            certificatePem: "-----BEGIN CERTIFICATE-----\nFAKE-CERT-{$device->device_id}\n-----END CERTIFICATE-----",
            certificateIssuedAt: Carbon::now(),
            certificateExpiresAt: Carbon::now()->addYear(),
            applicableTaxes: ['standard', 'zero_rated', 'exempt'],
            taxpayerDayMaxHours: 24,
            operatingMode: 'online',
        );
    }

    public function openDay(FiscalDevice $device, int $fiscalDayNumber): FdmsDayResult
    {
        if ($this->shouldSimulateUnreachable($device->last_ping_status)) {
            throw new FdmsUnreachableException("FDMS unreachable opening fiscal day [{$fiscalDayNumber}] for device [{$device->device_id}].");
        }

        return new FdmsDayResult(success: true, fdmsStatus: 'FiscalDayOpened');
    }

    public function submitReceipt(FiscalDevice $device, array $payload): FdmsReceiptResult
    {
        $simulate = $payload['_simulate'] ?? 'accept';

        if ($simulate === 'unreachable' || $this->shouldSimulateUnreachable($device->last_ping_status)) {
            throw new FdmsUnreachableException("FDMS unreachable submitting receipt [{$payload['invoice_number']}].");
        }

        if ($simulate === 'reject') {
            return new FdmsReceiptResult(
                accepted: false,
                rawResponse: ['status' => 'rejected'],
                errorCode: 'FAKE_REJECTED',
                errorMessage: 'Simulated rejection.',
            );
        }

        $counter = (string) ($payload['receipt_counter'] ?? '0');

        return new FdmsReceiptResult(
            accepted: true,
            rawResponse: ['status' => 'accepted', 'receiptID' => "FAKE-RCPT-{$counter}"],
            fdmsReceiptId: "FAKE-RCPT-{$counter}",
            verificationCode: strtoupper(substr(hash('sha256', $device->device_id.$counter), 0, 16)),
            qrUrl: "https://fdms.zimra.co.zw/verify/{$device->device_id}/{$counter}",
            receiptHash: hash('sha256', json_encode($payload) ?: ''),
        );
    }

    public function closeDay(FiscalDevice $device, FiscalDay $day): FdmsDayResult
    {
        if ($this->shouldSimulateUnreachable($device->last_ping_status)) {
            throw new FdmsUnreachableException("FDMS unreachable closing fiscal day [{$day->fiscal_day_number}] for device [{$device->device_id}].");
        }

        return new FdmsDayResult(success: true, fdmsStatus: 'FiscalDayClosed');
    }

    public function submitZReport(FiscalDevice $device, array $payload): FdmsReceiptResult
    {
        return new FdmsReceiptResult(accepted: true, rawResponse: ['status' => 'accepted']);
    }

    public function ping(FiscalDevice $device): FiscalHealthStatus
    {
        return new FiscalHealthStatus(status: $this->shouldSimulateUnreachable($device->last_ping_status) ? 'offline' : 'online');
    }

    private function shouldSimulateUnreachable(?string $simulatedPingStatus): bool
    {
        return $simulatedPingStatus === 'offline';
    }
}

<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Support;

use Closure;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\DataObjects\Gateway\CsrResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\DeviceRegistrationResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FdmsDayResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FdmsReceiptResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FiscalHealthStatus;
use Modules\Fiscal\Domain\Exceptions\FdmsUnreachableException;
use Modules\Fiscal\Models\FiscalAuditLogEntry;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;
use Throwable;

/**
 * BR-FIN-13-012 — every request to and response from the FDMS gateway is
 * logged verbatim in the append-only `fiscal_audit_log`, so when ZIMRA
 * disputes what was sent the payload settles it. A decorator rather than six
 * edits: every gateway call, from every Action and every future driver, goes
 * through this one place and cannot skip the log.
 *
 * What is never logged: the private key reference and the certificate body
 * (neither is a request/response a dispute turns on). A failed log write never
 * fails or changes the gateway call — fiscalisation must not block the
 * operation it attaches to.
 */
final class AuditedFiscalGatewayDriver implements FiscalGatewayDriver
{
    public function __construct(
        private readonly FiscalGatewayDriver $inner,
    ) {}

    public function key(): string
    {
        return $this->inner->key();
    }

    public function generateCsr(FiscalDevice $device): CsrResult
    {
        return $this->logged($device, 'generate_csr', null, null, fn (): CsrResult => $this->inner->generateCsr($device), fn (CsrResult $r): array => ['csr_generated' => true]);
    }

    public function registerDevice(FiscalDevice $device): DeviceRegistrationResult
    {
        return $this->logged($device, 'register_device', null, ['device_serial' => $device->device_serial, 'taxpayer_tin' => $device->taxpayer_tin], fn (): DeviceRegistrationResult => $this->inner->registerDevice($device), fn (DeviceRegistrationResult $r): array => [
            'certificate_issued_at' => $r->certificateIssuedAt->toIso8601String(),
            'certificate_expires_at' => $r->certificateExpiresAt->toIso8601String(),
            'applicable_taxes' => $r->applicableTaxes,
            'operating_mode' => $r->operatingMode,
        ]);
    }

    public function openDay(FiscalDevice $device, int $fiscalDayNumber): FdmsDayResult
    {
        return $this->logged($device, 'open_day', (string) $fiscalDayNumber, ['fiscal_day_number' => $fiscalDayNumber], fn (): FdmsDayResult => $this->inner->openDay($device, $fiscalDayNumber), $this->dayResponse(...));
    }

    public function submitReceipt(FiscalDevice $device, array $payload): FdmsReceiptResult
    {
        return $this->logged($device, 'submit_receipt', isset($payload['invoice_number']) ? (string) $payload['invoice_number'] : null, $payload, fn (): FdmsReceiptResult => $this->inner->submitReceipt($device, $payload), $this->receiptResponse(...));
    }

    public function closeDay(FiscalDevice $device, FiscalDay $day): FdmsDayResult
    {
        return $this->logged($device, 'close_day', (string) $day->fiscal_day_number, ['fiscal_day_number' => $day->fiscal_day_number], fn (): FdmsDayResult => $this->inner->closeDay($device, $day), $this->dayResponse(...));
    }

    public function submitZReport(FiscalDevice $device, array $payload): FdmsReceiptResult
    {
        return $this->logged($device, 'submit_z_report', isset($payload['fiscal_day_number']) ? (string) $payload['fiscal_day_number'] : null, $payload, fn (): FdmsReceiptResult => $this->inner->submitZReport($device, $payload), $this->receiptResponse(...));
    }

    public function ping(FiscalDevice $device): FiscalHealthStatus
    {
        return $this->logged($device, 'ping', null, null, fn (): FiscalHealthStatus => $this->inner->ping($device), fn (FiscalHealthStatus $r): array => ['status' => $r->status, 'message' => $r->message]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dayResponse(FdmsDayResult $result): array
    {
        return ['success' => $result->success, 'fdms_status' => $result->fdmsStatus, 'error_message' => $result->errorMessage];
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptResponse(FdmsReceiptResult $result): array
    {
        return [
            'accepted' => $result->accepted,
            'raw_response' => $result->rawResponse,
            'fdms_receipt_id' => $result->fdmsReceiptId,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
        ];
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>|null  $request
     * @param  Closure(): T  $call
     * @param  Closure(T): array<string, mixed>  $describe
     * @return T
     */
    private function logged(FiscalDevice $device, string $eventType, ?string $reference, ?array $request, Closure $call, Closure $describe): mixed
    {
        $startedAt = hrtime(true);

        try {
            $result = $call();
        } catch (FdmsUnreachableException $exception) {
            $this->record($device, $eventType, $reference, $request, ['unreachable' => true, 'message' => $exception->getMessage()], $startedAt);

            throw $exception;
        }

        $this->record($device, $eventType, $reference, $request, $describe($result), $startedAt);

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>  $response
     */
    private function record(FiscalDevice $device, string $eventType, ?string $reference, ?array $request, array $response, int $startedAt): void
    {
        try {
            FiscalAuditLogEntry::create([
                'school_id' => $device->school_id,
                'device_id' => $device->id,
                'event_type' => $eventType,
                'reference' => $reference,
                'request_payload' => $request,
                'response_payload' => $response,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // The gateway call already happened; losing its log line must not undo or fail it.
        }
    }
}

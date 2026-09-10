<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Contracts;

use Modules\Fiscal\Domain\DataObjects\Gateway\CsrResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\DeviceRegistrationResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FdmsDayResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FdmsReceiptResult;
use Modules\Fiscal\Domain\DataObjects\Gateway\FiscalHealthStatus;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * Book H3 FIN-13 §2 — "treat the ZIMRA integration as an isolated
 * adapter behind an interface". The literal FDMS API contract — see
 * `FakeFiscalGatewayDriver`, the only implementation registered, and
 * `FiscalGatewayDriverRegistry`'s own docblock for why a real ZIMRA
 * driver needs sandbox credentials this pass does not have.
 */
interface FiscalGatewayDriver
{
    public function key(): string;

    public function generateCsr(FiscalDevice $device): CsrResult;

    public function registerDevice(FiscalDevice $device): DeviceRegistrationResult;

    public function openDay(FiscalDevice $device, int $fiscalDayNumber): FdmsDayResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitReceipt(FiscalDevice $device, array $payload): FdmsReceiptResult;

    public function closeDay(FiscalDevice $device, FiscalDay $day): FdmsDayResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitZReport(FiscalDevice $device, array $payload): FdmsReceiptResult;

    public function ping(FiscalDevice $device): FiscalHealthStatus;
}

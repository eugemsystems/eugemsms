<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\DataObjects\CloseFiscalDayData;
use Modules\Fiscal\Domain\Events\FiscalDayClosed;
use Modules\Fiscal\Domain\Events\FiscalDayCloseFailed;
use Modules\Fiscal\Domain\Exceptions\FdmsUnreachableException;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * ACT-CloseFiscalDay (Book H3 FIN-13 §5/BR-FIN-13-007/008 ⭐). Unlike
 * opening, closing genuinely waits on FDMS: `local_status` moves to
 * `closed` ONLY on driver confirmation. A failed attempt leaves the
 * day `close_pending`, increments `close_attempts`, records the
 * error, and fires `FiscalDayCloseFailed` — it never silently
 * reopens, and a caller retries by calling this action again.
 */
final class CloseFiscalDayAction extends Action
{
    public function __construct(
        private readonly FiscalGatewayDriver $driver,
    ) {}

    public function execute(CloseFiscalDayData $data): FiscalDay
    {
        $day = FiscalDay::findOrFail($data->fiscalDayId);

        if ($day->local_status === 'closed') {
            throw new InvalidStateTransitionException("Fiscal day #{$day->id} is already closed.", ['fiscal_day_id' => $day->id]);
        }

        $device = FiscalDevice::findOrFail($day->device_id);

        $day = $this->transaction(fn (): FiscalDay => tap($day)->update([
            'local_status' => 'close_pending',
            'close_attempts' => $day->close_attempts + 1,
        ]));

        try {
            $result = $this->driver->closeDay($device, $day);

            $day = $this->transaction(fn (): FiscalDay => tap($day)->update([
                'local_status' => 'closed',
                'fdms_status' => $result->fdmsStatus,
                'closed_at' => Carbon::now(),
                'closed_by' => $data->closedByUserId,
                'close_error' => null,
            ]));

            event(new FiscalDayClosed($day->fresh()));
        } catch (FdmsUnreachableException $e) {
            $day = $this->transaction(fn (): FiscalDay => tap($day)->update([
                'local_status' => 'close_failed',
                'close_error' => $e->getMessage(),
            ]));

            event(new FiscalDayCloseFailed($day->fresh(), $e->getMessage()));
        }

        return $day->fresh();
    }
}

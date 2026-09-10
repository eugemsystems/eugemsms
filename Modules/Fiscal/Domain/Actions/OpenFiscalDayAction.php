<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\DataObjects\OpenFiscalDayData;
use Modules\Fiscal\Domain\Events\FiscalDayOpened;
use Modules\Fiscal\Domain\Exceptions\FdmsUnreachableException;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * ACT-OpenFiscalDay (Book H3 FIN-13 §5/BR-FIN-13-006). The local row
 * opens regardless of FDMS reachability — the same cardinal rule as
 * receipting itself: a school can't wait on FDMS to start trading.
 * `fdms_status` records the confirmation attempt separately and stays
 * null when FDMS couldn't be reached; it is not a precondition for
 * `RouteReceiptForFiscalisationAction` to use this day.
 */
final class OpenFiscalDayAction extends Action
{
    public function __construct(
        private readonly FiscalGatewayDriver $driver,
    ) {}

    public function execute(OpenFiscalDayData $data): FiscalDay
    {
        $device = FiscalDevice::findOrFail($data->deviceId);

        $nextNumber = (int) FiscalDay::where('device_id', $device->id)->max('fiscal_day_number') + 1;

        $day = $this->transaction(fn (): FiscalDay => FiscalDay::create([
            'school_id' => $device->school_id,
            'device_id' => $device->id,
            'fiscal_day_number' => $nextNumber,
            'opened_at' => Carbon::now(),
            'opened_by' => $data->openedByUserId,
            'local_status' => 'open',
            'counters' => [],
        ]));

        try {
            $result = $this->driver->openDay($device, $nextNumber);
            $day->update(['fdms_status' => $result->fdmsStatus]);
        } catch (FdmsUnreachableException) {
            // Local day is already open and usable — FDMS confirmation
            // will be retried the same way a receipt submission is.
        }

        event(new FiscalDayOpened($day->fresh()));

        return $day->fresh();
    }
}

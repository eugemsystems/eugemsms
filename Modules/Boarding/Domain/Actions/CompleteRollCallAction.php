<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\CompleteRollCallData;
use Modules\Boarding\Models\RollCall;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CompleteRollCall (Book F BRD-02 §2).
 */
final class CompleteRollCallAction extends Action
{
    public function execute(CompleteRollCallData $data): RollCall
    {
        $rollCall = RollCall::findOrFail($data->rollCallId);

        return $this->transaction(fn (): RollCall => tap($rollCall)->update([
            'status' => 'completed',
            'completed_at' => Carbon::now(),
            'conducted_by' => $data->completedByUserId,
        ]));
    }
}

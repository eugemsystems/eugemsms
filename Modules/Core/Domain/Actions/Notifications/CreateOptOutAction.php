<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Notifications\CreateOptOutData;
use Modules\Core\Models\NotificationOptOut;

final class CreateOptOutAction extends Action
{
    public function execute(CreateOptOutData $data): NotificationOptOut
    {
        return $this->transaction(fn (): NotificationOptOut => NotificationOptOut::updateOrCreate(
            ['school_id' => $data->schoolId, 'address' => $data->address, 'channel' => $data->channel],
            ['reason' => $data->reason, 'opted_out_at' => Carbon::now()],
        ));
    }
}

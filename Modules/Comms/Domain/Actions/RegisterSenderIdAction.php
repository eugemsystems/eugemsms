<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\RegisterSenderIdData;
use Modules\Comms\Domain\Events\SenderIdRegistered;
use Modules\Comms\Models\SenderId;
use Modules\Core\Domain\Actions\Action;

final class RegisterSenderIdAction extends Action
{
    public function execute(RegisterSenderIdData $data): SenderId
    {
        return $this->transaction(function () use ($data): SenderId {
            $senderId = SenderId::create([
                'school_id' => $data->schoolId,
                'gateway_id' => $data->gatewayId,
                'sender_id' => $data->senderId,
                'network' => $data->network,
                'registration_reference' => $data->registrationReference,
                'status' => 'pending',
                'submitted_at' => Carbon::now(),
            ]);

            event(new SenderIdRegistered($senderId));

            return $senderId;
        });
    }
}

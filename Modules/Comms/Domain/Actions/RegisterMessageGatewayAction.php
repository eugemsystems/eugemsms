<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\RegisterMessageGatewayData;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Domain\Actions\Action;

final class RegisterMessageGatewayAction extends Action
{
    public function execute(RegisterMessageGatewayData $data): MessageGateway
    {
        return $this->transaction(fn (): MessageGateway => MessageGateway::create([
            'school_id' => $data->schoolId,
            'channel' => $data->channel,
            'driver' => $data->driver,
            'name' => $data->name,
            'credentials' => $data->credentials,
            'webhook_secret' => $data->webhookSecret,
            'is_default' => $data->isDefault,
            'is_sandbox' => $data->isSandbox,
            'priority' => $data->priority,
            'is_active' => true,
            'created_by' => $data->createdByUserId,
        ]));
    }
}

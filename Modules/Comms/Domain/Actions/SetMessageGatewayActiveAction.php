<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\MessageGateway;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SetMessageGatewayActive (Book I COM-01 §2). An inactive gateway
 * is skipped by every driver's failover pick, exactly like a `down`
 * one — but never deleted, so its history and cost reconciliation rows
 * stay attached.
 */
final class SetMessageGatewayActiveAction extends Action
{
    public function execute(int $gatewayId, bool $isActive): MessageGateway
    {
        return $this->transaction(function () use ($gatewayId, $isActive): MessageGateway {
            $gateway = MessageGateway::findOrFail($gatewayId);
            $gateway->update(['is_active' => $isActive]);

            return $gateway;
        });
    }
}

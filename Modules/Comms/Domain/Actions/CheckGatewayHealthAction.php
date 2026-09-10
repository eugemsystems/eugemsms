<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Comms\Domain\Contracts\MessagingGatewayDriver;
use Modules\Comms\Domain\Events\GatewayHealthChanged;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Registry\NotificationChannelDriverRegistry;

/**
 * ACT-CheckGatewayHealth (Book I COM-01 §3/BR-COM-01-011). Runs on a
 * schedule (`comms.gateway_health_check_minutes`). A gateway marked
 * `down` is excluded from `resolveActiveGateway()`'s failover pick in
 * every driver until it recovers.
 */
final class CheckGatewayHealthAction extends Action
{
    /**
     * @return Collection<int, MessageGateway>
     */
    public function execute(int $schoolId): Collection
    {
        $gateways = MessageGateway::where('school_id', $schoolId)->where('is_active', true)->get();

        return $this->transaction(function () use ($gateways): Collection {
            return $gateways->map(function (MessageGateway $gateway): MessageGateway {
                $driver = NotificationChannelDriverRegistry::forChannel($gateway->channel);
                $status = $driver instanceof MessagingGatewayDriver ? $driver->healthCheck()->status : 'up';
                $previousStatus = $gateway->health_status ?? 'up';

                $gateway->update(['health_status' => $status, 'last_health_check_at' => Carbon::now()]);

                if ($status !== $previousStatus) {
                    event(new GatewayHealthChanged($gateway, $previousStatus));
                }

                return $gateway;
            });
        });
    }
}

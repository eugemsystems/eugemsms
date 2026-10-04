<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Models\PaymentGateway;

/**
 * ACT-CheckPaymentGatewayHealth (Book B FIN-05 §2/BR-FIN-05-017). The
 * spec's own `JOB-CheckGatewayHealth` schedule isn't wired (no job
 * infrastructure calls this on a timer yet — the same "real mechanism,
 * deferred schedule" boundary as `PollPendingIntentAction`'s own
 * docblock), but a manual "test connection" button needs exactly this
 * one call, and `PaymentGatewayDriver::healthCheck()` is a real,
 * already-implemented method on every registered driver.
 */
final class CheckPaymentGatewayHealthAction extends Action
{
    public function __construct(
        private readonly PaymentGatewayDriverRegistry $drivers,
    ) {}

    public function execute(int $gatewayId): PaymentGateway
    {
        $gateway = PaymentGateway::findOrFail($gatewayId);
        $driver = $this->drivers->resolve($gateway->driver);
        $result = $driver->healthCheck();

        return $this->transaction(function () use ($gateway, $result): PaymentGateway {
            $gateway->update([
                'health_status' => $result->status,
                'last_health_check_at' => Carbon::now(),
            ]);

            return $gateway->fresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Modules\Finance\Domain\Contracts\PaymentGatewayDriver;
use Modules\Finance\Domain\Exceptions\UnregisteredGatewayDriverException;

/**
 * Book B FIN-05 §4. Resolves a `payment_gateways.driver` key to its
 * driver instance. Only `FakePaymentGatewayDriver` is registered in
 * this pass — `ContiPay`/`Pesepay`/`Paynow`/`SmilePay` each need real
 * sandbox credentials and a live HTTP integration this build has
 * neither of; fabricating one against undocumented, unverifiable
 * behaviour would be worse than not building it. Wiring a real driver
 * later is exactly `FinanceServiceProvider::registerPaymentGatewayDrivers()`
 * gaining one more `register()` call — nothing here changes shape.
 */
final class PaymentGatewayDriverRegistry
{
    /**
     * @var array<string, PaymentGatewayDriver>
     */
    private array $drivers = [];

    public function register(PaymentGatewayDriver $driver): void
    {
        $this->drivers[$driver->key()] = $driver;
    }

    public function resolve(string $key): PaymentGatewayDriver
    {
        return $this->drivers[$key] ?? throw UnregisteredGatewayDriverException::forKey($key);
    }

    public function has(string $key): bool
    {
        return isset($this->drivers[$key]);
    }
}

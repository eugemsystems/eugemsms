<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\RegisterPaymentGatewayData;
use Modules\Finance\Models\PaymentGateway;

/**
 * ACT-RegisterPaymentGateway (Book B FIN-05 §3/BR-FIN-05-016).
 * `credentials` is encrypted by the model's own cast — this Action
 * never logs or echoes it back.
 */
final class RegisterPaymentGatewayAction extends Action
{
    public function execute(RegisterPaymentGatewayData $data): PaymentGateway
    {
        return $this->transaction(fn (): PaymentGateway => PaymentGateway::create([
            'school_id' => $data->schoolId,
            'driver' => $data->driver,
            'name' => $data->name,
            'credentials' => $data->credentials,
            'supported_methods' => $data->supportedMethods,
            'supported_currencies' => $data->supportedCurrencies,
            'settlement_account_id' => $data->settlementAccountId,
            'fee_account_id' => $data->feeAccountId,
            'fee_model' => $data->feeModel,
            'is_default' => $data->isDefault,
            'is_sandbox' => $data->isSandbox,
            'is_active' => $data->isActive,
        ]));
    }
}

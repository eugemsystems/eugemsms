<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\UpdatePaymentGatewayData;
use Modules\Finance\Models\PaymentGateway;

/**
 * ACT-UpdatePaymentGateway (Book B FIN-05 §3/§8, `finance.gateway.manage`).
 * `RegisterPaymentGatewayAction` only ever creates — the admin UI needs
 * to edit/toggle/rotate credentials on an existing gateway, which had
 * no Action behind it at all. Credentials are only rotated when a new
 * value is supplied; leaving the field blank keeps the existing
 * (still-encrypted) value rather than overwriting it with an empty
 * string (BR-FIN-05-016).
 *
 * At most one gateway is `is_default` per school — setting this one
 * clears the flag on every other gateway for the same school in the
 * same transaction.
 */
final class UpdatePaymentGatewayAction extends Action
{
    public function execute(UpdatePaymentGatewayData $data): PaymentGateway
    {
        $gateway = PaymentGateway::findOrFail($data->gatewayId);

        return $this->transaction(function () use ($gateway, $data): PaymentGateway {
            if ($data->isDefault) {
                PaymentGateway::where('school_id', $gateway->school_id)->where('id', '!=', $gateway->id)->update(['is_default' => false]);
            }

            $gateway->update([
                'name' => $data->name,
                'supported_methods' => $data->supportedMethods,
                'supported_currencies' => $data->supportedCurrencies,
                'settlement_account_id' => $data->settlementAccountId,
                'fee_account_id' => $data->feeAccountId,
                'fee_model' => $data->feeModel,
                'is_default' => $data->isDefault,
                'is_sandbox' => $data->isSandbox,
                'is_active' => $data->isActive,
                'priority' => $data->priority,
                ...($data->credentials !== null ? ['credentials' => $data->credentials] : []),
            ]);

            return $gateway->fresh();
        });
    }
}

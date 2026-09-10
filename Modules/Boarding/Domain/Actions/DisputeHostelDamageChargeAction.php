<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\DisputeHostelDamageChargeData;
use Modules\Boarding\Models\HostelDamage;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-DisputeHostelDamageCharge (Book F BRD-01 §4/BR-BRD-01-013/
 * AC-BRD-01-006). A disputed charge halts billing — this action only
 * ever moves a `pending` or `charged` damage to `disputed`; it never
 * raises or reverses a `FIN-02` charge itself. Resolving the dispute
 * (returning to `pending` or on to `charged`/`waived`) is a separate,
 * explicit step recorded the same way.
 */
final class DisputeHostelDamageChargeAction extends Action
{
    public function execute(DisputeHostelDamageChargeData $data): HostelDamage
    {
        $damage = HostelDamage::findOrFail($data->damageId);

        return $this->transaction(fn (): HostelDamage => tap($damage)->update([
            'charge_status' => 'disputed',
        ]));
    }
}

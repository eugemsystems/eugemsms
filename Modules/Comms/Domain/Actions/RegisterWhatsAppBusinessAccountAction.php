<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\RegisterWhatsAppBusinessAccountData;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RegisterWhatsAppBusinessAccount (Book I COM-01 §2). The backend
 * pass had no create path for `whatsapp_business_accounts` — this is
 * the admin-UI pass's own gap-filling Action. A new account starts
 * `pending` with no quality rating; Meta's verification and rating
 * arrive later via `UpdateWhatsAppQualityRatingAction`.
 */
final class RegisterWhatsAppBusinessAccountAction extends Action
{
    public function execute(RegisterWhatsAppBusinessAccountData $data): WhatsAppBusinessAccount
    {
        return $this->transaction(fn (): WhatsAppBusinessAccount => WhatsAppBusinessAccount::create([
            'school_id' => $data->schoolId,
            'gateway_id' => $data->gatewayId,
            'waba_id' => $data->wabaId,
            'display_phone_number' => $data->displayPhoneNumber,
            'display_name' => $data->displayName,
            'status' => 'pending',
        ]));
    }
}

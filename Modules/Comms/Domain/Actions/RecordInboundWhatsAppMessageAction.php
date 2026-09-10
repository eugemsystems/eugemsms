<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\MessageConversation;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordInboundWhatsAppMessage (Book I COM-01 §3/BR-COM-01-004).
 * `session_expires_at` extends to `now() + 24h` on every inbound
 * message from the contact — the only thing that ever extends it;
 * outbound sends never touch it.
 */
final class RecordInboundWhatsAppMessageAction extends Action
{
    public function execute(int $schoolId, int $wabaId, string $contactPhone): MessageConversation
    {
        return $this->transaction(function () use ($schoolId, $wabaId, $contactPhone): MessageConversation {
            $now = Carbon::now();

            return MessageConversation::updateOrCreate(
                ['school_id' => $schoolId, 'waba_id' => $wabaId, 'contact_phone' => $contactPhone],
                ['last_inbound_at' => $now, 'session_expires_at' => $now->copy()->addHours(24)],
            );
        });
    }
}

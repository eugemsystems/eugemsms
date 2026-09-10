<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Auth;

/**
 * Book A CORE-05 §4. Delivers an OTP code by SMS/WhatsApp. The real
 * gateway integration belongs to a messaging module that doesn't exist
 * yet (COM-02, Book I) — `NullOtpDeliveryChannel` logs instead of
 * sending, same deferred-dependency shape as the other Null* providers
 * in this codebase.
 */
interface OtpDeliveryChannel
{
    public function send(string $phoneE164, string $code): void;
}

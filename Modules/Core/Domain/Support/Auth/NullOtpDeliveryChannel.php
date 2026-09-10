<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use Illuminate\Support\Facades\Log;
use Modules\Core\Domain\Contracts\Auth\OtpDeliveryChannel;

final class NullOtpDeliveryChannel implements OtpDeliveryChannel
{
    public function send(string $phoneE164, string $code): void
    {
        Log::info('OTP delivery is a no-op until a messaging module is installed.', [
            'phone' => $phoneE164,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * Book J SAA-01 §3 ⭐ — a `past_due`/`grace` tenant's write was refused.
 * Renders 402 Payment Required, always carrying a direct link to
 * resolve payment (BR-SAA-01-005) rather than a generic error.
 */
final class SubscriptionPastDueException extends SerpException
{
    public function __construct(string $paymentPortalUrl)
    {
        parent::__construct(
            'Payment is overdue. Contact your account manager to restore full access.',
            ['payment_portal_url' => $paymentPortalUrl],
        );
    }

    public function errorCode(): string
    {
        return 'SUBSCRIPTION_PAST_DUE';
    }

    public function httpStatus(): int
    {
        return 402;
    }
}

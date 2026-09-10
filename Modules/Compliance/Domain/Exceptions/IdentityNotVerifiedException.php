<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 CMP-03 §3/BR-CMP-03-008 ⭐ (AC-CMP-03-002). No disclosure
 * compiles or fulfils before the requester's identity is verified.
 */
final class IdentityNotVerifiedException extends DomainException
{
    public static function forRequest(int $requestId): self
    {
        return new self(
            "Subject access request #{$requestId} cannot be fulfilled: the requester's identity has not been verified.",
            ['request_id' => $requestId],
        );
    }

    public function errorCode(): string
    {
        return 'IDENTITY_NOT_VERIFIED';
    }
}

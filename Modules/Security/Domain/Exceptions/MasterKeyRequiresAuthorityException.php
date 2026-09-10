<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H2 OPS-06 §4/BR-OPS-06-006.
 */
class MasterKeyRequiresAuthorityException extends DomainException
{
    public static function forKey(int $keyId): self
    {
        return new self(
            "Key/card #{$keyId} is a master and requires higher authority to issue (BR-OPS-06-006).",
            ['key_id' => $keyId],
        );
    }

    public function errorCode(): string
    {
        return 'MASTER_KEY_REQUIRES_AUTHORITY';
    }
}

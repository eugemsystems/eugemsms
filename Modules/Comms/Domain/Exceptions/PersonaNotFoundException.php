<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class PersonaNotFoundException extends DomainException
{
    public static function forUser(int $userId, string $persona): self
    {
        return new self(
            "User #{$userId} has no linked {$persona} record for this school.",
            ['user_id' => $userId, 'persona' => $persona],
        );
    }

    public function errorCode(): string
    {
        return 'PERSONA_NOT_FOUND';
    }
}

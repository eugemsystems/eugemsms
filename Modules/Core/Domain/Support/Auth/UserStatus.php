<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

/**
 * Book A CORE-05 §2 (`users.status`).
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Locked = 'locked';
    case Pending = 'pending';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}

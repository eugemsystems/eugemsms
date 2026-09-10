<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-01 §4/BR-BRD-01-009/AC-BRD-01-007. A room's occupants
 * must be reallocated before it can be marked out of service.
 */
class RoomOccupiedException extends DomainException
{
    public static function forRoom(int $roomId, int $occupantCount): self
    {
        return new self(
            "Room #{$roomId} has {$occupantCount} occupant(s) and must be vacated before it can be marked out of service.",
            ['room_id' => $roomId, 'occupant_count' => $occupantCount],
        );
    }

    public function errorCode(): string
    {
        return 'ROOM_OCCUPIED';
    }
}

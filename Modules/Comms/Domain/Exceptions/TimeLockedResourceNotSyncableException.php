<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-03 §4 ⭐ (AC-COM-03-005). No offline sync mechanism may
 * pre-fetch a time-locked resource before its release moment — a hard
 * constraint carried forward from `ACA-07`, not a convenience setting.
 */
final class TimeLockedResourceNotSyncableException extends DomainException
{
    public static function forCategory(string $category): self
    {
        return new self(
            "'{$category}' is a time-locked resource and may never be pre-fetched for offline use.",
            ['data_category' => $category],
        );
    }

    public function errorCode(): string
    {
        return 'TIME_LOCKED_RESOURCE_NOT_SYNCABLE';
    }
}

<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

class UnknownExitChecklistItemException extends DomainException
{
    public static function forCode(string $code, int $checklistId): self
    {
        return new self(
            "Exit checklist [{$checklistId}] has no item coded [{$code}].",
            ['checklist_id' => $checklistId, 'code' => $code],
        );
    }

    public function errorCode(): string
    {
        return 'UNKNOWN_EXIT_CHECKLIST_ITEM';
    }
}

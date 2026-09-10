<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-021 (AC-PPL-04-008). "Release is blocked
 * with the outstanding items named."
 */
class ExitClearanceIncompleteException extends DomainException
{
    /**
     * @param  array<int, string>  $outstandingItems
     */
    public static function forItems(int $checklistId, array $outstandingItems): self
    {
        $list = implode(', ', $outstandingItems);

        return new self(
            "Final pay cannot be released — outstanding clearance items: {$list}.",
            ['checklist_id' => $checklistId, 'outstanding_items' => $outstandingItems],
        );
    }

    public function errorCode(): string
    {
        return 'EXIT_CLEARANCE_INCOMPLETE';
    }
}

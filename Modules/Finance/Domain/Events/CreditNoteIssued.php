<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\CreditNote;

final class CreditNoteIssued
{
    public function __construct(
        public readonly CreditNote $creditNote,
    ) {}
}

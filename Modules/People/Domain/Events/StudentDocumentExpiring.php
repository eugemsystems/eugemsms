<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\StudentDocument;

/**
 * Book C PPL-01 §7/BR-PPL-01-021.
 */
final class StudentDocumentExpiring
{
    public function __construct(
        public readonly StudentDocument $document,
        public readonly int $daysRemaining,
    ) {}
}

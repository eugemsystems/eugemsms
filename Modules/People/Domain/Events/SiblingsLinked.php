<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Student;

/**
 * Book C PPL-01 §5/BR-PPL-01-018. Sibling links changed; sibling discounts for
 * both learners are worth re-evaluating.
 */
final class SiblingsLinked
{
    public function __construct(
        public readonly Student $student,
        public readonly Student $sibling,
        public readonly bool $linked,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class BulkEnrolmentOutcome
{
    public function __construct(
        public int $studentId,
        public bool $enrolled,
        public ?string $message = null,
    ) {}
}

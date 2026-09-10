<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class RecordStatutoryReturnSubmissionData
{
    public function __construct(
        public int $statutoryReturnId,
        public string $submissionReference,
    ) {}
}

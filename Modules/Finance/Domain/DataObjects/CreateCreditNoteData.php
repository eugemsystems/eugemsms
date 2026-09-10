<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateCreditNoteData
{
    /**
     * @param  array<int, array{component_id: int, description: string, amount_minor: int, invoice_line_id?: int|null}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public string $reasonCode,
        public string $reason,
        public string $currency,
        public array $lines,
        public int $raisedByUserId,
        public ?int $invoiceId = null,
        public ?int $approvedByUserId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Audit;

use Modules\Core\Domain\Support\Money;

final readonly class RecordFinancialAuditEntryData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $schoolId,
        public string $eventType,
        public string $subjectType,
        public int $subjectId,
        public int $causerId,
        public int $academicYearId,
        public array $payload,
        public ?Money $amount = null,
        public ?int $termId = null,
        public ?int $impersonatorId = null,
        public ?string $ip = null,
    ) {}
}

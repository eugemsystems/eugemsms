<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Audit;

final readonly class RecordActivityData
{
    /**
     * @param  array{old?: array<string, mixed>, attributes?: array<string, mixed>}|null  $properties
     */
    public function __construct(
        public string $logName,
        public string $description,
        public ?int $schoolId = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?string $causerType = null,
        public ?int $causerId = null,
        public ?string $event = null,
        public ?array $properties = null,
        public ?string $batchUuid = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $requestId = null,
        public ?int $impersonatorId = null,
        public ?int $academicYearId = null,
        public ?int $termId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

final readonly class CreateReportScheduleData
{
    /**
     * @param  array<int, mixed>  $recipients
     * @param  array<string, mixed>|null  $parameters
     */
    public function __construct(
        public int $schoolId,
        public int $reportDefinitionId,
        public string $name,
        public string $frequency,
        public array $recipients,
        public string $format,
        public ?array $parameters = null,
    ) {}
}

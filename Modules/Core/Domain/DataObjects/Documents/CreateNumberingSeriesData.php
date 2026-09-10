<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class CreateNumberingSeriesData
{
    public function __construct(
        public int $schoolId,
        public string $documentType,
        public string $pattern,
        public ?int $academicYearId = null,
        public ?int $termId = null,
        public ?string $prefix = null,
        public int $sequencePadding = 6,
        public string $resetPolicy = 'never',
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Backups;

final readonly class CreateSchoolExportData
{
    public function __construct(
        public int $schoolId,
        public ?int $requestedByUserId = null,
    ) {}
}

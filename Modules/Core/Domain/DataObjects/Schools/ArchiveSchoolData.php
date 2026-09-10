<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class ArchiveSchoolData
{
    public function __construct(
        public int $schoolId,
        public int $actingUserId,
        public string $reason,
    ) {}
}

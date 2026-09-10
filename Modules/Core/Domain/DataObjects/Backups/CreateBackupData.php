<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Backups;

final readonly class CreateBackupData
{
    /**
     * @param  string  $type  database|files|full|school_export
     * @param  string  $triggeredBy  schedule|manual|pre_upgrade
     */
    public function __construct(
        public string $type,
        public string $triggeredBy,
        public ?int $schoolId = null,
        public ?int $createdByUserId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreatePrivacyNoticeData
{
    public function __construct(
        public int $schoolId,
        public string $version,
        public string $title,
        public string $content,
        public string $effectiveFrom,
        public int $createdByUserId,
        public bool $requiresReconsent = false,
    ) {}
}

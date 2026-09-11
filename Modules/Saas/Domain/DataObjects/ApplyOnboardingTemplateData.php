<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class ApplyOnboardingTemplateData
{
    public function __construct(
        public int $templateId,
        public int $targetSchoolId,
        public int $actingUserId,
        public bool $overwriteSettings = false,
        public bool $overwriteCustomFields = false,
    ) {}
}

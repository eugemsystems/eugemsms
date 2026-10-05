<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateNewsletterData
{
    public function __construct(
        public int $schoolId,
        public string $issueNumber,
        public string $title,
        public string $contentHtml,
        public string $audienceScope = 'whole_school',
        public ?CarbonInterface $scheduledFor = null,
    ) {}
}

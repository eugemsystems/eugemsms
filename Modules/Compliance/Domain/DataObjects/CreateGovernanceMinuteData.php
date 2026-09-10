<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreateGovernanceMinuteData
{
    /**
     * @param  array<int, string>  $attendees
     * @param  array<int, string>|null  $resolutions
     * @param  array<int, int>|null  $accessRoleIds
     */
    public function __construct(
        public int $schoolId,
        public string $body,
        public string $meetingDate,
        public array $attendees,
        public string $confidentiality = 'open',
        public ?array $resolutions = null,
        public ?int $minutesFileId = null,
        public ?array $accessRoleIds = null,
    ) {}
}

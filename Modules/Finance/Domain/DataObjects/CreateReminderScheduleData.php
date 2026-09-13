<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateReminderScheduleData
{
    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public int $schoolId,
        public string $name,
        public int $daysAfterDue,
        public array $channels,
        public string $templateKey,
        public string $audience,
        public int $minimumBalanceMinor = 0,
        public ?string $currency = null,
        public ?int $escalateToRoleId = null,
    ) {}
}

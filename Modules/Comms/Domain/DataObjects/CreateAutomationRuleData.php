<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class CreateAutomationRuleData
{
    /**
     * @param  array<int, array{group_id: int, field: string, operator: string, value: mixed}>  $conditions
     * @param  array<int, string>|null  $channelOverride
     */
    public function __construct(
        public int $schoolId,
        public string $name,
        public string $notificationKey,
        public string $triggerType,
        public array $conditions,
        public int $createdByUserId,
        public ?string $eventName = null,
        public ?string $scheduleCron = null,
        public ?string $scanEntity = null,
        public ?string $audienceOverride = null,
        public ?array $channelOverride = null,
        public ?string $templateKeyOverride = null,
        public int $delayMinutes = 0,
        public ?string $throttleKey = null,
        public ?int $throttleWindowHours = null,
    ) {}
}

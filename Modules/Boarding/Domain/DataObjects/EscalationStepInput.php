<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class EscalationStepInput
{
    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public int $stepNumber,
        public int $delayMinutes,
        public array $channels,
        public string $messageTemplateKey,
        public ?int $notifyRoleId = null,
        public ?int $notifyStaffId = null,
        public bool $notifyGuardians = false,
        public bool $requiresAcknowledgement = true,
        public bool $requiresActionRecord = false,
    ) {}
}

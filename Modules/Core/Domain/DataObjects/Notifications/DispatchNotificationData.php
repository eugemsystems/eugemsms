<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class DispatchNotificationData
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $addresses  channel => address (e.g. ['sms' => '+263771234567', 'email' => 'parent@example.com']) — channel fallback tries each channel with its OWN address from here, never reuses one channel's address on another
     */
    public function __construct(
        public int $schoolId,
        public string $notificationKey,
        public string $recipientType,
        public array $addresses,
        public array $context = [],
        public ?int $recipientId = null,
        public ?string $channel = null,
        public ?string $relatedType = null,
        public ?int $relatedId = null,
        public ?bool $urgent = null,
        public int $dedupeWindowMinutes = 60,
        public string $locale = 'en_ZW',
    ) {}
}

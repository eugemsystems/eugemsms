<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class CreateNotificationTemplateData
{
    public function __construct(
        public string $key,
        public string $channel,
        public string $body,
        public ?int $schoolId = null,
        public string $locale = 'en_ZW',
        public ?string $subject = null,
        public ?string $providerTemplateId = null,
    ) {}
}

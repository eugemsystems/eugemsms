<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class SubmitWhatsAppTemplateData
{
    /**
     * @param  array<int, mixed>|null  $buttons
     */
    public function __construct(
        public int $schoolId,
        public int $wabaId,
        public string $metaTemplateName,
        public string $category,
        public string $language,
        public string $bodyText,
        public ?string $notificationKey = null,
        public ?string $headerType = 'none',
        public ?string $footerText = null,
        public ?array $buttons = null,
    ) {}
}

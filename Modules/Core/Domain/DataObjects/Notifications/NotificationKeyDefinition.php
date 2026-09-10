<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

/**
 * Book A CORE-09 BR-CORE-09-002. Every notification key a module wants
 * to dispatch is registered with exactly this shape before it can ever
 * be sent — an unregistered key throws.
 */
final readonly class NotificationKeyDefinition
{
    /**
     * @param  array<int, string>  $variables  dotted paths the body may reference
     * @param  array<int, string>  $defaultChannels  in fallback order
     */
    public function __construct(
        public string $key,
        public array $variables,
        public array $defaultChannels,
        public string $defaultAudience,
        public bool $isUrgent = false,
        public bool $isTransactional = false,
    ) {}
}

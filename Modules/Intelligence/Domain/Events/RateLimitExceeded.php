<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\ApiClient;

/**
 * Book J INT-04 §5/BR-INT-04-003. Dispatched by `AuthenticateApiClient` when a client
 * exceeds its `rate_limit_per_minute`.
 */
final class RateLimitExceeded
{
    public function __construct(
        public readonly ApiClient $client,
    ) {}
}

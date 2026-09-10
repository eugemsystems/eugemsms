<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\ApiClient;

/**
 * Book J INT-04 §5/BR-INT-04-003. Declared for the HTTP-layer rate
 * limiter to dispatch once it exists — this pass builds the domain
 * model (`api_clients.rate_limit_per_minute`) but not yet the actual
 * middleware enforcing it, consistent with this codebase's current
 * scope boundary of no general third-party REST controllers built yet.
 */
final class RateLimitExceeded
{
    public function __construct(
        public readonly ApiClient $client,
    ) {}
}

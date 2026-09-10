<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\ApiClient;

final class ApiClientRevoked
{
    public function __construct(
        public readonly ApiClient $client,
    ) {}
}

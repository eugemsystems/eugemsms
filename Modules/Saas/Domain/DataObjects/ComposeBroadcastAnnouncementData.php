<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

use Illuminate\Support\Carbon;

final readonly class ComposeBroadcastAnnouncementData
{
    /**
     * @param  array<int, int>|null  $targetTenantIds  null targets every tenant — always explicit, never inferred (BR-SAA-02-006)
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $severity,
        public int $postedBy,
        public ?array $targetTenantIds = null,
        public ?Carbon $startsAt = null,
        public ?Carbon $endsAt = null,
    ) {}
}

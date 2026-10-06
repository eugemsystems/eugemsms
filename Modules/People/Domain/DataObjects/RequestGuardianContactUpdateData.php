<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class RequestGuardianContactUpdateData
{
    /**
     * @param  array<string, string|null>  $changes  field => new value
     */
    public function __construct(
        public int $guardianId,
        public array $changes,
        public ?int $requestedByUserId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

final readonly class DeactivateCustomFieldData
{
    public function __construct(
        public int $definitionId,
        public ?int $actingUserId = null,
        public bool $deleteIfUnused = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

final readonly class CreateKeyAndCardData
{
    public function __construct(
        public int $schoolId,
        public string $identifier,
        public string $itemType,
        public string $description,
        public ?string $opensLocation = null,
        public bool $isMaster = false,
    ) {}
}

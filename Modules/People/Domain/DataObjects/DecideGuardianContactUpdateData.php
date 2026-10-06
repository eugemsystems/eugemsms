<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class DecideGuardianContactUpdateData
{
    public function __construct(
        public int $updateId,
        public int $decidedByUserId,
        public bool $approve,
        public ?string $note = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class LinkAccountData
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public string $linkedType,
        public int $linkedId,
    ) {}
}

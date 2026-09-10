<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class IssueTokenData
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public int $userId,
        public DeviceData $device,
        public array $abilities = ['*'],
        public ?int $schoolId = null,
        public ?string $ip = null,
    ) {}
}

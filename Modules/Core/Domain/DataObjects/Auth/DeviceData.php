<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class DeviceData
{
    public function __construct(
        public string $name,
        public ?string $id = null,
        public ?string $platform = null,
        public ?string $model = null,
        public ?string $appVersion = null,
    ) {}
}

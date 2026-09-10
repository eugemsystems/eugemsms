<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

use Modules\Core\Domain\Support\Settings\SettingScope;

final readonly class ResetSettingData
{
    public function __construct(
        public string $key,
        public SettingScope $scopeType,
        public int $scopeId,
        public ?int $performedByUserId = null,
        public ?string $ipAddress = null,
    ) {}
}

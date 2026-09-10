<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

use Modules\Core\Domain\Support\Settings\SettingScope;

final readonly class SetSettingValueData
{
    public function __construct(
        public string $key,
        public SettingScope $scopeType,
        public int $scopeId,
        public mixed $value,
        public ?int $setByUserId = null,
        public ?string $ipAddress = null,
    ) {}
}

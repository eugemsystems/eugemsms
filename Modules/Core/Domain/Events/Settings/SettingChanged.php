<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Settings;

use Modules\Core\Domain\Support\Settings\SettingScope;

final class SettingChanged
{
    public function __construct(
        public readonly string $key,
        public readonly SettingScope $scopeType,
        public readonly int $scopeId,
    ) {}
}

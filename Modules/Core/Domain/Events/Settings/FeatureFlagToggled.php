<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Settings;

use Modules\Core\Models\FeatureFlag;

final class FeatureFlagToggled
{
    public function __construct(
        public readonly FeatureFlag $flag,
        public readonly ?string $scopeType,
        public readonly ?int $scopeId,
        public readonly bool $isEnabled,
    ) {}
}

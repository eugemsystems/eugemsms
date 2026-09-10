<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Settings;

use Modules\Core\Models\CustomFieldDefinition;

final class CustomFieldDefined
{
    public function __construct(
        public readonly CustomFieldDefinition $definition,
    ) {}
}

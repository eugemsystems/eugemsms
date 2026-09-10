<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

final readonly class SetCustomFieldValueData
{
    public function __construct(
        public int $definitionId,
        public int $entityId,
        public mixed $value,
    ) {}
}

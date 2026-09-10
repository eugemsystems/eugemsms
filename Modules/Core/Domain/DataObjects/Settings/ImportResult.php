<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

final readonly class ImportResult
{
    public function __construct(
        public int $settingsImported,
        public int $settingsSkipped,
        public int $customFieldsImported,
        public int $customFieldsSkipped,
    ) {}
}

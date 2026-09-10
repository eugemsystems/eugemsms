<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class UpdateNumberingSeriesData
{
    public function __construct(
        public int $seriesId,
        public ?string $pattern = null,
        public ?string $prefix = null,
        public ?int $sequencePadding = null,
        public ?string $resetPolicy = null,
        public ?bool $isActive = null,
    ) {}
}

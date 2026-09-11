<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateLibraryItemData
{
    public function __construct(
        public int $schoolId,
        public string $title,
        public string $itemCategory,
        public ?string $isbn = null,
        public ?string $author = null,
        public ?string $publisher = null,
        public ?string $edition = null,
        public ?string $classification = null,
        public ?int $subjectId = null,
        public ?int $gradeLevelId = null,
        public ?int $replacementCostMinor = null,
        public ?string $currency = null,
        public ?int $coverImageFileId = null,
        public ?string $digitalResourceUrl = null,
    ) {}
}

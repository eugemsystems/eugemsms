<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class LinkSiblingsData
{
    public function __construct(
        public int $studentId,
        public int $siblingStudentId,
        public string $relationship,
        public int $linkedByUserId,
        public ?int $birthOrder = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SetTermResultCommentsData
{
    public function __construct(
        public int $termResultId,
        public ?string $classTeacherComment = null,
        public ?string $headComment = null,
    ) {}
}

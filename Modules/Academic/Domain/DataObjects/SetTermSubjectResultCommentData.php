<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SetTermSubjectResultCommentData
{
    public function __construct(
        public int $termSubjectResultId,
        public ?string $teacherComment = null,
        public ?int $teacherStaffId = null,
    ) {}
}

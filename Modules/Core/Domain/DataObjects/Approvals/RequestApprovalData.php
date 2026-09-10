<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Contracts\Approvals\Approvable;

final readonly class RequestApprovalData
{
    public function __construct(
        public Approvable&Model $approvable,
        public int $schoolId,
        public int $academicYearId,
        public int $requestedByUserId,
        public ?int $termId = null,
        public ?int $dueInHours = null,
    ) {}
}

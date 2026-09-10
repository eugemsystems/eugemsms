<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Approvals;

use Modules\Core\Models\ApprovalRequest;

final class ApprovalRejected
{
    public function __construct(
        public readonly ApprovalRequest $request,
    ) {}
}

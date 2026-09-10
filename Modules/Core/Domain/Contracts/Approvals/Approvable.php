<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Approvals;

use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\ApprovalRequest;

/**
 * Book A CORE-07 §3. Any model that can be routed through the
 * approvals engine implements this — CORE-07 itself owns none of what
 * gets approved, only the routing.
 */
interface Approvable
{
    public function approvableType(): string;

    public function approvalTitle(): string;

    public function approvalSummary(): ?string;

    public function approvalAmount(): ?Money;

    /**
     * @return array<string, mixed> rendered in the approval queue, and
     *                              the payload condition_rules match against
     */
    public function approvalPayload(): array;

    public function onApproved(ApprovalRequest $request): void;

    public function onRejected(ApprovalRequest $request): void;

    public function onReturned(ApprovalRequest $request): void;
}

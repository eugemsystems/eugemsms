<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Approvals;

use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Models\ApprovalChain;

/**
 * Book A CORE-07 BR-CORE-07-001/AC-CORE-07-001. Active chains for the
 * type are evaluated in `priority` order; the first whose
 * `condition_rules` match the approvable's payload applies. The
 * default chain (`is_default`) is the fallback when nothing else
 * matches, and is itself tried last regardless of its own priority
 * value.
 */
final class ApprovalChainSelector
{
    public function __construct(
        private readonly ConditionRuleMatcher $matcher,
    ) {}

    public function select(int $schoolId, Approvable $approvable): ApprovalChain
    {
        $payload = $approvable->approvalPayload();

        $chains = ApprovalChain::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('approvable_type', $approvable->approvableType())
            ->where('is_active', true)
            ->orderBy('is_default')
            ->orderBy('priority')
            ->get();

        foreach ($chains as $chain) {
            if (! $chain->is_default && $this->matcher->matches($chain->condition_rules, $payload)) {
                return $chain;
            }
        }

        $default = $chains->firstWhere('is_default', true);

        if ($default !== null) {
            return $default;
        }

        throw new class("No active approval chain is configured for [{$approvable->approvableType()}].") extends DomainException
        {
            public function errorCode(): string
            {
                return 'NO_APPROVAL_CHAIN';
            }
        };
    }
}

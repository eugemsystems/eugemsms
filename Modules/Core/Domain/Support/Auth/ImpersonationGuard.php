<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use Modules\Core\Domain\Exceptions\ImpersonationNotPermittedException;
use Modules\Core\Models\ImpersonationSession;

/**
 * Book A CORE-05 BR-CORE-05-018/AC-CORE-05-006. An impersonator can
 * never perform a financial mutation, change permissions, or export
 * bulk data — modules with those actions call `assertPermitted()` from
 * the Action itself once they exist; this is the shared check rather
 * than each module reinventing it.
 */
final class ImpersonationGuard
{
    public const string FINANCIAL_MUTATION = 'financial_mutation';

    public const string PERMISSION_CHANGE = 'permission_change';

    public const string BULK_EXPORT = 'bulk_export';

    /**
     * @var array<int, string>
     */
    private const array BLOCKED = [self::FINANCIAL_MUTATION, self::PERMISSION_CHANGE, self::BULK_EXPORT];

    public function assertPermitted(?ImpersonationSession $activeSession, string $category, string $description): void
    {
        if ($activeSession === null || ! $activeSession->isActive()) {
            return;
        }

        if (! in_array($category, self::BLOCKED, true)) {
            return;
        }

        $activeSession->recordBlockedAction($description);

        throw new ImpersonationNotPermittedException(
            "This action ({$description}) cannot be performed while impersonating another user.",
            ['category' => $category],
        );
    }
}

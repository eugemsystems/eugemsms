<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\TeachingGroupMember;

/**
 * Book D ACA-02 §9.
 */
final class TeachingGroupAssigned
{
    public function __construct(public readonly TeachingGroupMember $member) {}
}

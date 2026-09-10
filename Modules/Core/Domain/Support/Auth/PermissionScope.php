<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

/**
 * Book A CORE-05 §2/BR-CORE-05-015. Ordered narrowest→widest; widest
 * wins when a user holds the same permission at multiple scopes through
 * different roles.
 */
enum PermissionScope: string
{
    case Own = 'own';
    case Assigned = 'assigned';
    case Section = 'section';
    case School = 'school';

    /**
     * @return array<int, self>
     */
    public static function ascendingWidth(): array
    {
        return [self::Own, self::Assigned, self::Section, self::School];
    }

    public function isAtLeastAsWideAs(self $other): bool
    {
        $order = self::ascendingWidth();

        return array_search($this, $order, true) >= array_search($other, $order, true);
    }

    public static function widest(self $a, self $b): self
    {
        return $a->isAtLeastAsWideAs($b) ? $a : $b;
    }
}

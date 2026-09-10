<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Settings;

/**
 * Book A CORE-04 §3 — the levels `ScopeChain` walks, most specific first.
 * `Tier` isn't a literal database scope (nothing is ever *stored* against
 * it) — it's the resolution chain's second-to-last stop, checked via
 * `TenantTierProvider` for `is_locked_on_tier`, before the definition's
 * own system default.
 */
enum SettingScope: string
{
    case User = 'user';
    case Term = 'term';
    case AcademicYear = 'academic_year';
    case Section = 'section';
    case School = 'school';
    case Tenant = 'tenant';
    case System = 'system';

    /**
     * @return array<int, self> most specific first
     */
    public static function descendingSpecificity(): array
    {
        return [self::User, self::Term, self::AcademicYear, self::Section, self::School, self::Tenant];
    }

    public function isAtLeastAsGeneralAs(self $other): bool
    {
        return array_search($this, self::descendingSpecificity(), true)
            >= array_search($other, self::descendingSpecificity(), true);
    }
}

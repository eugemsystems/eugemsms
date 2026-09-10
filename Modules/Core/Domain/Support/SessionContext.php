<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Illuminate\Support\Facades\Facade;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * @method static AcademicYear year()
 * @method static Term|null term()
 * @method static int|null yearId()
 * @method static int|null termId()
 * @method static bool isCurrentLiveTerm()
 * @method static void set(AcademicYear $year, ?Term $term = null)
 * @method static void clear()
 * @method static bool isSet()
 *
 * @see SessionContextManager
 */
final class SessionContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SessionContextManager::class;
    }
}

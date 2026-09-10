<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Illuminate\Support\Facades\Facade;
use Modules\Core\Models\School;

/**
 * @method static School|null current()
 * @method static int|null currentId()
 * @method static void set(School $school)
 * @method static void clear()
 * @method static void assertSet()
 *
 * @see SchoolContextManager
 */
final class SchoolContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SchoolContextManager::class;
    }
}

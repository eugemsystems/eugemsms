<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Illuminate\Support\Facades\Facade;
use Modules\Core\Models\ImpersonationSession;

/**
 * @method static ImpersonationSession|null current()
 * @method static bool isActive()
 * @method static void set(ImpersonationSession $session)
 * @method static void clear()
 *
 * @see ImpersonationContextManager
 */
final class ImpersonationContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ImpersonationContextManager::class;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\AttendanceSession;

/**
 * Book D ACA-04 §7/BR-ACA-04-014.
 */
final class RegisterNotMarked
{
    public function __construct(public readonly AttendanceSession $session) {}
}

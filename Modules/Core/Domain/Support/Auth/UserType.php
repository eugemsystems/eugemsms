<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

/**
 * Book A CORE-05 §2 (`users.user_type`).
 */
enum UserType: string
{
    case Staff = 'staff';
    case Parent = 'parent';
    case Student = 'student';
    case Alumni = 'alumni';
    case Supplier = 'supplier';
    case Vendor = 'vendor';
}

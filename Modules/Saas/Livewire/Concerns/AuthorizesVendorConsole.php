<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Concerns;

use App\Models\User;
use Modules\Core\Http\Middleware\EnsureVendorGuard;

/**
 * Book J SAA-02 §3 ⭐/§0.2. Livewire's update requests do not re-run the
 * route's own middleware group, so every vendor-console component repeats
 * the console gate itself — in `mount()` and again in every mutating
 * method — by running the very same `EnsureVendorGuard` (vendor identity,
 * IP allowlist, confirmed 2FA). It checks who the user IS, never a
 * permission: no school role, however senior, passes.
 */
trait AuthorizesVendorConsole
{
    protected function authorizeVendor(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        (new EnsureVendorGuard)->handle(request(), fn () => response('', 204));

        return $user;
    }
}

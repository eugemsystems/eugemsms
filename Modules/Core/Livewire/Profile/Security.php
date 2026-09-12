<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Profile;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\ChangePasswordAction;
use Modules\Core\Domain\Actions\Auth\RevokeAllUserTokensAction;
use Modules\Core\Domain\DataObjects\Auth\ChangePasswordData;
use Modules\Core\Domain\Exceptions\InvalidCredentialsException;
use Modules\Core\Domain\Exceptions\WeakPasswordException;

/**
 * `Core\Profile\Security` (Book A CORE-05 §6, screen "own"). The
 * signed-in user's own security settings — no route parameters, always
 * acts on `Auth::user()`. Covers: (a) self-service password change via
 * `ChangePasswordAction`, never `ResetPasswordAction` (that's the
 * administrative reset-someone-else's-password path, a different
 * screen's concern); (b) a 2FA summary card that links out to
 * `Auth\TwoFactorSetup` — that screen already owns the full
 * enable/confirm/disable flow end to end and must not be duplicated
 * here; (c) "log out of all other sessions", which only revokes Sanctum
 * API/mobile tokens (`RevokeAllUserTokensAction`) — it does not and
 * cannot touch the current browser/web session, a distinction the view
 * spells out so it isn't confused with signing out.
 */
#[Title('My security')]
#[Layout('layouts.app')]
final class Security extends Component
{
    use Toasts;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        try {
            app(ChangePasswordAction::class)->execute(new ChangePasswordData(
                userId: (int) Auth::id(),
                currentPassword: $this->current_password,
                newPassword: $this->password,
            ));
        } catch (InvalidCredentialsException $e) {
            $this->reset('current_password');
            $this->addError('current_password', $e->getMessage());

            return;
        } catch (WeakPasswordException $e) {
            $this->addError('password', $e->getMessage());

            return;
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);

        $this->toast(__('Password changed.'));
    }

    /**
     * "Log out everywhere" for Sanctum devices only. Lives on this
     * screen rather than also being duplicated on `Core\Profile\Devices`
     * — Devices already offers the equivalent per-row revoke, and
     * Security is the natural single home for account-wide security
     * actions; a second identical button there would just be clutter.
     */
    public function logOutOtherDevices(): void
    {
        $count = app(RevokeAllUserTokensAction::class)->execute(Auth::user());

        $this->toast($count > 0
            ? __('Signed out of :count other device(s).', ['count' => $count])
            : __('No other devices were signed in.'));
    }

    public function render(): View
    {
        // A fresh query, not Auth::user() — see Auth\TwoFactorSetup's own
        // render() for why the guard's cached instance can lag behind a
        // just-completed write within the same request.
        $user = User::find(Auth::id());

        return view('core::profile.security', [
            'isTwoFactorEnabled' => $user?->two_factor_confirmed_at !== null,
        ]);
    }
}

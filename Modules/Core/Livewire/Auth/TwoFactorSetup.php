<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\ConfirmTwoFactorAction;
use Modules\Core\Domain\Actions\Auth\DisableTwoFactorAction;
use Modules\Core\Domain\Actions\Auth\EnableTwoFactorAction;
use Modules\Core\Domain\DataObjects\Auth\ConfirmTwoFactorData;
use Modules\Core\Domain\DataObjects\Auth\DisableTwoFactorData;
use Modules\Core\Domain\DataObjects\Auth\EnableTwoFactorData;
use Modules\Core\Domain\Exceptions\InvalidTwoFactorCodeException;
use Modules\Core\Domain\Support\Auth\TwoFactorRequirement;

/**
 * `Auth\TwoFactorSetup` (Book A CORE-05 §6). Enrolment + ongoing
 * management for the signed-in user's own 2FA — `EnsureTwoFactorIsEnrolled`
 * middleware routes a not-yet-enrolled, role-required user here and
 * nowhere else until `code` is confirmed.
 */
#[Title('Two-factor authentication')]
#[Layout('layouts.app')]
final class TwoFactorSetup extends Component
{
    use Toasts;

    public string $code = '';

    public bool $showRecoveryCodes = false;

    public function enable(): void
    {
        app(EnableTwoFactorAction::class)->execute(new EnableTwoFactorData(
            userId: (int) Auth::id(),
        ));
    }

    public function confirm(): void
    {
        try {
            app(ConfirmTwoFactorAction::class)->execute(new ConfirmTwoFactorData(
                userId: (int) Auth::id(),
                code: $this->code,
            ));
        } catch (InvalidTwoFactorCodeException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->reset('code');
        $this->showRecoveryCodes = true;

        $this->toast(__('Two-factor authentication enabled.'));
    }

    public function disable(): void
    {
        $user = Auth::user();

        if ($user !== null && app(TwoFactorRequirement::class)->isRequiredFor($user)) {
            $this->toast(__('Your role requires two-factor authentication — it cannot be disabled.'), 'danger');

            return;
        }

        app(DisableTwoFactorAction::class)->execute(new DisableTwoFactorData(
            userId: (int) Auth::id(),
        ));

        $this->reset('showRecoveryCodes');

        $this->toast(__('Two-factor authentication disabled.'));
    }

    public function render(): View
    {
        // A fresh query, not Auth::user() — the guard's own cached
        // instance can otherwise still reflect the pre-enable/-confirm
        // row for the rest of this request, since EnableTwoFactorAction/
        // ConfirmTwoFactorAction each load and save their own separate
        // User instance rather than mutating the guard's.
        $user = User::find(Auth::id());

        return view('core::auth.two-factor-setup', [
            'isEnabled' => $user?->two_factor_secret !== null,
            'isConfirmed' => $user?->two_factor_confirmed_at !== null,
            'qrCodeSvg' => $user?->two_factor_secret !== null && $user->two_factor_confirmed_at === null
                ? $user->twoFactorQrCodeSvg()
                : null,
            'recoveryCodes' => $this->showRecoveryCodes ? $user?->recoveryCodes() : null,
            'isRequired' => $user !== null && app(TwoFactorRequirement::class)->isRequiredFor($user),
        ]);
    }
}

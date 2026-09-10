<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use App\Models\User;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\CreateSuperAdminAction;
use Modules\Core\Domain\DataObjects\Install\SuperAdminData;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Administrator` (Book A CORE-01 §5). Name, email, password with
 * a policy check. AC-CORE-01-001: forced to enrol in 2FA before reaching
 * the dashboard — this screen is the installer's own gate for that;
 * persistent per-role enforcement afterwards belongs to CORE-05.
 */
#[Title('Administrator')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Administrator])]
final class Administrator extends InstallStepComponent
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $adminCreated = false;

    public bool $twoFactorEnabled = false;

    public string $qrCodeSvg = '';

    public string $manualSetupKey = '';

    public string $code = '';

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Administrator;
    }

    public function mount(): void
    {
        parent::mount();

        $userId = $this->progress()->payloadOf($this->stepKey())['user_id'] ?? null;

        if (! is_int($userId) && ! is_string($userId)) {
            return;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return;
        }

        $this->adminCreated = true;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->twoFactorEnabled = $user->two_factor_confirmed_at !== null;

        Auth::login($user);

        if (! $this->twoFactorEnabled && $user->two_factor_secret !== null) {
            $this->loadSetupData($user);
        }
    }

    public function createAdmin(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::default()->uncompromised(), 'confirmed'],
        ]);

        $user = app(CreateSuperAdminAction::class)->execute(new SuperAdminData(
            name: $this->name,
            email: $this->email,
            password: $this->password,
        ));

        $this->progress()->markRunning($this->stepKey(), ['user_id' => $user->id]);

        Auth::login($user);
        $enableTwoFactorAuthentication($user);

        $this->loadSetupData($user->fresh());
        $this->adminCreated = true;
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate(['code' => ['required', 'string', 'size:6']]);

        $user = Auth::user();

        $confirmTwoFactorAuthentication($user, $this->code);

        $this->twoFactorEnabled = true;
    }

    public function continue(): void
    {
        if (! $this->twoFactorEnabled) {
            return;
        }

        $this->completeStepAndContinue(['user_id' => Auth::id()]);
    }

    private function loadSetupData(User $user): void
    {
        try {
            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch two-factor setup data.');
        }
    }

    public function render(): View
    {
        return view('core::install.administrator');
    }
}

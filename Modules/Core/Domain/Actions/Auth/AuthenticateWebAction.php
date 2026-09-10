<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\AuthResult;
use Modules\Core\Domain\DataObjects\Auth\WebLoginData;
use Modules\Core\Domain\Events\Auth\UserLockedOut;
use Modules\Core\Domain\Events\Auth\UserLoggedIn;
use Modules\Core\Domain\Events\Auth\UserLoginFailed;
use Modules\Core\Domain\Exceptions\AccountLockedException;
use Modules\Core\Domain\Exceptions\InvalidCredentialsException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\LoginAttempt;

/**
 * ACT-AuthenticateWeb (Book A CORE-05 §3/§4). Throttle → credential
 * check → status check → hands back whether a 2FA challenge/enrolment
 * gate follows (BR-CORE-05-005); the caller (a Livewire login
 * component) is responsible for actually establishing the session —
 * this Action never touches auth()/session() itself.
 */
final class AuthenticateWebAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(WebLoginData $data): AuthResult
    {
        $scope = new ScopeChain(tenantId: $data->tenantId);
        $lockoutMinutes = (int) $this->settings->get('auth.lockout_minutes', $scope);
        $maxAttempts = (int) $this->settings->get('auth.max_failed_attempts', $scope);

        $this->assertIpNotThrottled($data->ip, $lockoutMinutes, $maxAttempts);

        $user = $this->findUser($data->identifier, $data->tenantId);

        if ($user !== null && $user->isLocked()) {
            $this->recordAttempt($data, $user, false, 'account_locked');

            throw new AccountLockedException('This account is temporarily locked. Try again later.');
        }

        if ($user === null || $user->password === null || ! Hash::check($data->password, $user->password)) {
            if ($user !== null) {
                $this->registerFailure($user, $maxAttempts, $lockoutMinutes);
            }

            $this->recordAttempt($data, $user, false, 'invalid_credentials');
            event(new UserLoginFailed($data->identifier, 'invalid_credentials'));

            throw new InvalidCredentialsException('Those credentials do not match our records.');
        }

        if (! $user->status->canAuthenticate()) {
            $this->recordAttempt($data, $user, false, 'account_not_active');

            throw new AccountLockedException("This account is {$user->status->value} and cannot sign in.");
        }

        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $data->ip,
        ])->save();

        $this->recordAttempt($data, $user, true, null);
        event(new UserLoggedIn($user, 'web'));

        $requiresTwoFactor = $user->two_factor_confirmed_at !== null || $this->roleRequiresTwoFactor($user, $scope);

        return new AuthResult($user, $requiresTwoFactor);
    }

    private function findUser(string $identifier, ?int $tenantId): ?User
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($query) => $query->where('email', $identifier)
                ->orWhere('phone', $identifier)
                ->orWhere('username', $identifier))
            ->first();
    }

    private function registerFailure(User $user, int $maxAttempts, int $lockoutMinutes): void
    {
        $failedCount = $user->failed_login_count + 1;

        $user->forceFill([
            'failed_login_count' => $failedCount,
            'locked_until' => $failedCount >= $maxAttempts ? Carbon::now()->addMinutes($lockoutMinutes) : $user->locked_until,
        ])->save();

        if ($failedCount >= $maxAttempts) {
            event(new UserLockedOut($user, $user->locked_until));
        }
    }

    private function assertIpNotThrottled(?string $ip, int $lockoutMinutes, int $maxAttempts): void
    {
        if ($ip === null) {
            return;
        }

        $recentFailures = LoginAttempt::query()
            ->where('ip_address', $ip)
            ->where('was_successful', false)
            ->where('attempted_at', '>=', Carbon::now()->subMinutes($lockoutMinutes))
            ->count();

        if ($recentFailures >= $maxAttempts) {
            throw new AccountLockedException('Too many failed attempts from this address. Try again later.');
        }
    }

    private function recordAttempt(WebLoginData $data, ?User $user, bool $successful, ?string $reason): void
    {
        LoginAttempt::create([
            'identifier' => $data->identifier,
            'user_id' => $user?->id,
            'guard' => 'web',
            'was_successful' => $successful,
            'failure_reason' => $reason,
            'ip_address' => $data->ip,
            'user_agent' => $data->userAgent,
            'attempted_at' => Carbon::now(),
        ]);
    }

    /**
     * Queried directly against `model_has_roles` rather than
     * `$user->roles()` — the "team" (school) scope spatie's relation
     * applies isn't resolvable yet at login time (`SchoolContext` is
     * only set after authentication, by `SetSchoolContext`), and
     * BR-CORE-05-005 doesn't limit the 2FA requirement to one school.
     */
    private function roleRequiresTwoFactor(User $user, ScopeChain $scope): bool
    {
        $requiredRoles = (array) $this->settings->get('auth.require_2fa_roles', $scope);

        if ($requiredRoles === []) {
            return false;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->whereIn('roles.name', $requiredRoles)
            ->exists();
    }
}

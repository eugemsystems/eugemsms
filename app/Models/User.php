<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Core\Domain\Support\Auth\UserStatus;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\LoginAttempt;
use Modules\Core\Models\RefreshToken;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\UserAccountLink;
use Modules\Core\Models\UserSessionPreference;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $tenant_id
 * @property string $name
 * @property string $first_name
 * @property string $last_name
 * @property string|null $other_names
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $username
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property string|null $password
 * @property string|null $avatar_path
 * @property UserType $user_type
 * @property string $locale
 * @property UserStatus $status
 * @property bool $must_change_password
 * @property Carbon|null $password_changed_at
 * @property string|null $two_factor_secret
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $failed_login_count
 * @property Carbon|null $locked_until
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $last_seen_at
 * @property string $theme
 * @property-read Tenant|null $tenant
 * @property-read Collection<int, School> $schools
 * @property-read Collection<int, UserSessionPreference> $sessionPreferences
 */
#[Fillable([
    'name', 'first_name', 'last_name', 'other_names', 'email', 'phone', 'username',
    'password', 'avatar_path', 'user_type', 'locale', 'status', 'must_change_password',
    'password_changed_at', 'tenant_id', 'theme',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserType::class,
            'status' => UserStatus::class,
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Cross-school user assignment (Volume 1 §3.2) — one identity, many
     * schools, roles resolved per (user, active school).
     *
     * @return BelongsToMany<School, $this, Pivot, 'pivot'>
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_user')
            ->withPivot(['is_primary', 'status', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<UserSessionPreference, $this>
     */
    public function sessionPreferences(): HasMany
    {
        return $this->hasMany(UserSessionPreference::class);
    }

    /**
     * @return HasMany<RefreshToken, $this>
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * @return HasMany<LoginAttempt, $this>
     */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class, 'user_id');
    }

    /**
     * @return HasMany<UserAccountLink, $this>
     */
    public function accountLinks(): HasMany
    {
        return $this->hasMany(UserAccountLink::class);
    }

    /**
     * @return HasMany<ImpersonationSession, $this>
     */
    public function impersonationsStarted(): HasMany
    {
        return $this->hasMany(ImpersonationSession::class, 'impersonator_id');
    }

    public function primarySchool(): ?School
    {
        $primary = $this->schools()
            ->wherePivot('is_primary', true)
            ->wherePivot('status', 'active')
            ->first();

        return $primary ?? $this->schools()->wherePivot('status', 'active')->first();
    }

    public function isAssignedToSchool(int $schoolId): bool
    {
        return $this->schools()->wherePivot('status', 'active')->where('schools.id', $schoolId)->exists();
    }

    /**
     * @return array<int, int>
     */
    public function assignedSchoolIds(): array
    {
        return $this->schools()->wherePivot('status', 'active')->pluck('schools.id')->all();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}

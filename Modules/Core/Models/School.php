<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SchoolFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * The tenancy anchor (Volume 1 ADR-003, Book A CORE-02 §2). Every other
 * tenant-owned table scopes itself to a school_id via `BelongsToSchool`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $short_name
 * @property string|null $centre_number
 * @property string|null $emis_code
 * @property string $category
 * @property string|null $responsible_authority
 * @property string|null $band
 * @property string|null $province
 * @property string|null $district
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $motto
 * @property string|null $logo_path
 * @property string|null $crest_path
 * @property string|null $letterhead_path
 * @property string $primary_colour
 * @property string|null $secondary_colour
 * @property string $base_currency
 * @property string $timezone
 * @property string $locale
 * @property int|null $head_user_id
 * @property string $status
 * @property Carbon|null $opened_on
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read User|null $head
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, AcademicYear> $academicYears
 * @property-read Collection<int, Term> $terms
 * @property-read Collection<int, SchoolSection> $sections
 * @property-read Collection<int, GradeLevel> $gradeLevels
 * @property-read Collection<int, SchoolClass> $classes
 * @property-read Collection<int, House> $houses
 * @property-read Collection<int, SchoolModule> $modules
 */
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'short_name',
        'centre_number',
        'emis_code',
        'category',
        'responsible_authority',
        'band',
        'province',
        'district',
        'address_line_1',
        'address_line_2',
        'city',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'motto',
        'logo_path',
        'crest_path',
        'letterhead_path',
        'primary_colour',
        'secondary_colour',
        'base_currency',
        'timezone',
        'locale',
        'head_user_id',
        'status',
        'opened_on',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'opened_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchoolFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * @return BelongsToMany<User, $this, Pivot, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_user')
            ->withPivot(['is_primary', 'status', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<AcademicYear, $this>
     */
    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    /**
     * @return HasMany<SchoolSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(SchoolSection::class);
    }

    /**
     * @return HasMany<GradeLevel, $this>
     */
    public function gradeLevels(): HasMany
    {
        return $this->hasMany(GradeLevel::class);
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * @return HasMany<House, $this>
     */
    public function houses(): HasMany
    {
        return $this->hasMany(House::class);
    }

    /**
     * @return HasMany<SchoolModule, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(SchoolModule::class);
    }

    public function currentAcademicYear(): ?AcademicYear
    {
        return $this->academicYears()->where('is_current', true)->first();
    }

    public function currentTerm(): ?Term
    {
        return $this->terms()->where('is_current', true)->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\TenantFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * The paying customer (Volume 1 §3.1) — a trust, a group, or a single
 * independent school. Owns one or more Schools.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string $country
 * @property string $status
 * @property bool $is_group_reporting_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, School> $schools
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'contact_name',
        'contact_email',
        'contact_phone',
        'country',
        'status',
        'is_group_reporting_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_group_reporting_enabled' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TenantFactory::new();
    }

    /**
     * @return HasMany<School, $this>
     */
    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

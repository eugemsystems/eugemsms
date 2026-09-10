<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ConfigurationProfileFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-04 §2/BR-CORE-04-014 — structure and configuration only,
 * never learner, staff, or financial data. Tenant-level, not
 * `BelongsToSchool`: a profile can be exported from one school and
 * imported into any other school in the same tenant.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int|null $source_school_id
 * @property array<string, mixed> $payload
 * @property string $version
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Tenant $tenant
 * @property-read School|null $sourceSchool
 */
class ConfigurationProfile extends Model
{
    /** @use HasFactory<ConfigurationProfileFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'source_school_id',
        'payload',
        'version',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConfigurationProfileFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function sourceSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'source_school_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

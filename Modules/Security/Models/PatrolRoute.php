<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Security\Database\Factories\PatrolRouteFactory;

/**
 * Book H2 OPS-06 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property array<int, int> $checkpoint_ids
 * @property int|null $expected_duration_min
 * @property string $frequency
 * @property array<int, mixed>|null $applies_at_times
 * @property bool $is_active
 */
class PatrolRoute extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PatrolRouteFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'checkpoint_ids', 'expected_duration_min', 'frequency',
        'applies_at_times', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'checkpoint_ids' => 'array',
            'applies_at_times' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PatrolRouteFactory::new();
    }

    /**
     * @return HasMany<Patrol, $this>
     */
    public function patrols(): HasMany
    {
        return $this->hasMany(Patrol::class);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;
use Modules\Security\Database\Factories\PatrolFactory;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-004/005.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $patrol_route_id
 * @property int $guard_staff_id
 * @property Carbon $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int $checkpoints_expected
 * @property int $checkpoints_scanned
 * @property string $status
 * @property string|null $findings
 */
class Patrol extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PatrolFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'patrol_route_id', 'guard_staff_id', 'scheduled_at', 'started_at', 'completed_at',
        'checkpoints_expected', 'checkpoints_scanned', 'status', 'findings',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PatrolFactory::new();
    }

    /**
     * @return BelongsTo<PatrolRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(PatrolRoute::class, 'patrol_route_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function guardStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'guard_staff_id');
    }

    /**
     * @return HasMany<PatrolScan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(PatrolScan::class);
    }
}

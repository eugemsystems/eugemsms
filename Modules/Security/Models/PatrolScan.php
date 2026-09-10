<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Security\Database\Factories\PatrolScanFactory;

/**
 * Book H2 OPS-06 §2. `checkpoint_id` is a real FK into `BRD-02`'s
 * `movement_checkpoints`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $patrol_id
 * @property int $checkpoint_id
 * @property Carbon $scanned_at
 * @property string $method
 * @property string|null $note
 * @property int|null $photo_file_id
 */
class PatrolScan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PatrolScanFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'patrol_id', 'checkpoint_id', 'scanned_at', 'method', 'note', 'photo_file_id',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PatrolScanFactory::new();
    }

    /**
     * @return BelongsTo<Patrol, $this>
     */
    public function patrol(): BelongsTo
    {
        return $this->belongsTo(Patrol::class);
    }

    /**
     * @return BelongsTo<MovementCheckpoint, $this>
     */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(MovementCheckpoint::class);
    }
}

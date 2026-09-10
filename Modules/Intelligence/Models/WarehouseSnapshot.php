<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\WarehouseSnapshotFactory;

/**
 * Book J INT-01 §2/BR-INT-01-007. See the owning migration's docblock
 * for this pass's honest scope (row-count tracking for the ad hoc
 * query budget decision, not a full generic denormalised data copy).
 *
 * @property int $id
 * @property int $school_id
 * @property string $entity_key
 * @property Carbon $snapshot_date
 * @property int $row_count
 * @property Carbon $rebuilt_at
 * @property int|null $duration_ms
 */
class WarehouseSnapshot extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WarehouseSnapshotFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'entity_key', 'snapshot_date', 'row_count', 'rebuilt_at', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'rebuilt_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WarehouseSnapshotFactory::new();
    }

    public function isFresh(): bool
    {
        return $this->snapshot_date->isToday();
    }
}

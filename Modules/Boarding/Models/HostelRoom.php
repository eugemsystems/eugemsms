<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Boarding\Database\Factories\HostelRoomFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book F BRD-01 §2/§3. `proximity_to_exit`/`is_ground_floor` are
 * placement constraints the allocation engine honours without ever
 * seeing the diagnosis behind them.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $hostel_id
 * @property int|null $wing_id
 * @property string $room_number
 * @property string $room_type
 * @property int $bed_count
 * @property string $condition_grade
 * @property bool $is_ground_floor
 * @property string|null $proximity_to_exit
 * @property string|null $proximity_to_ablution
 * @property bool $has_power_outlet
 * @property string|null $notes
 * @property bool $is_active
 */
class HostelRoom extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HostelRoomFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'hostel_id', 'wing_id', 'room_number', 'room_type', 'bed_count',
        'condition_grade', 'is_ground_floor', 'proximity_to_exit', 'proximity_to_ablution',
        'has_power_outlet', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_ground_floor' => 'boolean',
            'has_power_outlet' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HostelRoomFactory::new();
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * @return BelongsTo<HostelWing, $this>
     */
    public function wing(): BelongsTo
    {
        return $this->belongsTo(HostelWing::class);
    }

    /**
     * @return HasMany<HostelBed, $this>
     */
    public function beds(): HasMany
    {
        return $this->hasMany(HostelBed::class, 'room_id');
    }
}

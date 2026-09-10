<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\HostelBedFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book F BRD-01 §2/BR-BRD-01-002 — the unit `hostels.capacity` sums.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $room_id
 * @property string $bed_number
 * @property string $bed_type
 * @property string|null $asset_tag
 * @property string|null $mattress_asset_tag
 * @property string $condition_grade
 * @property bool $is_available
 * @property string|null $out_of_service_reason
 */
class HostelBed extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HostelBedFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = ['school_id', 'room_id', 'bed_number', 'bed_type', 'asset_tag', 'mattress_asset_tag', 'condition_grade', 'is_available', 'out_of_service_reason'];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HostelBedFactory::new();
    }

    /**
     * @return BelongsTo<HostelRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'room_id');
    }
}

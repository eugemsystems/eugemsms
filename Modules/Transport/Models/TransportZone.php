<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\FeeComponent;
use Modules\Transport\Database\Factories\TransportZoneFactory;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-006 — see this table's own migration
 * docblock for the `fee_component_id` boundary.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property float|null $max_distance_km
 * @property int|null $fee_component_id
 * @property int $termly_fee_minor
 * @property string $currency
 * @property bool $is_active
 */
class TransportZone extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TransportZoneFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'max_distance_km', 'fee_component_id', 'termly_fee_minor', 'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_distance_km' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TransportZoneFactory::new();
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function feeComponent(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }
}

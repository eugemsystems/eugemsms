<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\ConsumptionAnomalyFactory;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-023 — never auto-dismissed.
 *
 * @property int $id
 * @property int $school_id
 * @property int $store_id
 * @property int $item_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property float $expected_quantity
 * @property float $actual_quantity
 * @property float $variance_percent
 * @property float|null $occupancy_factor
 * @property string $severity
 * @property string $status
 * @property string|null $investigation_note
 * @property int|null $reviewed_by
 * @property Carbon $detected_at
 */
class ConsumptionAnomaly extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ConsumptionAnomalyFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'store_id', 'item_id', 'period_start', 'period_end', 'expected_quantity',
        'actual_quantity', 'variance_percent', 'occupancy_factor', 'severity', 'status',
        'investigation_note', 'reviewed_by', 'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'expected_quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
            'detected_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConsumptionAnomalyFactory::new();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

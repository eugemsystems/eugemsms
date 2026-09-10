<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Welfare\Database\Factories\ClinicStockFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-022 — lightweight; full mechanics are
 * `FIN-09` (Book H, not built).
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $inventory_item_id
 * @property string $name
 * @property string $category
 * @property bool $is_controlled
 * @property float $quantity_on_hand
 * @property string $unit
 * @property float|null $reorder_level
 * @property string|null $batch_number
 * @property Carbon|null $expiry_date
 * @property string|null $storage_location
 */
class ClinicStock extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ClinicStockFactory> */
    use HasFactory;

    protected $table = 'clinic_stock';

    protected $fillable = [
        'school_id', 'inventory_item_id', 'name', 'category', 'is_controlled', 'quantity_on_hand',
        'unit', 'reorder_level', 'batch_number', 'expiry_date', 'storage_location',
    ];

    protected function casts(): array
    {
        return [
            'is_controlled' => 'boolean',
            'quantity_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    /**
     * BR-BRD-06-022 — expired stock cannot be selected for administration.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->toDateString() < now()->toDateString();
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ClinicStockFactory::new();
    }

    /**
     * @return HasMany<ControlledStockLogEntry, $this>
     */
    public function controlledLog(): HasMany
    {
        return $this->hasMany(ControlledStockLogEntry::class, 'clinic_stock_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Utilities\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Database\Factories\MeterFactory;

/**
 * Book H2 OPS-04 §2 ⭐/BR-OPS-04-009.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $utility_account_id
 * @property string $meter_number
 * @property string $meter_type
 * @property string $location
 * @property string $serves_scope
 * @property int|null $scope_id
 * @property int|null $cost_centre_id
 * @property string $unit
 * @property float $multiplier
 * @property float|null $current_reading
 * @property float|null $current_balance_units
 * @property float|null $low_balance_threshold
 * @property Carbon|null $last_read_on
 * @property bool $is_active
 */
class Meter extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MeterFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'utility_account_id', 'meter_number', 'meter_type', 'location', 'serves_scope',
        'scope_id', 'cost_centre_id', 'unit', 'multiplier', 'current_reading', 'current_balance_units',
        'low_balance_threshold', 'last_read_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'current_reading' => 'decimal:3',
            'current_balance_units' => 'decimal:3',
            'low_balance_threshold' => 'decimal:3',
            'last_read_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MeterFactory::new();
    }

    /**
     * @return BelongsTo<UtilityAccount, $this>
     */
    public function utilityAccount(): BelongsTo
    {
        return $this->belongsTo(UtilityAccount::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return HasMany<PrepaidTokenPurchase, $this>
     */
    public function tokenPurchases(): HasMany
    {
        return $this->hasMany(PrepaidTokenPurchase::class);
    }

    /**
     * @return HasMany<MeterReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }
}

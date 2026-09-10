<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;
use Modules\Stores\Database\Factories\FixedAssetFactory;

/**
 * Book H1 FIN-10 §2/§3/BR-FIN-10-001/002/006/017 ⭐ — `net_book_value_minor`
 * is a maintained cache, verified nightly against the ledger
 * (BR-FIN-10-019), never recomputed ad hoc from cost minus
 * accumulated depreciation at read time.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $asset_tag
 * @property int $category_id
 * @property string $name
 * @property Carbon $acquisition_date
 * @property int $acquisition_cost_minor
 * @property string $currency
 * @property int $base_cost_minor
 * @property string $acquisition_source
 * @property int|null $supplier_id
 * @property int|null $purchase_order_id
 * @property int|null $grn_id
 * @property int|null $stock_movement_id
 * @property bool $is_depreciable
 * @property string $depreciation_method
 * @property float|null $useful_life_years
 * @property int $residual_value_minor
 * @property Carbon|null $depreciation_start_date
 * @property float|null $total_units_expected
 * @property float $units_consumed
 * @property int $accumulated_depreciation_minor
 * @property int $net_book_value_minor
 * @property Carbon|null $last_depreciated_on
 * @property bool $fully_depreciated
 * @property string|null $location
 * @property int|null $department_id
 * @property int $cost_centre_id
 * @property int|null $custodian_staff_id
 * @property string $status
 * @property string $condition
 * @property Carbon|null $last_verified_on
 * @property Carbon|null $next_verification_on
 */
class FixedAsset extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FixedAssetFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'asset_tag', 'category_id', 'name', 'description', 'serial_number', 'model',
        'manufacturer', 'acquisition_date', 'acquisition_cost_minor', 'currency', 'base_cost_minor',
        'exchange_rate_id', 'acquisition_source', 'supplier_id', 'purchase_order_id', 'grn_id',
        'stock_movement_id', 'donor_name', 'is_depreciable', 'depreciation_method', 'useful_life_years',
        'residual_value_minor', 'depreciation_start_date', 'total_units_expected', 'units_consumed',
        'accumulated_depreciation_minor', 'net_book_value_minor', 'last_depreciated_on', 'fully_depreciated',
        'location', 'building', 'room', 'department_id', 'cost_centre_id', 'custodian_staff_id', 'status',
        'condition', 'warranty_expires_on', 'photo_file_ids', 'barcode', 'qr_code', 'last_verified_on',
        'next_verification_on', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'is_depreciable' => 'boolean',
            'useful_life_years' => 'decimal:1',
            'depreciation_start_date' => 'date',
            'total_units_expected' => 'decimal:2',
            'units_consumed' => 'decimal:2',
            'last_depreciated_on' => 'date',
            'fully_depreciated' => 'boolean',
            'warranty_expires_on' => 'date',
            'photo_file_ids' => 'array',
            'last_verified_on' => 'date',
            'next_verification_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FixedAssetFactory::new();
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function custodianStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'custodian_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssetMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class, 'asset_id');
    }

    /**
     * @return HasMany<DepreciationEntry, $this>
     */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class, 'asset_id');
    }

    /**
     * @return HasOne<AssetDisposal, $this>
     */
    public function disposal(): HasOne
    {
        return $this->hasOne(AssetDisposal::class, 'asset_id');
    }
}

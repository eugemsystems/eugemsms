<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\AssetInsuranceFactory;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-015/016. `covered_asset_ids` null means
 * the whole `category_id` is covered.
 *
 * @property int $id
 * @property int $school_id
 * @property string $policy_number
 * @property string $insurer
 * @property array<int, int>|null $covered_asset_ids
 * @property int|null $category_id
 * @property int $sum_insured_minor
 * @property string $currency
 * @property Carbon $starts_on
 * @property Carbon $expires_on
 * @property string $status
 */
class AssetInsurance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssetInsuranceFactory> */
    use HasFactory;

    protected $table = 'asset_insurance';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'policy_number', 'insurer', 'policy_type', 'covered_asset_ids', 'category_id',
        'sum_insured_minor', 'currency', 'premium_minor', 'starts_on', 'expires_on', 'document_file_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'covered_asset_ids' => 'array',
            'starts_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssetInsuranceFactory::new();
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }
}

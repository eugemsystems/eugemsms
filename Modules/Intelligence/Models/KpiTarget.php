<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\KpiTargetFactory;

/**
 * Book J INT-02 §2/BR-INT-02-002/003.
 *
 * @property int $id
 * @property int $school_id
 * @property string $kpi_key
 * @property int $academic_year_id
 * @property float $target_value
 * @property float $warning_threshold_percent
 */
class KpiTarget extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<KpiTargetFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'kpi_key', 'academic_year_id', 'target_value', 'warning_threshold_percent',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'float',
            'warning_threshold_percent' => 'float',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return KpiTargetFactory::new();
    }
}

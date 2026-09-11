<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\School;
use Modules\Saas\Database\Factories\ModuleAdoptionScoreFactory;

/**
 * Book J SAA-03 §2/§3 ⭐/BR-SAA-03-006 ⭐. Not `BelongsToSchool` — read
 * cross-tenant by the vendor's adoption dashboard, the same visibility
 * shape every other `SAA-*` analytics table uses.
 *
 * @property int $id
 * @property int $school_id
 * @property string $module_code
 * @property string $period_month
 * @property string $activity_signal
 * @property int $activity_count
 * @property bool $is_actively_used
 */
class ModuleAdoptionScore extends Model
{
    /** @use HasFactory<ModuleAdoptionScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'module_code', 'period_month', 'activity_signal', 'activity_count', 'is_actively_used',
    ];

    protected function casts(): array
    {
        return [
            'activity_count' => 'integer',
            'is_actively_used' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ModuleAdoptionScoreFactory::new();
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}

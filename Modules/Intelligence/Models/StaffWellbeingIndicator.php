<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\Intelligence\Database\Factories\StaffWellbeingIndicatorFactory;
use Modules\People\Models\Staff;

/**
 * Book J INT-03 §2/BR-INT-03-009/010.
 *
 * @property int $id
 * @property int $school_id
 * @property int $staff_id
 * @property int $term_id
 * @property float|null $workload_utilisation_percent
 * @property int $consecutive_terms_over_ceiling
 * @property string|null $sick_leave_days_trend
 * @property string $flag_level
 */
class StaffWellbeingIndicator extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffWellbeingIndicatorFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'staff_id', 'term_id', 'workload_utilisation_percent',
        'consecutive_terms_over_ceiling', 'sick_leave_days_trend', 'flag_level',
    ];

    protected function casts(): array
    {
        return [
            'workload_utilisation_percent' => 'float',
            'consecutive_terms_over_ceiling' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffWellbeingIndicatorFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}

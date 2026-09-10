<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Payroll\Database\Factories\PayGradeNotchFactory;

/**
 * Book H3 PPL-05 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $grade_id
 * @property string $notch
 * @property int $basic_salary_minor
 * @property string $currency
 * @property Carbon $effective_from
 */
class PayGradeNotch extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PayGradeNotchFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'grade_id', 'notch', 'basic_salary_minor', 'currency', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PayGradeNotchFactory::new();
    }

    /**
     * @return BelongsTo<PayGrade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(PayGrade::class, 'grade_id');
    }
}

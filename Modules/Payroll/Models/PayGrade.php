<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Payroll\Database\Factories\PayGradeFactory;

/**
 * Book H3 PPL-05 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property int|null $min_salary_minor
 * @property int|null $max_salary_minor
 * @property string $currency
 * @property bool $is_active
 */
class PayGrade extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PayGradeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'category', 'min_salary_minor', 'max_salary_minor',
        'currency', 'nec_grade_reference', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PayGradeFactory::new();
    }

    /**
     * @return HasMany<PayGradeNotch, $this>
     */
    public function notches(): HasMany
    {
        return $this->hasMany(PayGradeNotch::class, 'grade_id');
    }
}

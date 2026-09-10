<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\FeeDefaultRiskScoreFactory;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book J INT-03 §2/BR-INT-03-006.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $guardian_id
 * @property float $risk_score
 * @property array<int, array<string, mixed>> $contributing_factors
 * @property string|null $recommended_action
 * @property Carbon $computed_at
 */
class FeeDefaultRiskScore extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeDefaultRiskScoreFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'guardian_id', 'risk_score',
        'contributing_factors', 'recommended_action', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'float',
            'contributing_factors' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeeDefaultRiskScoreFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}

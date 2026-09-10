<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\Intelligence\Database\Factories\LearnerRiskScoreFactory;
use Modules\People\Models\Student;

/**
 * Book J INT-03 §2/§3 ⭐/BR-INT-03-001/002/004/005.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $term_id
 * @property float $composite_score
 * @property string $risk_band
 * @property array<int, array<string, mixed>> $contributing_factors
 * @property Carbon $computed_at
 */
class LearnerRiskScore extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerRiskScoreFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'term_id', 'composite_score', 'risk_band',
        'contributing_factors', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'composite_score' => 'float',
            'contributing_factors' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerRiskScoreFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}

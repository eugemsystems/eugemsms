<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\ExamSlotPlanFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;

/**
 * Book E ACA-03 §2/BR-ACA-03-020 🇿🇼.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $name
 * @property string $exam_body
 * @property string $starts_on
 * @property string $ends_on
 * @property array<int, int> $affected_levels
 * @property array<int, int>|null $venues_reserved
 * @property array<int, int>|null $staff_reserved
 * @property array<string, mixed>|null $disruption_report
 * @property string $status
 * @property int $created_by
 */
class ExamSlotPlan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExamSlotPlanFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'name', 'exam_body', 'starts_on', 'ends_on',
        'affected_levels', 'venues_reserved', 'staff_reserved', 'disruption_report', 'status',
        'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'affected_levels' => 'array',
            'venues_reserved' => 'array',
            'staff_reserved' => 'array',
            'disruption_report' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExamSlotPlanFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}

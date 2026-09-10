<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\TimetableExceptionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;

/**
 * Book E ACA-03 §2/§5/BR-ACA-03-021.
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property Carbon $exception_date
 * @property string $exception_type
 * @property int|null $alternative_structure_id
 * @property string $affected_scope
 * @property int|null $scope_id
 * @property string $reason
 * @property bool $suppresses_attendance
 * @property int $created_by
 */
class TimetableException extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TimetableExceptionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'exception_date', 'exception_type', 'alternative_structure_id',
        'affected_scope', 'scope_id', 'reason', 'suppresses_attendance', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'suppresses_attendance' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TimetableExceptionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<PeriodStructure, $this>
     */
    public function alternativeStructure(): BelongsTo
    {
        return $this->belongsTo(PeriodStructure::class, 'alternative_structure_id');
    }
}

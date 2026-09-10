<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ExitInterviewFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-007 (AC-COM-08-005).
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int|null $guardian_id
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 * @property string|null $primary_reason
 * @property string|null $detail
 * @property bool|null $would_recommend
 * @property string|null $response_source
 */
class ExitInterview extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExitInterviewFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'guardian_id', 'requested_at', 'completed_at',
        'primary_reason', 'detail', 'would_recommend', 'response_source',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'would_recommend' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExitInterviewFactory::new();
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

    public function wasDeclined(): bool
    {
        return $this->response_source === 'declined';
    }
}

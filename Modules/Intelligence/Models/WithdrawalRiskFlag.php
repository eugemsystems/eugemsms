<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\WithdrawalRiskFlagFactory;
use Modules\People\Models\Student;

/**
 * Book J INT-03 §2/BR-INT-03-008. Closing without an `intervention_note`
 * is refused by `ReviewWithdrawalRiskFlagAction`, not by a DB guard —
 * unlike this book set's append-only tables, this row is legitimately
 * mutable (`status` progresses open → intervention_logged/resolved/withdrawn).
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property Carbon $flagged_at
 * @property array<int, array<string, mixed>> $contributing_factors
 * @property string $status
 * @property int|null $reviewed_by
 * @property string|null $intervention_note
 */
class WithdrawalRiskFlag extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WithdrawalRiskFlagFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'flagged_at', 'contributing_factors',
        'status', 'reviewed_by', 'intervention_note',
    ];

    protected function casts(): array
    {
        return [
            'flagged_at' => 'datetime',
            'contributing_factors' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WithdrawalRiskFlagFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

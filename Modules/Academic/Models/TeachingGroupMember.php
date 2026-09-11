<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\TeachingGroupMemberFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book D ACA-02 §2/BR-ACA-02-013 — see the owning migration's docblock
 * for why this table exists alongside `teaching_groups.current_count`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $teaching_group_id
 * @property int $student_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class TeachingGroupMember extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TeachingGroupMemberFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'teaching_group_id', 'student_id', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TeachingGroupMemberFactory::new();
    }

    /**
     * @return BelongsTo<TeachingGroup, $this>
     */
    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Book K ACA-08 §4/BR-ACA-08-002. Whether a student currently holds
     * an active membership of a teaching group, as of today.
     */
    public static function isActiveFor(int $studentId, int $teachingGroupId): bool
    {
        return static::query()
            ->where('student_id', $studentId)
            ->where('teaching_group_id', $teachingGroupId)
            ->whereDate('effective_from', '<=', Carbon::today())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', Carbon::today()))
            ->exists();
    }
}

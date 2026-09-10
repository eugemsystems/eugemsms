<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\LearnerIncompatibilityFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book F BRD-01 §2/§3/BR-BRD-01-008 ⭐. `reason` is confidential where
 * flagged — the allocation engine reads `is_active`/`scope` only; the
 * housemaster's view is expected to surface that a constraint exists,
 * never `reason` itself (enforced at the Livewire/API layer, not
 * built yet in this pass).
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_a_id
 * @property int $student_b_id
 * @property string $scope
 * @property string $reason_category
 * @property string|null $reason
 * @property bool $is_confidential
 * @property int $raised_by
 * @property Carbon|null $expires_on
 * @property bool $is_active
 */
class LearnerIncompatibility extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerIncompatibilityFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'student_a_id', 'student_b_id', 'scope', 'reason_category', 'reason', 'is_confidential', 'raised_by', 'expires_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_confidential' => 'boolean',
            'expires_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerIncompatibilityFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function studentA(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_a_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function studentB(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_b_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function involves(int $studentId): bool
    {
        return $this->student_a_id === $studentId || $this->student_b_id === $studentId;
    }

    public function otherStudentId(int $studentId): ?int
    {
        if ($this->student_a_id === $studentId) {
            return $this->student_b_id;
        }

        if ($this->student_b_id === $studentId) {
            return $this->student_a_id;
        }

        return null;
    }
}

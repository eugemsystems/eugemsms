<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\StudentLeadershipFactory;

/**
 * Book G BRD-07 §2/BR-BRD-07-020.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $student_id
 * @property string $role_title
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property array<int, string>|null $granted_permissions
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int $appointed_by
 * @property string $status
 * @property string|null $revocation_reason
 */
class StudentLeadership extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentLeadershipFactory> */
    use HasFactory;

    protected $table = 'student_leadership';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_id', 'role_title', 'scope_type', 'scope_id',
        'granted_permissions', 'starts_on', 'ends_on', 'appointed_by', 'status', 'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'granted_permissions' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentLeadershipFactory::new();
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
    public function appointedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appointed_by');
    }
}

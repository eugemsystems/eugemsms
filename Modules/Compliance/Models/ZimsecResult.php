<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\ZimsecResultFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book H3 CMP-01 §2/BR-CMP-01-010/011.
 *
 * @property int $id
 * @property int $school_id
 * @property int $registration_id
 * @property int $student_id
 * @property string $candidate_number
 * @property string $subject_code
 * @property string $subject_name
 * @property string $grade
 * @property string|null $points
 * @property bool $is_provisional
 * @property Carbon $imported_at
 * @property int $imported_by
 * @property int|null $source_file_id
 */
class ZimsecResult extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ZimsecResultFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'registration_id', 'student_id', 'candidate_number', 'subject_code',
        'subject_name', 'grade', 'points', 'is_provisional', 'imported_at', 'imported_by',
        'source_file_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'is_provisional' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ZimsecResultFactory::new();
    }

    /**
     * @return BelongsTo<ZimsecRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(ZimsecRegistration::class, 'registration_id');
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
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}

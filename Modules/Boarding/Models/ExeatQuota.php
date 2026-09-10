<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\ExeatQuotaFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book F BRD-03 §2/BR-BRD-03-005.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $term_id
 * @property int $exeat_type_id
 * @property int $allowed
 * @property int $used
 * @property int $pending
 */
class ExeatQuota extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExeatQuotaFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'student_id', 'term_id', 'exeat_type_id', 'allowed', 'used', 'pending'];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExeatQuotaFactory::new();
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

    /**
     * @return BelongsTo<ExeatType, $this>
     */
    public function exeatType(): BelongsTo
    {
        return $this->belongsTo(ExeatType::class);
    }

    public function remaining(): int
    {
        return max(0, $this->allowed - $this->used - $this->pending);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;
use Modules\Welfare\Database\Factories\ImmunisationFactory;

/**
 * Book G BRD-06 §2 — Tier 3 (grouped with clinical data by policy,
 * though it carries no free text needing encryption).
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property string $vaccine
 * @property int|null $dose_number
 * @property Carbon|null $administered_on
 * @property string|null $administered_by
 * @property string|null $batch_number
 * @property Carbon|null $next_due_on
 * @property int|null $certificate_file_id
 * @property string $status
 * @property string|null $decline_reason
 */
class Immunisation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ImmunisationFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'vaccine', 'dose_number', 'administered_on', 'administered_by',
        'batch_number', 'next_due_on', 'certificate_file_id', 'status', 'decline_reason',
    ];

    protected function casts(): array
    {
        return [
            'administered_on' => 'date',
            'next_due_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ImmunisationFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

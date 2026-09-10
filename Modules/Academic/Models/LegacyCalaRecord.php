<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LegacyCalaRecordFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\AcademicYear;
use Modules\People\Models\Student;
use RuntimeException;

/**
 * Book E ACA-06 §7 — CALA marks recorded before the May 2024 SBP
 * changeover (Circular No. 9 of 2024). Read-only history: production
 * grants revoke UPDATE/DELETE for the app DB user (documented as a
 * deployment step, mirroring `Modules\Core\Models\FinancialAuditLogEntry`).
 * This model-level guard is the layer that is actually testable and
 * always in effect, regardless of the DB grant.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $student_id
 * @property int $subject_id
 * @property int $cala_number
 * @property string|null $title
 * @property string $raw_mark
 * @property string $max_mark
 * @property string|null $percent
 * @property Carbon $recorded_at
 * @property string|null $source
 */
class LegacyCalaRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LegacyCalaRecordFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'academic_year_id', 'student_id', 'subject_id', 'cala_number', 'title', 'raw_mark', 'max_mark', 'percent', 'recorded_at', 'source'];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LegacyCalaRecordFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @throws RuntimeException always — legacy CALA records are read-only.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('legacy_cala_records is read-only: CALA history is preserved as recorded, never amended.');
    }

    /**
     * @throws RuntimeException always — legacy CALA records are read-only.
     */
    public function delete(): bool
    {
        throw new RuntimeException('legacy_cala_records is read-only: CALA history is preserved as recorded, never deleted.');
    }
}

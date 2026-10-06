<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StudentPriorSchoolFactory;

/**
 * Book C PPL-01 §2. Where a learner was before this school.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property string $school_name
 * @property string|null $school_type
 * @property string $country
 * @property string|null $province
 * @property Carbon|null $attended_from
 * @property Carbon|null $attended_to
 * @property string|null $last_grade_completed
 * @property string|null $reason_for_leaving
 * @property int|null $transfer_letter_file_id
 * @property bool $had_outstanding_fees
 * @property string|null $notes
 */
class StudentPriorSchool extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentPriorSchoolFactory> */
    use HasFactory;

    protected $table = 'student_prior_schools';

    public $timestamps = false;

    protected $fillable = ['school_id', 'student_id', 'school_name', 'school_type', 'country', 'province', 'attended_from', 'attended_to', 'last_grade_completed', 'reason_for_leaving', 'transfer_letter_file_id', 'had_outstanding_fees', 'notes'];

    protected function casts(): array
    {
        return [
            'attended_from' => 'date',
            'attended_to' => 'date',
            'had_outstanding_fees' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentPriorSchoolFactory::new();
    }
}

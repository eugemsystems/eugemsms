<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StaffQualificationFactory;

/**
 * Book C PPL-04 §2. A staff member's qualifications, verified against the certificate.
 *
 * @property int $id
 * @property int $school_id
 * @property int $staff_id
 * @property string $qualification_type
 * @property string $title
 * @property string $institution
 * @property string $country
 * @property int|null $year_obtained
 * @property string|null $grade_class
 * @property array<int, string>|null $subjects
 * @property int|null $certificate_file_id
 * @property bool $is_verified
 * @property int|null $verified_by
 */
class StaffQualification extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffQualificationFactory> */
    use HasFactory;

    protected $table = 'staff_qualifications';

    public $timestamps = false;

    protected $fillable = ['school_id', 'staff_id', 'qualification_type', 'title', 'institution', 'country', 'year_obtained', 'grade_class', 'subjects', 'certificate_file_id', 'is_verified', 'verified_by'];

    protected function casts(): array
    {
        return [
            'subjects' => 'array',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffQualificationFactory::new();
    }
}

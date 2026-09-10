<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\HostelWingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book F BRD-01 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $hostel_id
 * @property string $code
 * @property string $name
 * @property string|null $floor
 * @property int|null $supervisor_staff_id
 * @property int|null $prefect_student_id
 * @property bool $is_active
 */
class HostelWing extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HostelWingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'hostel_id', 'code', 'name', 'floor', 'supervisor_staff_id', 'prefect_student_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HostelWingFactory::new();
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervisor_staff_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function prefect(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'prefect_student_id');
    }
}

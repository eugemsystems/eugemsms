<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\CalendarHolidayFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book A CORE-03 §2. Feeds BR-CORE-03-004's teaching-days computation.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicYear $academicYear
 */
class CalendarHoliday extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CalendarHolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'starts_on',
        'ends_on',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CalendarHolidayFactory::new();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}

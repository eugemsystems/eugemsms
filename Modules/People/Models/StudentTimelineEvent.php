<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StudentTimelineEventFactory;

/**
 * Book C PPL-01 §2. A denormalised feed of what happened to a learner, for the profile timeline. Append-only.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int|null $academic_year_id
 * @property int|null $term_id
 * @property string $event_category
 * @property string $event_type
 * @property string $title
 * @property string|null $summary
 * @property string|null $severity
 * @property string|null $source_type
 * @property int|null $source_id
 * @property bool $is_visible_to_guardian
 * @property Carbon $occurred_at
 * @property int|null $recorded_by
 */
class StudentTimelineEvent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentTimelineEventFactory> */
    use HasFactory;

    protected $table = 'student_timeline';

    public $timestamps = false;

    protected $fillable = ['school_id', 'student_id', 'academic_year_id', 'term_id', 'event_category', 'event_type', 'title', 'summary', 'severity', 'source_type', 'source_id', 'is_visible_to_guardian', 'occurred_at', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'is_visible_to_guardian' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentTimelineEventFactory::new();
    }
}

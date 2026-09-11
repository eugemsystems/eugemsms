<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\AlumniEventFactory;

/**
 * Book K PPL-06 §2/BR-PPL-06-005. Built entirely on `COM-06`'s
 * calendar — this table adds only `target_graduation_years`
 * targeting on top of it.
 *
 * @property int $id
 * @property int $school_id
 * @property int $calendar_event_id
 * @property string $event_type
 * @property array<int, int>|null $target_graduation_years
 * @property bool $requires_ticket
 */
class AlumniEvent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AlumniEventFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'calendar_event_id', 'event_type', 'target_graduation_years', 'requires_ticket'];

    protected function casts(): array
    {
        return [
            'target_graduation_years' => 'array',
            'requires_ticket' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AlumniEventFactory::new();
    }

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function targetsGraduationYear(int $year): bool
    {
        return $this->target_graduation_years === null || in_array($year, $this->target_graduation_years, true);
    }
}

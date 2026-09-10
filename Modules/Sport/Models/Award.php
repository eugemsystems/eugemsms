<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Student;
use Modules\Sport\Database\Factories\AwardFactory;

/**
 * Book H2 OPS-07 §2. BR-OPS-07-010's "carries into the alumni record
 * on graduation" is a documented Book K deferral — no alumni module
 * exists yet.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $student_id
 * @property string $award_type
 * @property int|null $activity_id
 * @property string $title
 * @property string|null $citation
 * @property Carbon $awarded_on
 * @property int $awarded_by
 * @property bool $appears_on_report_card
 * @property bool $appears_on_transcript
 */
class Award extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_id', 'award_type', 'activity_id', 'title',
        'citation', 'awarded_on', 'awarded_by', 'appears_on_report_card', 'appears_on_transcript',
    ];

    protected function casts(): array
    {
        return [
            'awarded_on' => 'date',
            'appears_on_report_card' => 'boolean',
            'appears_on_transcript' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AwardFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}

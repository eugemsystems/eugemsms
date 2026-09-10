<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\RoomInspectionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book F BRD-01 §2/BR-BRD-01-011.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $room_id
 * @property Carbon $inspection_date
 * @property string $inspection_type
 * @property array<string, int> $criteria_scores
 * @property string|null $total_score
 * @property string $max_score
 * @property string|null $grade
 * @property string|null $findings
 * @property array<int, int>|null $photo_file_ids
 * @property int $inspector_staff_id
 * @property bool $follow_up_required
 * @property Carbon|null $follow_up_by
 */
class RoomInspection extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RoomInspectionFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'room_id', 'inspection_date', 'inspection_type', 'criteria_scores',
        'total_score', 'max_score', 'grade', 'findings', 'photo_file_ids', 'inspector_staff_id',
        'follow_up_required', 'follow_up_by',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'criteria_scores' => 'array',
            'photo_file_ids' => 'array',
            'follow_up_required' => 'boolean',
            'follow_up_by' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RoomInspectionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<HostelRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'room_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'inspector_staff_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\ExaminationSeatingFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book E ACA-07 §2/BR-ACA-07-006/007. No `ulid` — always reached
 * through its paper.
 *
 * @property int $id
 * @property int $school_id
 * @property int $paper_id
 * @property int $candidate_id
 * @property int $venue_id
 * @property int|null $row_number
 * @property string $seat_number
 * @property bool|null $attended
 * @property string|null $arrival_time
 * @property string|null $notes
 */
class ExaminationSeating extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExaminationSeatingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'paper_id', 'candidate_id', 'venue_id', 'row_number', 'seat_number', 'attended', 'arrival_time', 'notes'];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExaminationSeatingFactory::new();
    }

    /**
     * @return BelongsTo<ExaminationPaper, $this>
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(ExaminationPaper::class, 'paper_id');
    }

    /**
     * @return BelongsTo<ExaminationCandidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ExaminationCandidate::class, 'candidate_id');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}

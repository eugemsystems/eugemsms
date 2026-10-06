<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\EntranceExamCandidateFactory;

/**
 * Book C PPL-02 §2. An applicant sitting an entrance exam, with their marks and rank.
 *
 * @property int $id
 * @property int $school_id
 * @property int $exam_id
 * @property int $application_id
 * @property string $candidate_number
 * @property string|null $seat_number
 * @property bool|null $attended
 * @property array<string, float>|null $marks
 * @property string|null $total_mark
 * @property string|null $percentage
 * @property int|null $rank_in_exam
 */
class EntranceExamCandidate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EntranceExamCandidateFactory> */
    use HasFactory;

    protected $table = 'entrance_exam_candidates';

    public $timestamps = false;

    protected $fillable = ['school_id', 'exam_id', 'application_id', 'candidate_number', 'seat_number', 'attended', 'marks', 'total_mark', 'percentage', 'rank_in_exam'];

    protected function casts(): array
    {
        return [
            'marks' => 'array',
            'attended' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EntranceExamCandidateFactory::new();
    }
}

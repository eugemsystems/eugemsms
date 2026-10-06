<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\InterviewFactory;

/**
 * Book C PPL-02 §2. An applicant interview with panel scores and a recommendation.
 *
 * @property int $id
 * @property int $school_id
 * @property int $application_id
 * @property Carbon $scheduled_at
 * @property string|null $venue
 * @property array<int, int> $panel_user_ids
 * @property bool|null $attended
 * @property array<string, float>|null $scores
 * @property string|null $total_score
 * @property string|null $recommendation
 * @property string|null $panel_notes
 * @property Carbon|null $completed_at
 */
class Interview extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    protected $table = 'interviews';

    public $timestamps = false;

    protected $fillable = ['school_id', 'application_id', 'scheduled_at', 'venue', 'panel_user_ids', 'attended', 'scores', 'total_score', 'recommendation', 'panel_notes', 'completed_at'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'panel_user_ids' => 'array',
            'scores' => 'array',
            'attended' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return InterviewFactory::new();
    }
}

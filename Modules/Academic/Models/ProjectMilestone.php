<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ProjectMilestoneFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book E ACA-06 §2 — a staged submission point within a brief.
 *
 * @property int $id
 * @property int $school_id
 * @property int $brief_id
 * @property int $sequence
 * @property string $title
 * @property string|null $description
 * @property Carbon $due_on
 * @property string $weight_percent
 * @property bool $requires_evidence
 */
class ProjectMilestone extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectMilestoneFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'brief_id', 'sequence', 'title', 'description', 'due_on', 'weight_percent', 'requires_evidence'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'requires_evidence' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectMilestoneFactory::new();
    }

    /**
     * @return BelongsTo<ProjectBrief, $this>
     */
    public function brief(): BelongsTo
    {
        return $this->belongsTo(ProjectBrief::class, 'brief_id');
    }
}

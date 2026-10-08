<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ProjectPortfolioFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Document;

/**
 * Book E ACA-06 §6/BR-ACA-06-017. The audit trail of each portfolio
 * compilation — the document itself (JSON, no bespoke PDF renderer,
 * the same pattern `BoardPack`/`ReportCardRun` already use) is the
 * source of truth; this just records when and by whom.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $learner_project_id
 * @property int|null $document_id
 * @property int $compiled_by
 * @property Carbon $compiled_at
 */
class ProjectPortfolio extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectPortfolioFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = ['school_id', 'learner_project_id', 'document_id', 'compiled_by', 'compiled_at'];

    protected function casts(): array
    {
        return [
            'compiled_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectPortfolioFactory::new();
    }

    /**
     * @return BelongsTo<LearnerProject, $this>
     */
    public function learnerProject(): BelongsTo
    {
        return $this->belongsTo(LearnerProject::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function compiledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compiled_by');
    }
}

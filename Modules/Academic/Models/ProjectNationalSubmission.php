<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ProjectNationalSubmissionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Document;

/**
 * Book E ACA-06 §6/BR-ACA-06-018. The audit trail of a national
 * submission export — the document itself (generated through a
 * school's own configurable template) is the source of truth; this
 * just records when, by whom, and how many candidates it covered.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $instrument_id
 * @property int $academic_year_id
 * @property int|null $document_id
 * @property int $candidate_count
 * @property int $exported_by
 * @property Carbon $exported_at
 */
class ProjectNationalSubmission extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectNationalSubmissionFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = ['school_id', 'instrument_id', 'academic_year_id', 'document_id', 'candidate_count', 'exported_by', 'exported_at'];

    protected function casts(): array
    {
        return [
            'exported_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectNationalSubmissionFactory::new();
    }

    /**
     * @return BelongsTo<AssessmentInstrument, $this>
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(AssessmentInstrument::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
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
    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}

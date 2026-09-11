<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\CbtTestFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;

/**
 * Book K ACA-09 §2/BR-ACA-09-010 ⭐ — `assessment_id` is this module's
 * own addition; see the owning migration's docblock.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $title
 * @property int $subject_id
 * @property int|null $assessment_type_id
 * @property int|null $assessment_id
 * @property string $assembly_method
 * @property array<string, mixed>|null $assembly_rules
 * @property array<int, int>|null $question_ids
 * @property bool $randomise_question_order
 * @property bool $randomise_option_order
 * @property int $duration_minutes
 * @property Carbon $opens_at
 * @property Carbon $closes_at
 * @property bool $browser_focus_monitoring
 * @property int|null $max_tab_switches
 * @property string $status
 */
class CbtTest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CbtTestFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'title', 'subject_id', 'assessment_type_id', 'assessment_id',
        'assembly_method', 'assembly_rules', 'question_ids', 'randomise_question_order',
        'randomise_option_order', 'duration_minutes', 'opens_at', 'closes_at',
        'browser_focus_monitoring', 'max_tab_switches', 'status',
    ];

    protected function casts(): array
    {
        return [
            'assembly_rules' => 'array',
            'question_ids' => 'array',
            'randomise_question_order' => 'boolean',
            'randomise_option_order' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'browser_focus_monitoring' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CbtTestFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<AssessmentType, $this>
     */
    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return HasMany<CbtCandidateAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(CbtCandidateAttempt::class, 'test_id');
    }

    public function isWithinWindow(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return $at->between($this->opens_at, $this->closes_at);
    }
}

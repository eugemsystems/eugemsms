<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Welfare\Database\Factories\RiskAssessmentFactory;

/**
 * Book G BRD-08 §2/BR-BRD-08-019. `risk_factors`/`protective_factors`
 * are stored as `json_encode`d, `SecondaryEncrypted` strings.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $case_id
 * @property Carbon $assessed_at
 * @property int $assessed_by
 * @property string $risk_factors
 * @property string|null $protective_factors
 * @property string $risk_level
 * @property string $rationale
 * @property string $mitigation_plan
 * @property Carbon $review_due_on
 */
class RiskAssessment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RiskAssessmentFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'case_id', 'assessed_at', 'assessed_by', 'risk_factors', 'protective_factors',
        'risk_level', 'rationale', 'mitigation_plan', 'review_due_on',
    ];

    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
            'risk_factors' => SecondaryEncrypted::class,
            'protective_factors' => SecondaryEncrypted::class,
            'rationale' => SecondaryEncrypted::class,
            'mitigation_plan' => SecondaryEncrypted::class,
            'review_due_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RiskAssessmentFactory::new();
    }

    /**
     * @return BelongsTo<SafeguardingCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SafeguardingCase::class, 'case_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}

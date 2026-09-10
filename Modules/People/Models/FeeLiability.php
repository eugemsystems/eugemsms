<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Database\Factories\FeeLiabilityFactory;

/**
 * Book C PPL-03 §3/§4 ⭐. THE table `LiabilityResolver` (Finance's
 * `IssueInvoicesForAssignmentAction` calls it) reads to decide who
 * receives a bill. `component_id = null` means "all other components"
 * (§4's pass 2, evaluated after every component-specific rule).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int $guardian_id
 * @property int|null $component_id
 * @property string $share_type
 * @property string|null $share_percent
 * @property int|null $share_amount_minor
 * @property string|null $currency
 * @property int $priority
 * @property int|null $academic_year_id
 * @property int|null $term_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 */
class FeeLiability extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeLiabilityFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'guardian_id', 'component_id', 'share_type',
        'share_percent', 'share_amount_minor', 'currency', 'priority', 'academic_year_id',
        'term_id', 'effective_from', 'effective_to', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'share_percent' => 'decimal:2',
            'priority' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeeLiabilityFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }
}

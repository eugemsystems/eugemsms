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
use Modules\People\Database\Factories\StaffAppraisalFactory;

/**
 * Book C PPL-04 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $staff_id
 * @property int $academic_year_id
 * @property string $cycle
 * @property int $appraiser_staff_id
 * @property array<string, mixed>|null $self_assessment
 * @property array<string, mixed>|null $appraiser_assessment
 * @property array<string, mixed>|null $objectives
 * @property string|null $overall_rating
 * @property string|null $development_plan
 * @property string|null $staff_comments
 * @property string $status
 * @property Carbon|null $signed_off_at
 */
class StaffAppraisal extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffAppraisalFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'academic_year_id', 'cycle', 'appraiser_staff_id', 'self_assessment',
        'appraiser_assessment', 'objectives', 'overall_rating', 'development_plan', 'staff_comments',
        'status', 'signed_off_at',
    ];

    protected function casts(): array
    {
        return [
            'self_assessment' => 'array',
            'appraiser_assessment' => 'array',
            'objectives' => 'array',
            'signed_off_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffAppraisalFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function appraiser(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'appraiser_staff_id');
    }
}

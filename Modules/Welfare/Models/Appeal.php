<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Guardian;
use Modules\Welfare\Database\Factories\AppealFactory;

/**
 * Book G BRD-07 §2/BR-BRD-07-008.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $sanction_id
 * @property int|null $lodged_by_guardian_id
 * @property bool $lodged_by_student
 * @property string $grounds
 * @property int|null $supporting_document_id
 * @property Carbon $lodged_at
 * @property Carbon|null $heard_at
 * @property array<int, mixed>|null $heard_by
 * @property string|null $outcome
 * @property string|null $outcome_reason
 * @property int|null $new_sanction_id
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property string $status
 */
class Appeal extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AppealFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'sanction_id', 'lodged_by_guardian_id', 'lodged_by_student', 'grounds',
        'supporting_document_id', 'lodged_at', 'heard_at', 'heard_by', 'outcome', 'outcome_reason',
        'new_sanction_id', 'decided_at', 'decided_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'lodged_by_student' => 'boolean',
            'lodged_at' => 'datetime',
            'heard_at' => 'datetime',
            'heard_by' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AppealFactory::new();
    }

    /**
     * @return BelongsTo<Sanction, $this>
     */
    public function sanction(): BelongsTo
    {
        return $this->belongsTo(Sanction::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function lodgedByGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'lodged_by_guardian_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

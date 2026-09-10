<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\AllocatedNumberFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book A CORE-06 §2/§3. BR-CORE-06-002: never reused — a failed or
 * cancelled document produces a `voided` record with a reason, it is
 * not deleted and its sequence is not reissued.
 *
 * @property int $id
 * @property int $school_id
 * @property int $series_id
 * @property int $sequence
 * @property string $formatted_number
 * @property string $document_type
 * @property string|null $documentable_type
 * @property int|null $documentable_id
 * @property string $status
 * @property string|null $void_reason
 * @property int $allocated_by
 * @property Carbon $allocated_at
 * @property Carbon|null $used_at
 * @property Carbon|null $voided_at
 */
class AllocatedNumber extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AllocatedNumberFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'series_id', 'sequence', 'formatted_number', 'document_type',
        'documentable_type', 'documentable_id', 'status', 'void_reason',
        'allocated_by', 'allocated_at', 'used_at', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
            'used_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AllocatedNumberFactory::new();
    }

    /**
     * @return BelongsTo<NumberingSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(NumberingSeries::class, 'series_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }
}

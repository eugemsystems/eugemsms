<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\ScriptBatchFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;

/**
 * Book E ACA-07 §2/§3 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $paper_id
 * @property string $batch_reference
 * @property int|null $venue_id
 * @property int $script_count
 * @property int $expected_count
 * @property string $status
 * @property int|null $current_holder_staff_id
 */
class ScriptBatch extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ScriptBatchFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = ['school_id', 'paper_id', 'batch_reference', 'venue_id', 'script_count', 'expected_count', 'status', 'current_holder_staff_id'];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ScriptBatchFactory::new();
    }

    /**
     * @return BelongsTo<ExaminationPaper, $this>
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(ExaminationPaper::class, 'paper_id');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'current_holder_staff_id');
    }

    /**
     * @return HasMany<ScriptCustodyLogEntry, $this>
     */
    public function custodyLog(): HasMany
    {
        return $this->hasMany(ScriptCustodyLogEntry::class, 'batch_id')->orderBy('occurred_at');
    }
}

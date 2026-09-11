<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\SyllabusCoverageRecordFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-11 §2/BR-ACA-11-002. One row per planned topic; a null
 * `actual_delivered_on` means "not yet delivered" — the coverage
 * tracker's own on-track/behind read, not stored here.
 *
 * @property int $id
 * @property int $school_id
 * @property int $scheme_of_work_id
 * @property int $planned_topic_index
 * @property Carbon|null $actual_delivered_on
 * @property string|null $variance_note
 */
class SyllabusCoverageRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SyllabusCoverageRecordFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'scheme_of_work_id', 'planned_topic_index', 'actual_delivered_on', 'variance_note'];

    protected function casts(): array
    {
        return [
            'actual_delivered_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SyllabusCoverageRecordFactory::new();
    }

    /**
     * @return BelongsTo<SchemeOfWork, $this>
     */
    public function schemeOfWork(): BelongsTo
    {
        return $this->belongsTo(SchemeOfWork::class);
    }
}

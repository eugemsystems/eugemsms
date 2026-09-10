<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ReportCardRunFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;

/**
 * Book D ACA-05 §2/§6 ⭐ — a batch report-card generation request.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property array<string, mixed>|null $scope_filter
 * @property int $template_id
 * @property int $template_version
 * @property int $total_count
 * @property int $generated_count
 * @property int $withheld_count
 * @property int $failed_count
 * @property string $status
 * @property int|null $merged_document_id
 * @property int $requested_by
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class ReportCardRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportCardRunFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'scope_filter', 'template_id', 'template_version', 'total_count',
        'generated_count', 'withheld_count', 'failed_count', 'status', 'merged_document_id',
        'requested_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_filter' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReportCardRunFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}

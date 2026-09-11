<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\BulkTextbookIssueFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;

/**
 * Book K ACA-10 §2/§3 ⭐/BR-ACA-10-007. `exceptions` is this module's
 * own addition — see the owning migration's docblock.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $class_id
 * @property string $issue_type
 * @property array<int, int> $item_ids
 * @property int $total_learners
 * @property int $completed_count
 * @property int $exception_count
 * @property array<int, array<string, mixed>>|null $exceptions
 * @property string $status
 */
class BulkTextbookIssue extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BulkTextbookIssueFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'class_id', 'issue_type', 'item_ids', 'total_learners',
        'completed_count', 'exception_count', 'exceptions', 'status',
    ];

    protected function casts(): array
    {
        return [
            'item_ids' => 'array',
            'total_learners' => 'integer',
            'completed_count' => 'integer',
            'exception_count' => 'integer',
            'exceptions' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BulkTextbookIssueFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}

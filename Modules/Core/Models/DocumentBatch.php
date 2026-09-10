<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\DocumentBatchFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-06 §2/BR-CORE-06-013.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $document_type
 * @property int $template_id
 * @property int $total_count
 * @property int $completed_count
 * @property int $failed_count
 * @property string $status
 * @property string|null $merged_file_path
 * @property array<int, array<string, mixed>>|null $error_log
 * @property int $requested_by
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class DocumentBatch extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DocumentBatchFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'document_type', 'template_id', 'total_count', 'completed_count',
        'failed_count', 'status', 'merged_file_path', 'error_log', 'requested_by',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'error_log' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DocumentBatchFactory::new();
    }

    /**
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function recordFailure(int $index, string $message): void
    {
        $this->error_log = [...($this->error_log ?? []), ['index' => $index, 'message' => $message]];
        $this->failed_count++;
        $this->save();
    }
}

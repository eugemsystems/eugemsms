<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ImportBatchFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-11 §2. Not `BelongsToSchool` — every write supplies
 * `school_id` explicitly, same reasoning as CORE-08/09/10's tables.
 * `term_id`/`academic_year_id` exist purely so `PeriodGuard` can gate
 * writes against them (BR-CORE-11-008); a school-wide import (no
 * academic content) simply leaves both null.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $academic_year_id
 * @property int|null $term_id
 * @property string $definition_key
 * @property int $source_file_id
 * @property array<string, string> $column_mapping
 * @property array{duplicate_strategy?: string, dry_run?: bool}|null $options
 * @property string $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $imported_rows
 * @property int $skipped_rows
 * @property int $failed_rows
 * @property array<string, mixed>|null $validation_report
 * @property int|null $error_file_id
 * @property int $imported_by
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $rolled_back_at
 */
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'definition_key', 'source_file_id',
        'column_mapping', 'options', 'status', 'total_rows', 'valid_rows', 'invalid_rows',
        'imported_rows', 'skipped_rows', 'failed_rows', 'validation_report',
        'error_file_id', 'imported_by', 'started_at', 'completed_at', 'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'options' => 'array',
            'validation_report' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ImportBatchFactory::new();
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'source_file_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function errorFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'error_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }

    public function duplicateStrategy(): string
    {
        return $this->options['duplicate_strategy'] ?? 'skip';
    }

    public function isDryRun(): bool
    {
        return (bool) ($this->options['dry_run'] ?? false);
    }
}

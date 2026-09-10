<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Book A CORE-11 §2/BR-CORE-11-006.
 *
 * @property int $id
 * @property int $batch_id
 * @property int $row_number
 * @property array<string, mixed> $raw_data
 * @property array<string, mixed>|null $mapped_data
 * @property string $status
 * @property array<int, string>|null $errors
 * @property string|null $created_type
 * @property int|null $created_id
 */
class ImportRow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'batch_id', 'row_number', 'raw_data', 'mapped_data', 'status', 'errors',
        'created_type', 'created_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'mapped_data' => 'array',
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }
}

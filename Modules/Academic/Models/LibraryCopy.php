<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LibraryCopyFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-10 §2/BR-ACA-10-001 — `accession_number` is gapless per
 * school, allocated through `CORE-06`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $item_id
 * @property string $accession_number
 * @property string|null $barcode
 * @property string $condition
 * @property Carbon|null $acquired_on
 * @property string $status
 */
class LibraryCopy extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LibraryCopyFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'item_id', 'accession_number', 'barcode', 'condition', 'acquired_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LibraryCopyFactory::new();
    }

    /**
     * @return BelongsTo<LibraryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'item_id');
    }
}

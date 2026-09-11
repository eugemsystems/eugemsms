<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LibraryStockTakeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-10 §2/BR-ACA-10-009. Table is `stock_takes_library`
 * (word order per spec) — see the owning migration's docblock, which
 * also explains this model's two own additions:
 * `scanned_copy_ids`/`confirmatory_pass_done`.
 *
 * @property int $id
 * @property int $school_id
 * @property Carbon $conducted_on
 * @property int $expected_count
 * @property int $scanned_count
 * @property int $missing_count
 * @property array<int, int> $scanned_copy_ids
 * @property bool $confirmatory_pass_done
 * @property string $status
 */
class LibraryStockTake extends Model
{
    use BelongsToSchool;

    protected $table = 'stock_takes_library';

    /** @use HasFactory<LibraryStockTakeFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'conducted_on', 'expected_count', 'scanned_count', 'missing_count',
        'scanned_copy_ids', 'confirmatory_pass_done', 'status',
    ];

    protected function casts(): array
    {
        return [
            'conducted_on' => 'date',
            'expected_count' => 'integer',
            'scanned_count' => 'integer',
            'missing_count' => 'integer',
            'scanned_copy_ids' => 'array',
            'confirmatory_pass_done' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LibraryStockTakeFactory::new();
    }
}

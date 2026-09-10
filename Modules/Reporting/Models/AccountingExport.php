<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Reporting\Database\Factories\AccountingExportFactory;

/**
 * Book H3 FIN-12 §2/BR-FIN-12-014.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $target_system
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property int $journal_count
 * @property int $export_file_id
 * @property int $exported_by
 * @property Carbon $exported_at
 */
class AccountingExport extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AccountingExportFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'target_system', 'period_from', 'period_to', 'journal_count', 'export_file_id',
        'exported_by', 'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'exported_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AccountingExportFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Security\Database\Factories\ContractorWorkerFactory;

/**
 * Book H2 OPS-06 §2 ⭐/BR-OPS-06-002 — working near children requires
 * a police clearance on file. `id_number` is cast `encrypted`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $contractor_id
 * @property string $full_name
 * @property string|null $id_number
 * @property int|null $photo_file_id
 * @property Carbon|null $induction_completed_on
 * @property Carbon|null $police_clearance_on
 * @property bool $is_cleared
 * @property string|null $badge_number
 */
class ContractorWorker extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ContractorWorkerFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'contractor_id', 'full_name', 'id_number', 'photo_file_id', 'induction_completed_on',
        'police_clearance_on', 'is_cleared', 'badge_number',
    ];

    protected function casts(): array
    {
        return [
            'id_number' => 'encrypted',
            'induction_completed_on' => 'date',
            'police_clearance_on' => 'date',
            'is_cleared' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ContractorWorkerFactory::new();
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }
}

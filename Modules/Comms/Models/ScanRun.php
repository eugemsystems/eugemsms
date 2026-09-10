<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ScanRunFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-02 §2/BR-COM-02-002.
 *
 * @property int $id
 * @property int $school_id
 * @property int $rule_id
 * @property Carbon $ran_at
 * @property int $records_scanned
 * @property int $records_matched
 * @property int $notifications_dispatched
 * @property int|null $duration_ms
 * @property string $status
 * @property string|null $error
 */
class ScanRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ScanRunFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'rule_id', 'ran_at', 'records_scanned', 'records_matched',
        'notifications_dispatched', 'duration_ms', 'status', 'error',
    ];

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ScanRunFactory::new();
    }

    /**
     * @return BelongsTo<AutomationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}

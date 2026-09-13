<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Role;
use Modules\Finance\Database\Factories\ReminderScheduleFactory;

/**
 * Book B FIN-03 §2/BR-FIN-03-015/016. One rung of the chase ladder.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property int $days_after_due
 * @property int $minimum_balance_minor
 * @property string|null $currency
 * @property array<int, string> $channels
 * @property string $template_key
 * @property string $audience
 * @property int|null $escalate_to_role_id
 * @property bool $is_active
 */
class ReminderSchedule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReminderScheduleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'name', 'days_after_due', 'minimum_balance_minor', 'currency',
        'channels', 'template_key', 'audience', 'escalate_to_role_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days_after_due' => 'integer',
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReminderScheduleFactory::new();
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function escalateToRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'escalate_to_role_id');
    }
}

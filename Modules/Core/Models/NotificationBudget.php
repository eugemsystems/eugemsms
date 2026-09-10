<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\NotificationBudgetFactory;

/**
 * Book A CORE-09 §2/BR-CORE-09-007/008.
 *
 * @property int $id
 * @property int $school_id
 * @property string $period_month
 * @property string $channel
 * @property int|null $cap_minor
 * @property int $spent_minor
 * @property string $currency
 * @property int $warn_at_percent
 * @property bool $is_hard_stop
 */
class NotificationBudget extends Model
{
    /** @use HasFactory<NotificationBudgetFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'period_month', 'channel', 'cap_minor', 'spent_minor', 'currency',
        'warn_at_percent', 'is_hard_stop',
    ];

    protected function casts(): array
    {
        return [
            'is_hard_stop' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return NotificationBudgetFactory::new();
    }

    public function hasReachedCap(): bool
    {
        return $this->cap_minor !== null && $this->spent_minor >= $this->cap_minor;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\SettingScope;

/**
 * Book A CORE-04 §2/BR-CORE-04-006 — append-only.
 *
 * @property int $id
 * @property string $setting_key
 * @property SettingScope $scope_type
 * @property int $scope_id
 * @property string|null $old_value
 * @property string|null $new_value
 * @property int|null $changed_by
 * @property string|null $ip_address
 * @property Carbon $changed_at
 * @property-read User|null $changedBy
 */
class SettingChangeLog extends Model
{
    protected $table = 'setting_change_log';

    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'scope_type',
        'scope_id',
        'old_value',
        'new_value',
        'changed_by',
        'ip_address',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => SettingScope::class,
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('The setting change log is append-only and can never be edited.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('The setting change log is append-only and can never be deleted.');
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SettingValueFactory;
use Modules\Core\Domain\Support\Settings\SettingScope;

/**
 * Book A CORE-04 §2. `value` is the *raw stored string* — encrypted at
 * rest when the definition is `is_encrypted` (BR-CORE-04-005), decrypted
 * and cast only by `SettingResolver`, never read directly for display.
 *
 * @property int $id
 * @property string $setting_key
 * @property SettingScope $scope_type
 * @property int $scope_id
 * @property string|null $value
 * @property int|null $set_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $setBy
 */
class SettingValue extends Model
{
    /** @use HasFactory<SettingValueFactory> */
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'scope_type',
        'scope_id',
        'value',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => SettingScope::class,
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SettingValueFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}

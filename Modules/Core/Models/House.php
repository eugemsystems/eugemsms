<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\HouseFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-02 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string|null $colour
 * @property string|null $motto
 * @property int|null $housemaster_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $housemaster
 */
class House extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HouseFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'colour',
        'motto',
        'housemaster_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HouseFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function housemaster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'housemaster_id');
    }
}

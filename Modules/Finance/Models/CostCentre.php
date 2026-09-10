<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\SchoolSection;
use Modules\Finance\Database\Factories\CostCentreFactory;

/**
 * Book B FIN-01 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property int|null $section_id
 * @property int|null $manager_user_id
 * @property bool $is_profit_centre
 * @property bool $is_active
 */
class CostCentre extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CostCentreFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'parent_id', 'code', 'name', 'section_id', 'manager_user_id',
        'is_profit_centre', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_profit_centre' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CostCentreFactory::new();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<SchoolSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}

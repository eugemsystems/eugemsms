<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\AlumniHouseGroupFactory;

/**
 * Book K PPL-06 §2. A year group for reunions — membership is by
 * matching `graduation_year`, not a stored FK on `alumni`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $graduation_year
 * @property string|null $group_name
 * @property int|null $coordinator_alumnus_id
 */
class AlumniHouseGroup extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AlumniHouseGroupFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'graduation_year', 'group_name', 'coordinator_alumnus_id'];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AlumniHouseGroupFactory::new();
    }

    /**
     * @return BelongsTo<Alumnus, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Alumnus::class, 'coordinator_alumnus_id');
    }
}

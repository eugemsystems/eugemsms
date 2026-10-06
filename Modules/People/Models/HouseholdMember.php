<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\HouseholdMemberFactory;

/**
 * Book C PPL-03 §3. A learner or guardian belonging to a household, with dates.
 *
 * @property int $id
 * @property int $school_id
 * @property int $household_id
 * @property string $member_type
 * @property int $member_id
 * @property Carbon $joined_on
 * @property Carbon|null $left_on
 */
class HouseholdMember extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HouseholdMemberFactory> */
    use HasFactory;

    protected $table = 'household_members';

    public $timestamps = false;

    protected $fillable = ['school_id', 'household_id', 'member_type', 'member_id', 'joined_on', 'left_on'];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HouseholdMemberFactory::new();
    }
}

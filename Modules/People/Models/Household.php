<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\HouseholdFactory;

/**
 * Book C PPL-03 §3. A family grouping for sibling discounts and combined statements.
 *
 * @property int $id
 * @property int $school_id
 * @property string $ulid
 * @property string $name
 * @property int|null $head_guardian_id
 * @property string|null $address_line_1
 * @property string|null $city
 * @property bool $combined_statement
 * @property bool $sibling_discount_eligible
 */
class Household extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'households';

    public $timestamps = true;

    protected $fillable = ['school_id', 'name', 'head_guardian_id', 'address_line_1', 'city', 'combined_statement', 'sibling_discount_eligible'];

    protected function casts(): array
    {
        return [
            'combined_statement' => 'boolean',
            'sibling_discount_eligible' => 'boolean',
        ];
    }

    /**
     * @return HasMany<HouseholdMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HouseholdFactory::new();
    }
}

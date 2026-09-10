<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Boarding\Database\Factories\RecipeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book F BRD-04 §2/§3 ⭐ — quantities are per `base_servings`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property int $base_servings
 * @property string|null $preparation_notes
 * @property array<int, string>|null $allergen_flags
 * @property bool $is_vegetarian
 * @property bool $is_halal_suitable
 * @property bool $is_active
 */
class Recipe extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'category', 'base_servings', 'preparation_notes',
        'allergen_flags', 'is_vegetarian', 'is_halal_suitable', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allergen_flags' => 'array',
            'is_vegetarian' => 'boolean',
            'is_halal_suitable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RecipeFactory::new();
    }

    /**
     * @return HasMany<RecipeIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class, 'recipe_id');
    }
}

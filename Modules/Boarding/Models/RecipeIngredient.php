<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\RecipeIngredientFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-04 §2/§3 ⭐. `inventory_item_id` is a forward reference
 * to `FIN-09` (Book H, not built).
 *
 * @property int $id
 * @property int $school_id
 * @property int $recipe_id
 * @property int $inventory_item_id
 * @property string $quantity
 * @property string $unit
 * @property bool $is_substitutable
 * @property array<int, int>|null $substitute_item_ids
 * @property string $wastage_allowance_pct
 */
class RecipeIngredient extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RecipeIngredientFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'recipe_id', 'inventory_item_id', 'quantity', 'unit', 'is_substitutable', 'substitute_item_ids', 'wastage_allowance_pct'];

    protected function casts(): array
    {
        return [
            'is_substitutable' => 'boolean',
            'substitute_item_ids' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RecipeIngredientFactory::new();
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}

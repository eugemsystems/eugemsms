<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\MenuDayFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-04 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $cycle_id
 * @property int $cycle_day
 * @property string $meal
 * @property array<int, int> $recipe_ids
 * @property string|null $notes
 */
class MenuDay extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MenuDayFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'cycle_id', 'cycle_day', 'meal', 'recipe_ids', 'notes'];

    protected function casts(): array
    {
        return [
            'recipe_ids' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MenuDayFactory::new();
    }

    /**
     * @return BelongsTo<MenuCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MenuCycle::class);
    }

    /**
     * @return Collection<int, Recipe>
     */
    public function recipes(): Collection
    {
        return Recipe::query()->whereIn('id', $this->recipe_ids)->get();
    }
}

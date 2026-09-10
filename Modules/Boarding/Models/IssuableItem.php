<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Boarding\Database\Factories\IssuableItemFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-05 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property int|null $inventory_item_id
 * @property bool $is_returnable
 * @property bool $is_launderable
 * @property int|null $replacement_cost_minor
 * @property string $currency
 * @property int|null $expected_lifespan_terms
 * @property bool $requires_tagging
 */
class IssuableItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<IssuableItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'category', 'inventory_item_id', 'is_returnable', 'is_launderable',
        'replacement_cost_minor', 'currency', 'expected_lifespan_terms', 'requires_tagging',
    ];

    protected function casts(): array
    {
        return [
            'is_returnable' => 'boolean',
            'is_launderable' => 'boolean',
            'requires_tagging' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return IssuableItemFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Saas\Database\Factories\ProductTourFactory;

/**
 * Book J SAA-03 §2 — a code-owned tour definition, not tenant data.
 *
 * @property int $id
 * @property string $key
 * @property string $persona
 * @property array<int, array<string, mixed>> $steps
 */
class ProductTour extends Model
{
    /** @use HasFactory<ProductTourFactory> */
    use HasFactory;

    protected $primaryKey = 'id';

    protected $fillable = [
        'key', 'persona', 'steps',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProductTourFactory::new();
    }

    /**
     * @return HasMany<TourCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(TourCompletion::class, 'tour_key', 'key');
    }
}

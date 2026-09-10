<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Database\Factories\FeeStructureRuleFactory;

/**
 * Book B FIN-02 §2 ⭐/BR-FIN-02-001. WHO a structure applies to — every
 * row must match for the structure to be selected. No `school_id` of
 * its own: always reached through its owning `FeeStructure`.
 *
 * @property int $id
 * @property int $structure_id
 * @property string $attribute
 * @property string $operator
 * @property mixed $value
 * @property string|null $custom_field_key
 */
class FeeStructureRule extends Model
{
    /** @use HasFactory<FeeStructureRuleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'structure_id', 'attribute', 'operator', 'value', 'custom_field_key',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FeeStructureRuleFactory::new();
    }

    /**
     * @return BelongsTo<FeeStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'structure_id');
    }
}

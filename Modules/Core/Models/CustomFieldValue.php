<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\CustomFieldValueFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book A CORE-04 §2 — one column per `data_type` family, all nullable;
 * exactly one is populated per row, per `CustomFieldDefinition::data_type`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $definition_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $value_text
 * @property string|null $value_number
 * @property Carbon|null $value_date
 * @property bool|null $value_bool
 * @property array<string, mixed>|null $value_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomFieldDefinition $definition
 */
class CustomFieldValue extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CustomFieldValueFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'definition_id',
        'entity_type',
        'entity_id',
        'value_text',
        'value_number',
        'value_date',
        'value_bool',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_date' => 'datetime',
            'value_bool' => 'boolean',
            'value_json' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CustomFieldValueFactory::new();
    }

    /**
     * @return BelongsTo<CustomFieldDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }
}

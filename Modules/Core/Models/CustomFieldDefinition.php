<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\CustomFieldDefinitionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book A CORE-04 §2. BR-CORE-04-009: `key` is immutable after creation —
 * enforced here the same way `academic_state`/`financial_state` are
 * guarded on `Term` (Book A CORE-03), rather than merely by convention.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $entity_type
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property string $data_type
 * @property array<int, mixed>|null $options
 * @property string|null $validation_rules
 * @property bool $is_required
 * @property bool $is_searchable
 * @property bool $is_exposed_in_api
 * @property bool $is_printable
 * @property array<int, string>|null $visible_to_roles
 * @property string|null $group_label
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CustomFieldValue> $values
 */
class CustomFieldDefinition extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CustomFieldDefinitionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id',
        'entity_type',
        'key',
        'label',
        'description',
        'data_type',
        'options',
        'validation_rules',
        'is_required',
        'is_searchable',
        'is_exposed_in_api',
        'is_printable',
        'visible_to_roles',
        'group_label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_searchable' => 'boolean',
            'is_exposed_in_api' => 'boolean',
            'is_printable' => 'boolean',
            'visible_to_roles' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CustomFieldDefinitionFactory::new();
    }

    /**
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'definition_id');
    }

    protected static function booted(): void
    {
        static::updating(function (CustomFieldDefinition $definition): void {
            if ($definition->isDirty('key')) {
                throw new InvalidStateTransitionException('A custom field\'s key is immutable after creation.');
            }
        });
    }
}

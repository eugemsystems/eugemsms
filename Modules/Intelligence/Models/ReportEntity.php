<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Intelligence\Database\Factories\ReportEntityFactory;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-001/004.
 *
 * @property int $id
 * @property string $entity_key
 * @property string $module_code
 * @property string $base_model_class
 * @property bool $default_school_scoped
 * @property array<int, string>|null $allowed_join_entity_keys
 */
class ReportEntity extends Model
{
    /** @use HasFactory<ReportEntityFactory> */
    use HasFactory;

    protected $fillable = [
        'entity_key', 'module_code', 'base_model_class', 'default_school_scoped', 'allowed_join_entity_keys',
    ];

    protected function casts(): array
    {
        return [
            'default_school_scoped' => 'boolean',
            'allowed_join_entity_keys' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReportEntityFactory::new();
    }

    /**
     * The REAL query the whole reporting engine builds from — ⭐ never
     * a raw table name, always this entity's own Eloquent model, so
     * every existing scope (`BelongsToSchool` included) applies
     * automatically and unconditionally.
     *
     * @return Builder<Model>
     */
    public function baseModelQuery(): Builder
    {
        /** @var class-string<Model> $class */
        $class = $this->base_model_class;

        return $class::query();
    }
}

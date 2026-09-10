<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Intelligence\Database\Factories\ReportFieldFactory;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-001/002/003. A materialization of
 * `Modules\Intelligence\Domain\Registry\ReportFieldRegistry`.
 *
 * @property int $id
 * @property string $module_code
 * @property string $entity_key
 * @property string $field_key
 * @property string $label
 * @property string $data_type
 * @property bool $is_filterable
 * @property bool $is_groupable
 * @property bool $is_aggregatable
 * @property string $required_permission
 * @property bool $is_sensitive
 * @property array<int, mixed>|null $enum_options
 */
class ReportField extends Model
{
    /** @use HasFactory<ReportFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'module_code', 'entity_key', 'field_key', 'label', 'data_type',
        'is_filterable', 'is_groupable', 'is_aggregatable', 'required_permission',
        'is_sensitive', 'enum_options',
    ];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_groupable' => 'boolean',
            'is_aggregatable' => 'boolean',
            'is_sensitive' => 'boolean',
            'enum_options' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReportFieldFactory::new();
    }

    public function qualifiedColumn(): string
    {
        return $this->field_key;
    }
}

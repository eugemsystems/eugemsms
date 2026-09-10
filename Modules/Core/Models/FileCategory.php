<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Book A CORE-10 §2. Mirrors `FileCategoryRegistry` — see that
 * registry's own docblock.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string $module_code
 * @property array<int, string> $allowed_mimes
 * @property int $max_size_bytes
 * @property bool $is_sensitive
 * @property bool $generates_variants
 * @property bool $requires_expiry
 * @property int|null $retention_years
 */
class FileCategory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key', 'label', 'module_code', 'allowed_mimes', 'max_size_bytes', 'is_sensitive',
        'generates_variants', 'requires_expiry', 'retention_years',
    ];

    protected function casts(): array
    {
        return [
            'allowed_mimes' => 'array',
            'is_sensitive' => 'boolean',
            'generates_variants' => 'boolean',
            'requires_expiry' => 'boolean',
        ];
    }
}

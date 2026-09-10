<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\ApprovalChainFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-07 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $approvable_type
 * @property string $name
 * @property string|null $description
 * @property array<int, array{field: string, operator: string, value: mixed}>|null $condition_rules
 * @property bool $is_default
 * @property int $priority
 * @property bool $is_active
 */
class ApprovalChain extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ApprovalChainFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'approvable_type', 'name', 'description', 'condition_rules',
        'is_default', 'priority', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'condition_rules' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApprovalChainFactory::new();
    }

    /**
     * @return HasMany<ApprovalStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'chain_id')->orderBy('step_number');
    }
}

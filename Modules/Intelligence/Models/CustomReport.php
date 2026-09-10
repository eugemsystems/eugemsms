<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Intelligence\Database\Factories\CustomReportFactory;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-001/005.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $name
 * @property string|null $description
 * @property string $primary_entity_key
 * @property array<int, array{entity: string, field: string, alias?: string}> $selected_fields
 * @property array<int, mixed>|null $filters
 * @property array<int, mixed>|null $group_by
 * @property array<int, array{field: string, function: string}>|null $aggregations
 * @property array<int, mixed>|null $sort
 * @property string|null $chart_type
 * @property int $created_by
 * @property bool $is_active
 */
class CustomReport extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CustomReportFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'name', 'description', 'primary_entity_key', 'selected_fields',
        'filters', 'group_by', 'aggregations', 'sort', 'chart_type', 'created_by', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selected_fields' => 'array',
            'filters' => 'array',
            'group_by' => 'array',
            'aggregations' => 'array',
            'sort' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CustomReportFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ReportShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ReportShare::class, 'report_id');
    }
}

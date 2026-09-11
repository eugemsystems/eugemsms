<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\ChurnRiskFlagFactory;

/**
 * Book J SAA-03 §2/BR-SAA-03-008.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $flagged_at
 * @property array<int, array{indicator: string, plain_language: string, weight: float, contribution: float, source: string}> $contributing_factors
 * @property string $status
 * @property int|null $assigned_to
 */
class ChurnRiskFlag extends Model
{
    /** @use HasFactory<ChurnRiskFlagFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'flagged_at', 'contributing_factors', 'status', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'flagged_at' => 'datetime',
            'contributing_factors' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ChurnRiskFlagFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

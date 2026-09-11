<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\ApprovalChain;
use Modules\Finance\Database\Factories\DiscountSchemeFactory;

/**
 * Book K FIN-07 §2 ⭐ — the discount and scholarship catalogue.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $scheme_type
 * @property string $category
 * @property string $calculation_method
 * @property array<int, int>|null $applies_to_components
 * @property string|null $default_percent
 * @property int|null $default_amount_minor
 * @property string|null $currency
 * @property array<string, mixed>|null $tier_bands
 * @property bool $requires_means_assessment
 * @property bool $requires_academic_threshold
 * @property string|null $minimum_average_percent
 * @property bool $requires_approval
 * @property int|null $approval_chain_id
 * @property bool $is_sponsor_funded
 * @property int $contra_account_id
 * @property string|null $renewal_frequency
 * @property bool $is_active
 */
class DiscountScheme extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DiscountSchemeFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'scheme_type', 'category', 'calculation_method',
        'applies_to_components', 'default_percent', 'default_amount_minor', 'currency',
        'tier_bands', 'requires_means_assessment', 'requires_academic_threshold',
        'minimum_average_percent', 'requires_approval', 'approval_chain_id', 'is_sponsor_funded',
        'contra_account_id', 'renewal_frequency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_components' => 'array',
            'default_percent' => 'decimal:2',
            'default_amount_minor' => 'integer',
            'tier_bands' => 'array',
            'requires_means_assessment' => 'boolean',
            'requires_academic_threshold' => 'boolean',
            'minimum_average_percent' => 'decimal:2',
            'requires_approval' => 'boolean',
            'is_sponsor_funded' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DiscountSchemeFactory::new();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }

    /**
     * @return BelongsTo<ApprovalChain, $this>
     */
    public function approvalChain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class);
    }

    public function appliesToComponent(int $componentId): bool
    {
        return $this->applies_to_components === null || in_array($componentId, $this->applies_to_components, true);
    }
}

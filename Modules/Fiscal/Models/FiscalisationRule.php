<?php

declare(strict_types=1);

namespace Modules\Fiscal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Fiscal\Database\Factories\FiscalisationRuleFactory;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-002/003.
 *
 * @property int $id
 * @property int $school_id
 * @property string $rule_name
 * @property string $source_type
 * @property string|null $source_identifier
 * @property bool $is_fiscalisable
 * @property string $tax_type
 * @property float $tax_rate_percent
 * @property string|null $tax_code
 * @property string|null $rationale
 * @property int $priority
 * @property bool $is_active
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 */
class FiscalisationRule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FiscalisationRuleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'rule_name', 'source_type', 'source_identifier', 'is_fiscalisable', 'tax_type',
        'tax_rate_percent', 'tax_code', 'rationale', 'priority', 'is_active', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_fiscalisable' => 'boolean',
            'tax_rate_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FiscalisationRuleFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

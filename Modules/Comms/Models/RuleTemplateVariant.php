<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Database\Factories\RuleTemplateVariantFactory;

/**
 * Book I COM-02 §2/BR-COM-02-009/010.
 *
 * @property int $id
 * @property int $rule_id
 * @property string $variant_key
 * @property string $template_key
 * @property int $weight_percent
 * @property int $sent_count
 * @property int $opened_count
 * @property int $response_count
 */
class RuleTemplateVariant extends Model
{
    /** @use HasFactory<RuleTemplateVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'rule_id', 'variant_key', 'template_key', 'weight_percent', 'sent_count', 'opened_count', 'response_count',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RuleTemplateVariantFactory::new();
    }

    /**
     * @return BelongsTo<AutomationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}

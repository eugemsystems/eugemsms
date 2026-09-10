<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\AddRuleTemplateVariantData;
use Modules\Comms\Models\RuleTemplateVariant;
use Modules\Core\Domain\Actions\Action;

final class AddRuleTemplateVariantAction extends Action
{
    public function execute(AddRuleTemplateVariantData $data): RuleTemplateVariant
    {
        return $this->transaction(fn (): RuleTemplateVariant => RuleTemplateVariant::create([
            'rule_id' => $data->ruleId,
            'variant_key' => $data->variantKey,
            'template_key' => $data->templateKey,
            'weight_percent' => $data->weightPercent,
        ]));
    }
}

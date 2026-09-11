<?php

declare(strict_types=1);

namespace Modules\Saas\Providers;

use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book J SAA-01 — Licensing, Subscription & Entitlement.
 */
class SaasServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Saas';

    protected string $nameLower = 'saas';

    public function boot(): void
    {
        parent::boot();

        $this->registerSettingDefinitions();
    }

    private function registerSettingDefinitions(): void
    {
        SettingDefinitionRegistry::register('saas.usage_soft_warning_threshold_percent', [
            'module_code' => 'SAA',
            'group_key' => 'saas',
            'label' => 'Usage percentage of a plan limit at which a soft warning is sent (BR-SAA-01-003).',
            'data_type' => 'int',
            'default_value' => '90',
            'ui_control' => 'text',
            'lowest_scope' => 'system',
            'is_encrypted' => false,
            'sort_order' => 0,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\ConfirmStatutoryConfigurationData;
use Modules\Payroll\Domain\Events\StatutoryConfigActivated;
use Modules\Payroll\Models\StatutoryConfiguration;

/**
 * ACT-ConfirmStatutoryConfiguration (Book H3 PPL-05
 * §0.1/BR-PPL-05-002, AC-PPL-05-001). Unblocks payroll for every run
 * that depends on this configuration.
 */
final class ConfirmStatutoryConfigurationAction extends Action
{
    public function execute(ConfirmStatutoryConfigurationData $data): StatutoryConfiguration
    {
        $config = StatutoryConfiguration::findOrFail($data->statutoryConfigurationId);

        return $this->transaction(function () use ($config, $data): StatutoryConfiguration {
            $config->update(['confirmed_by' => $data->confirmedByUserId, 'confirmed_at' => Carbon::now()]);

            event(new StatutoryConfigActivated($config));

            return $config;
        });
    }
}

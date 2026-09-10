<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\CreateStatutoryConfigurationData;
use Modules\Payroll\Domain\Events\StatutoryConfigActivated;
use Modules\Payroll\Models\StatutoryConfiguration;

/**
 * ACT-CreateStatutoryConfiguration (Book H3 PPL-05 §0.1/§2
 * ⭐/BR-PPL-05-001/004). Marks any prior row for the same
 * (school_id, config_type, currency) as `superseded` — informational
 * only; `StatutoryConfigResolver` resolves by date range regardless
 * of this flag, so a historical payslip is never affected by it.
 */
final class CreateStatutoryConfigurationAction extends Action
{
    public function execute(CreateStatutoryConfigurationData $data): StatutoryConfiguration
    {
        return $this->transaction(function () use ($data): StatutoryConfiguration {
            StatutoryConfiguration::where('school_id', $data->schoolId)
                ->where('config_type', $data->configType)
                ->where('currency', $data->currency)
                ->where('status', 'active')
                ->update(['status' => 'superseded']);

            $config = StatutoryConfiguration::create([
                'school_id' => $data->schoolId,
                'config_type' => $data->configType,
                'currency' => $data->currency,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'configuration' => $data->configuration,
                'source_reference' => $data->sourceReference,
                'requires_confirmation' => $data->requiresConfirmation,
                'status' => 'active',
                'created_by' => $data->createdByUserId,
                'created_at' => Carbon::now(),
            ]);

            if (! $data->requiresConfirmation) {
                event(new StatutoryConfigActivated($config));
            }

            return $config;
        });
    }
}

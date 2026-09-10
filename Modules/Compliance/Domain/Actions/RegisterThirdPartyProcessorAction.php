<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\RegisterThirdPartyProcessorData;
use Modules\Compliance\Models\ThirdPartyProcessor;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RegisterThirdPartyProcessor (Book H3 CMP-03 §3/BR-CMP-03-014).
 * `ThirdPartyProcessor::isCrossBorder()` is the flag this rule asks
 * for — computed from `country`, never a separate stored boolean that
 * could drift from it.
 */
final class RegisterThirdPartyProcessorAction extends Action
{
    public function execute(RegisterThirdPartyProcessorData $data): ThirdPartyProcessor
    {
        return $this->transaction(fn (): ThirdPartyProcessor => ThirdPartyProcessor::create([
            'school_id' => $data->schoolId,
            'name' => $data->name,
            'processor_type' => $data->processorType,
            'data_shared' => $data->dataShared,
            'purpose' => $data->purpose,
            'country' => $data->country,
            'agreement_file_id' => $data->agreementFileId,
            'agreement_expires_on' => $data->agreementExpiresOn,
            'status' => 'active',
            'last_reviewed_on' => Carbon::now()->toDateString(),
        ]));
    }
}

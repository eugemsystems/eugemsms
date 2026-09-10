<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreatePrivacyNoticeData;
use Modules\Compliance\Models\PrivacyNotice;
use Modules\Core\Domain\Actions\Action;

final class CreatePrivacyNoticeAction extends Action
{
    public function execute(CreatePrivacyNoticeData $data): PrivacyNotice
    {
        return $this->transaction(fn (): PrivacyNotice => PrivacyNotice::create([
            'school_id' => $data->schoolId,
            'version' => $data->version,
            'title' => $data->title,
            'content' => $data->content,
            'effective_from' => $data->effectiveFrom,
            'requires_reconsent' => $data->requiresReconsent,
            'created_by' => $data->createdByUserId,
        ]));
    }
}

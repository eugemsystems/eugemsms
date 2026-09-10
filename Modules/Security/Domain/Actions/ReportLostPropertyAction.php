<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\ReportLostPropertyData;
use Modules\Security\Models\LostProperty;

/**
 * ACT-ReportLostProperty (Book H2 OPS-06 §2).
 */
final class ReportLostPropertyAction extends Action
{
    public function execute(ReportLostPropertyData $data): LostProperty
    {
        return $this->transaction(fn (): LostProperty => LostProperty::create([
            'school_id' => $data->schoolId,
            'found_on' => $data->foundOn->toDateString(),
            'description' => $data->description,
            'found_location' => $data->foundLocation,
            'found_by' => $data->foundByUserId,
            'photo_file_id' => $data->photoFileId,
            'status' => 'held',
        ]));
    }
}

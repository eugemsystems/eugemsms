<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\ClearExitChecklistItemData;
use Modules\People\Domain\Exceptions\UnknownExitChecklistItemException;
use Modules\People\Models\StaffExitChecklist;

final class ClearExitChecklistItemAction extends Action
{
    public function execute(ClearExitChecklistItemData $data): StaffExitChecklist
    {
        $checklist = StaffExitChecklist::findOrFail($data->checklistId);
        $items = $checklist->items;
        $found = false;

        foreach ($items as &$item) {
            if ($item['code'] === $data->itemCode) {
                $item['is_cleared'] = true;
                $item['cleared_by'] = $data->clearedByUserId;
                $item['cleared_at'] = Carbon::now()->toIso8601String();
                $found = true;

                break;
            }
        }
        unset($item);

        if (! $found) {
            throw UnknownExitChecklistItemException::forCode($data->itemCode, $checklist->id);
        }

        return $this->transaction(function () use ($checklist, $items): StaffExitChecklist {
            $checklist->update(['items' => $items]);

            return $checklist;
        });
    }
}

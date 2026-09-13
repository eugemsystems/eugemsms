<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\RecordDebtorChaseNoteData;
use Modules\Finance\Models\DebtorChaseNote;

final class RecordDebtorChaseNoteAction extends Action
{
    public function execute(RecordDebtorChaseNoteData $data): DebtorChaseNote
    {
        return $this->transaction(fn (): DebtorChaseNote => DebtorChaseNote::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'outcome' => $data->outcome,
            'note' => $data->note,
            'next_action_on' => $data->nextActionOn,
            'recorded_by' => $data->recordedByUserId,
            'created_at' => Carbon::now(),
        ]));
    }
}

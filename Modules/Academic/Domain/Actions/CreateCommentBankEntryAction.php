<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateCommentBankEntryData;
use Modules\Academic\Models\CommentBank;
use Modules\Core\Domain\Actions\Action;

final class CreateCommentBankEntryAction extends Action
{
    public function execute(CreateCommentBankEntryData $data): CommentBank
    {
        return $this->transaction(fn (): CommentBank => CommentBank::create([
            'school_id' => $data->schoolId,
            'scope' => $data->scope,
            'subject_id' => $data->subjectId,
            'grade_band' => $data->gradeBand,
            'text' => $data->text,
            'usage_count' => 0,
            'created_by' => $data->createdByUserId,
            'is_active' => true,
        ]));
    }
}

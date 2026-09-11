<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateQuestionBankItemData;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Actions\Action;

final class CreateQuestionBankItemAction extends Action
{
    public function execute(CreateQuestionBankItemData $data): QuestionBankItem
    {
        return $this->transaction(fn (): QuestionBankItem => QuestionBankItem::create([
            'school_id' => $data->schoolId,
            'subject_id' => $data->subjectId,
            'topic' => $data->topic,
            'syllabus_objective_ref' => $data->syllabusObjectiveRef,
            'item_type' => $data->itemType,
            'difficulty' => $data->difficulty,
            'prompt' => $data->prompt,
            'prompt_image_file_id' => $data->promptImageFileId,
            'options' => $data->options,
            'correct_answer' => $data->correctAnswer,
            'max_mark' => $data->maxMark,
            'is_auto_markable' => $data->isAutoMarkable,
            'usage_count' => 0,
            'created_by' => $data->createdByUserId,
            'is_active' => true,
        ]));
    }
}

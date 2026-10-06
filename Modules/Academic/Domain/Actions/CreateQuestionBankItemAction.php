<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateQuestionBankItemData;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;

final class CreateQuestionBankItemAction extends Action
{
    public function execute(CreateQuestionBankItemData $data): QuestionBankItem
    {
        $this->assertValid($data);

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

    private function assertValid(CreateQuestionBankItemData $data): void
    {
        $types = ['mcq', 'true_false', 'matching', 'fill_in', 'short_answer', 'essay', 'file_upload'];

        if (! in_array($data->itemType, $types, true) || ! in_array($data->difficulty, ['easy', 'medium', 'hard'], true)) {
            throw new InvalidArgumentException('That question type or difficulty is not recognised.');
        }

        if (trim($data->prompt) === '' || $data->maxMark <= 0) {
            throw new InvalidArgumentException('A question needs a prompt and a mark above zero.');
        }

        Subject::query()->where('school_id', $data->schoolId)->findOrFail($data->subjectId);

        // Whether an item marks itself is a property of its type, not the caller's choice: an
        // essay can never be "auto-markable", and an objective item always is.
        if (in_array($data->itemType, ['essay', 'file_upload'], true) && $data->isAutoMarkable) {
            throw new InvalidArgumentException('A written or file-upload question cannot be auto-marked.');
        }

        if (in_array($data->itemType, ['mcq', 'true_false', 'matching'], true) && ! $data->isAutoMarkable) {
            throw new InvalidArgumentException('An objective question is always auto-marked.');
        }

        if ($data->isAutoMarkable && ($data->correctAnswer === null || $data->correctAnswer === [])) {
            throw new InvalidArgumentException('An auto-marked question needs its correct answer.');
        }

        if (in_array($data->itemType, ['mcq', 'matching'], true) && count($data->options ?? []) < 2) {
            throw new InvalidArgumentException('This question needs at least two options.');
        }
    }
}

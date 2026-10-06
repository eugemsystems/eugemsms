<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SetQuestionActive (Book K ACA-09 §5, question bank). Retiring a
 * question only stops it being drawn into new tests; tests already built
 * keep it, so no scheduled paper changes underneath its candidates.
 */
final class SetQuestionActiveAction extends Action
{
    public function execute(int $questionId, bool $isActive): QuestionBankItem
    {
        $question = QuestionBankItem::findOrFail($questionId);

        return $this->transaction(function () use ($question, $isActive): QuestionBankItem {
            $question->update(['is_active' => $isActive]);

            return $question->fresh();
        });
    }
}

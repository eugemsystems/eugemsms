<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Document;
use Modules\Core\Models\School;
use Modules\Finance\Domain\DataObjects\GenerateStatementData;
use Modules\Finance\Domain\Support\FinanceDocumentTemplates as T;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * ACT-RenderStatementDocument (Book B FIN-03 BR-FIN-03-009/021). A statement for a
 * date range, built from journal lines (never cached invoice figures) and
 * rendered from the template now in force. Each call makes a new, verifiable
 * document — a statement is a point-in-time artefact, not an issued record.
 */
final class RenderStatementDocumentAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly GenerateStatementAction $generateStatement,
        private readonly RenderFinanceDocumentAction $render,
    ) {}

    public function execute(GenerateStatementData $data, int $generatedByUserId): Document
    {
        $statement = $this->generateStatement->execute($data);

        $name = match ($data->subledgerType) {
            'student' => Student::query()->find($data->subledgerId)?->fullName(),
            'guardian' => Guardian::query()->find($data->subledgerId)?->displayName(),
            default => null,
        } ?? "{$data->subledgerType} #{$data->subledgerId}";

        return $this->render->execute($data->schoolId, T::STATEMENT, [
            'school' => ['name' => (string) School::findOrFail($data->schoolId)->name],
            'account' => ['name' => $name],
            'currency' => $data->currency,
            'from' => $data->from->toDateString(),
            'to' => $data->to->toDateString(),
            'opening' => T::money($statement->openingBalanceMinor),
            'closing' => T::money($statement->closingBalanceMinor),
            'lines' => array_map(fn ($l): array => [
                'date' => substr($l->effectiveAt, 0, 10), 'reference' => $l->journalNumber, 'narration' => $l->narration,
                'debit' => $l->direction === 'debit' ? T::money($l->amountMinor) : '', 'credit' => $l->direction === 'credit' ? T::money($l->amountMinor) : '',
                'balance' => T::money($l->runningBalanceMinor),
            ], $statement->lines),
        ], $generatedByUserId, null, null, verifiable: true);
    }
}

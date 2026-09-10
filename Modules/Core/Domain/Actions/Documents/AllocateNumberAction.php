<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Events\Documents\NumberAllocated;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Documents\NumberPatternFormatter;
use Modules\Core\Models\AllocatedNumber;
use Modules\Core\Models\NumberingSeries;

/**
 * ACT-AllocateNumber (Book A CORE-06, "the numbering allocation
 * algorithm" / BR-CORE-06-001..004). The whole gapless guarantee lives
 * in the row lock: lockForUpdate() serialises concurrent allocations
 * against the same series so two requests can never walk away with
 * the same sequence (AC-CORE-06-001). Must run inside the SAME
 * transaction as the document it numbers (BR-CORE-06-001's "critical
 * detail"). This Action still wraps itself in transaction() like every
 * other Action here — when called from inside GenerateDocumentAction's
 * own transaction, Laravel nests it as a savepoint rather than a
 * second real transaction, and the row lock (tied to the outermost
 * transaction, not the savepoint) still holds until that one commits,
 * so the guarantee is unaffected either way.
 */
final class AllocateNumberAction extends Action
{
    public function __construct(
        private readonly NumberPatternFormatter $formatter,
    ) {}

    public function execute(AllocateNumberData $data): AllocatedNumber
    {
        return $this->transaction(function () use ($data): AllocatedNumber {
            $series = NumberingSeries::withoutGlobalScopes()
                ->where('school_id', $data->schoolId)
                ->where('document_type', $data->documentType)
                ->forPeriod($data->academicYearId, $data->termId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($series === null) {
                throw new class("No active numbering series for [{$data->documentType}] in this period.") extends DomainException
                {
                    public function errorCode(): string
                    {
                        return 'NUMBERING_SERIES_NOT_FOUND';
                    }
                };
            }

            $sequence = $series->next_sequence;
            $formatted = $this->formatter->format($series, $sequence);

            $series->increment('next_sequence');

            $allocated = AllocatedNumber::create([
                'school_id' => $data->schoolId,
                'series_id' => $series->id,
                'sequence' => $sequence,
                'formatted_number' => $formatted,
                'document_type' => $data->documentType,
                'documentable_type' => $data->documentableType,
                'documentable_id' => $data->documentableId,
                'status' => 'allocated',
                'allocated_by' => $data->allocatedByUserId,
                'allocated_at' => Carbon::now(),
            ]);

            event(new NumberAllocated($allocated));

            return $allocated;
        });
    }
}

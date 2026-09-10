<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Models\NumberingSeries;

final class CreateNumberingSeriesAction extends Action
{
    public function execute(CreateNumberingSeriesData $data): NumberingSeries
    {
        $exists = NumberingSeries::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('document_type', $data->documentType)
            ->where('academic_year_id', $data->academicYearId)
            ->where('term_id', $data->termId)
            ->exists();

        if ($exists) {
            throw new DuplicateRecordException(
                "A numbering series for [{$data->documentType}] already exists for this period.",
                ['document_type' => $data->documentType],
            );
        }

        return $this->transaction(fn (): NumberingSeries => NumberingSeries::create([
            'school_id' => $data->schoolId,
            'document_type' => $data->documentType,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'pattern' => $data->pattern,
            'prefix' => $data->prefix,
            'next_sequence' => 1,
            'sequence_padding' => $data->sequencePadding,
            'reset_policy' => $data->resetPolicy,
            'is_active' => true,
        ]));
    }
}

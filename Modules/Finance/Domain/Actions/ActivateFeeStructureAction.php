<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\ActivateFeeStructureData;
use Modules\Finance\Domain\Events\FeeStructureVersioned;
use Modules\Finance\Models\FeeStructure;

/**
 * ACT-ActivateFeeStructure (Book B FIN-02 §2/BR-FIN-02-011). Activating
 * a structure supersedes whatever other `active` row shares its
 * school/year/term/name — that row is the version this one replaces,
 * never edited, never deleted.
 */
final class ActivateFeeStructureAction extends Action
{
    public function execute(ActivateFeeStructureData $data): FeeStructure
    {
        $structure = FeeStructure::findOrFail($data->structureId);

        return $this->transaction(function () use ($structure, $data): FeeStructure {
            $previous = FeeStructure::query()
                ->where('school_id', $structure->school_id)
                ->where('academic_year_id', $structure->academic_year_id)
                ->where('term_id', $structure->term_id)
                ->where('name', $structure->name)
                ->where('status', 'active')
                ->where('id', '!=', $structure->id)
                ->first();

            $previous?->update(['status' => 'superseded', 'updated_by' => $data->activatedByUserId]);

            $structure->update([
                'status' => 'active',
                'approved_by' => $data->activatedByUserId,
                'approved_at' => Carbon::now(),
                'updated_by' => $data->activatedByUserId,
            ]);

            event(new FeeStructureVersioned($structure, $previous));

            return $structure;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\StartLibraryStockTakeData;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Core\Domain\Actions\Action;

final class StartLibraryStockTakeAction extends Action
{
    public function execute(StartLibraryStockTakeData $data): LibraryStockTake
    {
        $expectedCount = LibraryCopy::query()
            ->where('school_id', $data->schoolId)
            ->where('status', '!=', 'withdrawn')
            ->count();

        return $this->transaction(fn (): LibraryStockTake => LibraryStockTake::create([
            'school_id' => $data->schoolId,
            'conducted_on' => Carbon::today(),
            'expected_count' => $expectedCount,
            'scanned_count' => 0,
            'missing_count' => 0,
            'scanned_copy_ids' => [],
            'confirmatory_pass_done' => false,
            'status' => 'in_progress',
        ]));
    }
}

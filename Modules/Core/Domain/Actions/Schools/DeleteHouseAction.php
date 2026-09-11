<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\DeleteHouseData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\House;

/**
 * ACT-DeleteHouse (Book A CORE-02 §5, admin UI follow-up). A hard delete
 * — `houses` carries no soft-delete column — refused while any student
 * is still assigned to it (`students.house_id`), following the same
 * "throw a domain exception if unsafe" precedent as
 * `DeleteAcademicYearAction`/`DeactivateCustomFieldAction`. Queried via
 * the query builder rather than the `Student` model (Book C/People) to
 * avoid a cross-module model dependency from Book A — a soft-deleted
 * student (`students.deleted_at`) is excluded, matching what
 * `Student`'s own `SoftDeletes` scope would otherwise do for free.
 */
final class DeleteHouseAction extends Action
{
    public function execute(DeleteHouseData $data): void
    {
        $house = House::withoutGlobalScopes()
            ->where('id', $data->houseId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        $hasStudents = DB::table('students')->where('house_id', $house->id)->whereNull('deleted_at')->exists();

        if ($hasStudents) {
            throw new InvalidStateTransitionException(
                'This house has students assigned to it and cannot be deleted.',
                ['house_id' => $house->id],
            );
        }

        $this->transaction(fn () => $house->delete());
    }
}

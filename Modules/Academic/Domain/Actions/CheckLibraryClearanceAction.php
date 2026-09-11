<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\LibraryClearanceResult;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckLibraryClearance (Book K ACA-10 §4/BR-ACA-10-008). A
 * standalone, callable check — not wired into
 * `Modules\People\Domain\Actions\WithdrawStudentAction`, exactly the
 * same deferral `Modules\Boarding\Domain\Actions\CheckLinenClearanceAction`
 * documents for itself (Book F BRD-05). Any future general-clearance
 * workflow calls this rather than duplicating the query. Only `active`
 * / `overdue` loans block clearance — in this module `fined`/`lost`
 * are only ever reached together with a `fine_charge_id` already set
 * (see `ReturnLoanAction`/`ReportLoanLostAction`/
 * `ProcessBulkTextbookReturnAction`), so they're always already
 * resolved financially by the time they exist, exactly like a charged
 * item in `BRD-05`'s own linen check.
 */
final class CheckLibraryClearanceAction extends Action
{
    public function execute(int $schoolId, string $borrowerType, int $borrowerId): LibraryClearanceResult
    {
        $outstanding = Loan::query()
            ->where('school_id', $schoolId)
            ->where('borrower_type', $borrowerType)
            ->where('borrower_id', $borrowerId)
            ->whereIn('status', ['active', 'overdue'])
            ->pluck('id')
            ->all();

        return new LibraryClearanceResult(
            isClear: $outstanding === [],
            outstandingLoanIds: $outstanding,
        );
    }
}

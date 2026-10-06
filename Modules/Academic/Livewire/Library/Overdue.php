<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Library;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\SendOverdueReminderAction;
use Modules\Academic\Domain\DataObjects\SendOverdueReminderData;
use Modules\Academic\Livewire\Concerns\ChecksPermissions;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * `Academic\Library\Overdue` (Book K ACA-10 §5, `library.view`). Loans past
 * their due date with the fine they would attract today (daily rate times
 * days late, capped at the replacement cost). A reminder goes out through the
 * notification bus before any fine is charged (BR-ACA-10-004); sending one
 * needs `library.circulate`.
 */
#[Title('Overdue loans')]
#[Layout('layouts.app')]
final class Overdue extends Component
{
    use AuthorizesPermissions;
    use ChecksPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('library.view');
    }

    public function remind(int $loanId): void
    {
        $this->authorizePermission('library.circulate');

        $loan = Loan::query()->where('status', 'active')->whereDate('due_on', '<', now()->toDateString())->find($loanId);

        if ($loan === null) {
            $this->toast(__('That loan is not overdue.'), 'danger');

            return;
        }

        $sent = app(SendOverdueReminderAction::class)->execute(new SendOverdueReminderData($loan->id));

        $this->toast($sent ? __('Reminder sent.') : __('This borrower cannot be reached in-app.'), $sent ? 'success' : 'warning');
    }

    public function render(): View
    {
        $dailyRate = (int) app(SettingResolver::class)->get('library.daily_fine_rate_minor', new ScopeChain(schoolId: $this->school->id));

        $loans = Loan::query()->with('copy.item')->where('status', 'active')->whereDate('due_on', '<', now()->toDateString())->orderBy('due_on')->limit(300)->get();

        return view('academic::library.overdue', [
            'loans' => $loans,
            'dailyRate' => $dailyRate,
            'students' => Student::query()->whereIn('id', $loans->where('borrower_type', 'student')->pluck('borrower_id'))->get()->keyBy('id'),
            'staff' => Staff::query()->whereIn('id', $loans->where('borrower_type', 'staff')->pluck('borrower_id'))->get()->keyBy('id'),
            'canRemind' => $this->holds('library.circulate'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Discounts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\RevokeAwardAction;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Models\Student;

/**
 * `Finance\Awards\Index` (Book K FIN-07 §5, `finance.award.view`). Awards by
 * scheme, learner and status. Revoking needs `finance.award.revoke ⚠`, a
 * reason, and takes effect from the chosen date forward — already-billed
 * terms are never re-invoiced (BR-FIN-07-012).
 */
#[Title('Awards')]
#[Layout('layouts.app')]
final class AwardList extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $statusFilter = '';

    public ?int $schemeFilter = null;

    public string $search = '';

    public ?int $revokingId = null;

    public string $reason = '';

    public string $effectiveTo = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.award.view');
    }

    public function beginRevoke(int $awardId): void
    {
        $this->authorizePermission('finance.award.revoke');

        $this->revokingId = DiscountAward::query()->whereIn('status', ['active', 'suspended', 'pending_approval'])->findOrFail($awardId)->id;
        $this->reason = '';
        $this->effectiveTo = now()->toDateString();
        $this->resetErrorBag();
    }

    public function revoke(): void
    {
        $this->authorizePermission('finance.award.revoke');
        $this->resetErrorBag();

        $this->validate(['reason' => ['required', 'string', 'max:255'], 'effectiveTo' => ['required', 'date']]);

        $award = DiscountAward::query()->findOrFail($this->revokingId);

        try {
            app(RevokeAwardAction::class)->execute($award->id, $this->reason, Carbon::parse($this->effectiveTo));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->revokingId = null;
        $this->toast(__('Award revoked from the chosen date.'));
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $awards = DiscountAward::query()
            ->when(in_array($this->statusFilter, ['pending_approval', 'active', 'suspended', 'ended', 'revoked'], true), fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->schemeFilter !== null, fn ($q) => $q->where('scheme_id', $this->schemeFilter))
            ->when($term !== '', fn ($q) => $q->whereIn('student_id', Student::query()->where(fn ($s) => $s->where('admission_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->select('id')))
            ->orderByDesc('id')->limit(200)->get();

        $user = auth()->user();
        $resolver = app(PermissionScopeResolver::class);

        return view('finance::discounts.awards', [
            'awards' => $awards,
            'students' => Student::query()->whereIn('id', $awards->pluck('student_id'))->get()->keyBy('id'),
            'schemes' => DiscountScheme::query()->orderBy('code')->get(['id', 'code', 'name']),
            'canRevoke' => $user !== null && $resolver->has($user, 'finance.award.revoke', PermissionScope::Own),
        ]);
    }
}

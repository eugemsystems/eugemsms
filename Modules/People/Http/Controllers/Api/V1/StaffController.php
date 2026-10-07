<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Models\Staff;

/**
 * `GET /api/v1/staff` and `.../staff/{ulid}` (Book C PPL-04 §6). Compensation fields are absent
 * from the response entirely for a caller without `people.staff.view_compensation`
 * (AC-PPL-04-009) — the same field-is-absent-not-hidden rule `People\Staff\Show` already applies,
 * reused here rather than re-decided.
 */
final class StaffController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'per_page' => ['nullable', 'integer'], 'page' => ['nullable', 'integer']]);

        $query = Staff::query()->orderBy('last_name')->orderBy('first_name');
        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('staff_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"));
        }

        $page = $query->paginate(ApiResponse::perPage($request->integer('per_page') ?: null));

        return ApiResponse::page($page->getCollection()->map(fn (Staff $staff): array => [
            'id' => $staff->ulid,
            'staff_number' => $staff->staff_number,
            'name' => $staff->fullName(),
            'staff_category' => $staff->staff_category,
            'status' => $staff->status,
        ])->values()->all(), $page);
    }

    public function show(Request $request, string $staff): JsonResponse
    {
        $found = Staff::query()->where('ulid', $staff)->first();
        abort_if($found === null, 404);

        $row = [
            'id' => $found->ulid,
            'staff_number' => $found->staff_number,
            'first_name' => $found->first_name,
            'last_name' => $found->last_name,
            'staff_category' => $found->staff_category,
            'department_id' => $found->department_id,
            'post_id' => $found->post_id,
            'is_teaching' => $found->is_teaching,
            'status' => $found->status,
            'joined_on' => $found->joined_on->toDateString(),
        ];

        /** @var User $user */
        $user = $request->user();

        if (app(PermissionScopeResolver::class)->has($user, 'people.staff.view_compensation', PermissionScope::Own)) {
            $row['bank_name'] = $found->bank_name;
            $row['bank_branch'] = $found->bank_branch;
            $row['bank_account_number'] = $found->bank_account_number;
            $row['bank_account_currency'] = $found->bank_account_currency;

            $contract = $found->activeContract();
            $row['active_contract'] = $contract === null ? null : [
                'basic_salary_minor' => $contract->basic_salary_minor,
                'salary_currency' => $contract->salary_currency,
            ];
        }

        return ApiResponse::ok($row);
    }
}

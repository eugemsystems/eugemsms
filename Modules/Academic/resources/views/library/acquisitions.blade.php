<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Library acquisitions') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Request new titles. An approved request goes to procurement as a purchase requisition.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Title') }}</th><th class="text-end">{{ __('Copies') }}</th><th class="text-end">{{ __('Est. cost') }}</th><th>{{ __('Requested by') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr wire:key="rq-{{ $request->id }}">
                            <td>{{ $request->requested_title }}</td><td class="text-end">{{ $request->copies_requested }}</td>
                            <td class="text-end">{{ $request->estimated_cost_minor === null ? '—' : number_format($request->estimated_cost_minor / 100, 2) }}</td>
                            <td class="small">{{ $request->requestedBy?->name }}</td><td><span class="badge text-bg-light border">{{ $request->status }}</span></td>
                            <td class="text-end text-nowrap">@if ($canApprove && $request->status === 'requested')
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startApproval({{ $request->id }})">{{ __('Approve') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="reject({{ $request->id }})" wire:confirm="{{ __('Reject this request?') }}">{{ __('Reject') }}</button>
                            @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No requests yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div></div>
        </div>
        <div class="col-xl-4">
            @if ($approvingId !== null)
                <div class="card mb-3 border-primary"><div class="card-header">{{ __('Send to procurement') }}</div><div class="card-body">
                    <select class="form-select form-select-sm mb-2" wire:model="departmentId"><option value="">{{ __('Department…') }}</option>@foreach ($departments as $department) <option value="{{ $department->id }}">{{ $department->name }}</option> @endforeach</select>
                    <select class="form-select form-select-sm mb-2" wire:model="costCentreId"><option value="">{{ __('Cost centre…') }}</option>@foreach ($costCentres as $centre) <option value="{{ $centre->id }}">{{ $centre->name }}</option> @endforeach</select>
                    @error('departmentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="approve">{{ __('Approve') }}</button>
                </div></div>
            @endif
            <div class="card"><div class="card-header">{{ __('Request a title') }}</div><div class="card-body">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="requestedTitle" placeholder="{{ __('Title and edition') }}">
                @error('requestedTitle') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div class="row g-2 mb-2"><div class="col-4"><input type="number" min="1" class="form-control form-control-sm" wire:model="copies"></div><div class="col-8"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="estimatedCost" placeholder="{{ __('Estimated total cost') }}"></div></div>
                <button type="button" class="btn btn-primary btn-sm" wire:click="submit">{{ __('Submit') }}</button>
            </div></div>
        </div>
    </div>
</div>

<div>
    <h4 class="mb-1">{{ __('Qualifications') }}</h4>
    <p class="text-body-secondary mb-4">{{ $staff->fullName() }} · <a href="{{ route('people.staff.show', [$school, $staff]) }}" wire:navigate>{{ __('Back to profile') }}</a></p>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Qualification') }}</th><th>{{ __('Institution') }}</th><th>{{ __('Year') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>@forelse ($rows as $row) <tr wire:key="q-{{ $row->id }}"><td>{{ $row->title }} <span class="small text-body-secondary">{{ $row->qualification_type }} {{ $row->grade_class }}</span>@if ($row->subjects) <div class="small">{{ implode(', ', $row->subjects) }}</div> @endif</td><td>{{ $row->institution }} <span class="small text-body-secondary">{{ $row->country }}</span></td><td>{{ $row->year_obtained }}</td><td>@if ($row->is_verified) <span class="badge text-bg-success">{{ __('verified') }}</span> @elseif ($row->certificate_file_id) <span class="badge text-bg-light border">{{ __('certificate on file') }}</span> @else <span class="badge text-bg-warning">{{ __('no certificate') }}</span> @endif</td><td class="text-end">@if (! $row->is_verified && $row->certificate_file_id) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="verify({{ $row->id }})">{{ __('Verify') }}</button> @endif</td></tr> @empty <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('None recorded.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Add a qualification') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="type">@foreach ($types as $t) <option value="{{ $t }}">{{ ucfirst($t) }}</option> @endforeach</select>
            <input type="text" class="form-control form-control-sm mb-2" wire:model="title" placeholder="{{ __('Title') }}">@error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="institution" placeholder="{{ __('Institution') }}">
            <div class="row g-2 mb-2"><div class="col-4"><input type="text" maxlength="2" class="form-control form-control-sm" wire:model="country"></div><div class="col-4"><input type="number" class="form-control form-control-sm" wire:model="year" placeholder="{{ __('Year') }}"></div><div class="col-4"><input type="text" class="form-control form-control-sm" wire:model="gradeClass" placeholder="{{ __('Class') }}"></div></div>
            <input type="text" class="form-control form-control-sm mb-2" wire:model="subjects" placeholder="{{ __('Teaching subjects, comma separated') }}">
            <input type="file" class="form-control form-control-sm mb-2" wire:model="certificate">@error('certificate') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="add" wire:loading.attr="disabled">{{ __('Add') }}</button>
        </div></div></div>
    </div>
</div>

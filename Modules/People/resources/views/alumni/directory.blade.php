<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Alumni directory') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Created automatically when a learner graduates. Anyone who has opted out of contact is marked — do not approach them.') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="graduationYear"><option value="">{{ __('All year groups') }}</option>@foreach ($years as $year) <option value="{{ $year->graduation_year }}">{{ $year->group_name ?? $year->graduation_year }}</option> @endforeach</select>
        <select class="form-select form-select-sm w-auto" wire:model.live="statusFilter"><option value="">{{ __('Any status') }}</option>@foreach (['active', 'unreachable', 'deceased', 'opted_out'] as $s) <option value="{{ $s }}">{{ __(ucfirst(str_replace('_', ' ', $s))) }}</option> @endforeach</select>
        <input type="search" class="form-control form-control-sm w-auto" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name or admission number') }}">
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Admission no.') }}</th><th>{{ __('Class of') }}</th><th>{{ __('Occupation') }}</th><th>{{ __('City') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($alumni as $alumnus)
                    <tr wire:key="al-{{ $alumnus->id }}">
                        <td>{{ $students[$alumnus->student_id]?->fullName() ?? '—' }} @if ($alumnus->is_notable) <i class="ri ri-star-fill text-warning" title="{{ __('Notable alumnus') }}"></i> @endif</td>
                        <td>{{ $alumnus->admission_number }}</td><td>{{ $alumnus->graduation_year }}</td>
                        <td>{{ $alumnus->current_occupation ?? '—' }}</td><td>{{ $alumnus->current_city ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ ['active' => 'success', 'opted_out' => 'danger', 'deceased' => 'dark', 'unreachable' => 'warning'][$alumnus->status] ?? 'secondary' }}">{{ $alumnus->status === 'opted_out' ? __('Do not contact') : __(ucfirst($alumnus->status)) }}</span></td>
                        <td class="text-end"><a href="{{ route('alumni.directory.show', [$school, $alumnus->id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No alumni match.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>

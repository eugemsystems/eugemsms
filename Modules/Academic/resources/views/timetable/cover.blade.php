<div>
    <h4 class="mb-1">{{ __('Daily cover') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Today\'s absences and substitutions.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Absent teacher') }}</th><th>{{ __('Status') }}</th><th>{{ __('Cover') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse ($substitutions as $substitution)
                        <tr wire:key="sub-{{ $substitution->id }}" class="{{ $substitution->status === 'pending' ? 'table-danger' : '' }}">
                            <td>{{ $substitution->absentStaff?->first_name }} {{ $substitution->absentStaff?->last_name }}</td>
                            <td><span class="badge text-bg-{{ $substitution->status === 'assigned' ? 'success' : 'danger' }}">{{ ucfirst($substitution->status) }}</span></td>
                            <td>
                                @if ($substitution->coverStaff)
                                    {{ $substitution->coverStaff->first_name }} {{ $substitution->coverStaff->last_name }}
                                @else
                                    <select class="form-select form-select-sm" wire:model="selectedCover.{{ $substitution->id }}">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach (($suggestions[$substitution->id] ?? []) as $staffId)
                                            <option value="{{ $staffId }}">{{ $staffNames[$staffId] ?? "#{$staffId}" }}</option>
                                        @endforeach
                                        @foreach ($staffNames as $id => $name)
                                            @if (!in_array($id, $suggestions[$substitution->id] ?? []))
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td>
                                @if (! $substitution->coverStaff)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="suggest({{ $substitution->id }})">{{ __('Suggest') }}</button>
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="assign({{ $substitution->id }})">{{ __('Assign') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No substitutions today.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

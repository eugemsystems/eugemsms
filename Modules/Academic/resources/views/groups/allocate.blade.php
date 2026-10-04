<div>
    <h4 class="mb-1">{{ __('Set allocation') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Move a learner between sets for a subject — one membership per subject per term.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Current memberships') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Group') }}</th><th>{{ __('Since') }}</th></tr></thead>
                        <tbody>
                            @forelse ($memberships as $membership)
                                <tr wire:key="membership-{{ $membership->id }}">
                                    <td>{{ $membership->student?->first_name }} {{ $membership->student?->last_name }}</td>
                                    <td>{{ $membership->teachingGroup?->subject?->name }}</td>
                                    <td>{{ $membership->teachingGroup?->code }}</td>
                                    <td>{{ $membership->effective_from->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No memberships yet this term.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Assign to group') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('studentId') is-invalid @enderror" wire:model="studentId">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Student') }}</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('teachingGroupId') is-invalid @enderror" wire:model="teachingGroupId">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->code }} — {{ $group->subject?->name }} ({{ $group->current_count }}/{{ $group->capacity ?? '∞' }})</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Teaching group') }}</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" wire:model="acknowledgeCapacityWarning">
                                <label class="form-check-label">{{ __('Acknowledge over-capacity warning, if any') }}</label>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary mt-3" wire:click="assign">{{ __('Assign') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

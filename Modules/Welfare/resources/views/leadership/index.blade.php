<div>
    <h4 class="mb-1">{{ __('Student leadership') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($leaderships as $leadership)
                                <tr wire:key="leadership-{{ $leadership->id }}">
                                    <td>{{ $leadership->student?->first_name }} {{ $leadership->student?->last_name }}</td>
                                    <td>{{ $leadership->role_title }}</td>
                                    <td><span class="badge text-bg-{{ $leadership->status === 'active' ? 'success' : 'secondary' }}">{{ $leadership->status }}</span></td>
                                    <td>
                                        @if ($leadership->status === 'active')
                                            @if ($revokingLeadershipId === $leadership->id)
                                                <div class="d-flex gap-2">
                                                    <input type="text" class="form-control form-control-sm" wire:model="revocationReason" placeholder="{{ __('Reason') }}">
                                                    <button type="button" class="btn btn-sm btn-danger" wire:click="revoke({{ $leadership->id }})">{{ __('Confirm') }}</button>
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="$set('revokingLeadershipId', {{ $leadership->id }})">{{ __('Revoke') }}</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No leadership appointments.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Appoint') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="roleTitle" placeholder="{{ __('Role title — e.g. \'Head Boy\'') }}">
                    <select class="form-select mb-2" wire:model="scopeType">
                        <option value="">{{ __('Scope (optional)') }}</option>
                        <option value="school">{{ __('School') }}</option>
                        <option value="house">{{ __('House') }}</option>
                        <option value="hostel">{{ __('Hostel') }}</option>
                        <option value="class">{{ __('Class') }}</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="startsOn">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="appoint">{{ __('Appoint') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

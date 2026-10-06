<div>
    <h4 class="mb-1">{{ __('Duplicate guardians') }}</h4>
    <p class="text-body-secondary small">{{ __('Guardians who share a phone number or a name. Nothing is merged automatically: choose which record stays, and the other is folded into it — its learners, fee shares, bookings and consents move across. The merged record is kept for history and the merge cannot be undone.') }}</p>

    @forelse ($groups as $group)
        <div class="card mb-3" wire:key="group-{{ $group['members']->first()->id }}">
            <div class="card-header">{{ $group['reason'] }}</div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>{{ __('Guardian') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Email') }}</th><th>{{ __('Learners') }}</th><th>{{ __('App access') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($group['members'] as $member)
                        <tr wire:key="m-{{ $member->id }}">
                            <td>{{ $member->displayName() }}</td>
                            <td>{{ $member->primary_phone ?? '—' }}</td>
                            <td>{{ $member->email ?? '—' }}</td>
                            <td>{{ $member->studentGuardians()->where('status', 'active')->count() }}</td>
                            <td>{{ $member->user_id !== null ? __('Yes') : __('No') }}</td>
                            <td class="text-end">
                                @foreach ($group['members']->where('id', '!=', $member->id) as $other)
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        wire:click="merge({{ $member->id }}, {{ $other->id }})"
                                        wire:confirm="{{ __('Keep :keep and fold :other into it? This cannot be undone.', ['keep' => $member->displayName(), 'other' => $other->displayName()]) }}">
                                        {{ __('Keep this · merge :name', ['name' => $other->displayName()]) }}
                                    </button>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </div>
    @empty
        <div class="alert alert-success">{{ __('No possible duplicate guardians found.') }}</div>
    @endforelse
</div>

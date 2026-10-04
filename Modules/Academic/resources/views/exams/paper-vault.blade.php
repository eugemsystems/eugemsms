<div>
    <h4 class="mb-1">{{ __('Secure paper vault') }}</h4>
    <div class="alert alert-danger">{{ __('release_at is enforced server-side with NO override path — not for the Head, not for the Super Admin.') }}</div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="sessionId">
                <option value="">{{ __('Select session') }}</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Paper') }}</th><th>{{ __('Status') }}</th><th>{{ __('Setter / Vetter') }}</th><th>{{ __('Release at') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse ($papers as $paper)
                        <tr wire:key="vault-{{ $paper->id }}">
                            <td>{{ $paper->subject?->name }} — {{ $paper->paper_name }}</td>
                            <td><span class="badge text-bg-secondary">{{ ucfirst($paper->status) }}</span></td>
                            <td>{{ $paper->setterStaff?->first_name }} / {{ $paper->vettedBy?->first_name ?? '—' }}</td>
                            <td>{{ $paper->release_at?->toDayDateTimeString() ?? '—' }}</td>
                            <td>
                                @if (in_array($paper->status, ['draft', 'set'], true))
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="vet({{ $paper->id }})">{{ __('Vet') }}</button>
                                @elseif ($paper->status === 'vetted')
                                    <input type="datetime-local" class="form-control form-control-sm d-inline-block mb-1" style="width: 200px" wire:model="releaseAt.{{ $paper->id }}">
                                    <button type="button" class="btn btn-sm btn-warning" wire:click="seal({{ $paper->id }})">{{ __('Seal') }}</button>
                                @elseif ($paper->status === 'sealed')
                                    <button type="button" class="btn btn-sm btn-danger" wire:click="release({{ $paper->id }})">{{ __('Release') }}</button>
                                @elseif ($paper->status === 'released')
                                    <span class="badge text-bg-success">{{ __('Released') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No papers for this session.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Access log') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('User') }}</th><th>{{ __('IP') }}</th><th>{{ __('When') }}</th></tr></thead>
                <tbody>
                    @forelse ($accessLog as $entry)
                        <tr>
                            <td>#{{ $entry->user_id }}</td>
                            <td>{{ $entry->ip }}</td>
                            <td>{{ $entry->created_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('No access logged yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

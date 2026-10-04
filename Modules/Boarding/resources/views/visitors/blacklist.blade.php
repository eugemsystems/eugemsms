<div>
    <h4 class="mb-1">{{ __('Visitor blacklist') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A blacklisted visitor is refused at sign-in; the attempt is logged as a security event.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Blacklisted') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @forelse ($blacklisted as $visitor)
                                <tr><td>{{ $visitor->full_name }}</td><td>{{ $visitor->blacklist_reason }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('Nobody blacklisted.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Blacklist a visitor') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model.live.debounce.400ms="visitorSearch" placeholder="{{ __('Search by name') }}">
                    @if ($searchResults->isNotEmpty())
                        <div class="list-group mb-2">
                            @foreach ($searchResults as $visitor)
                                <button type="button" class="list-group-item list-group-item-action {{ $visitorId === $visitor->id ? 'active' : '' }}" wire:click="$set('visitorId', {{ $visitor->id }})">{{ $visitor->full_name }}</button>
                            @endforeach
                        </div>
                    @endif
                    <input type="text" class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason') }}">
                    <button type="button" class="btn btn-danger btn-sm" wire:click="blacklist">{{ __('Blacklist') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

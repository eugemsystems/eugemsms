<div>
    <h4 class="mb-1">{{ __('Issue requisitions') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Approve, then issue — FIFO lots are consumed automatically and one journal posts per requisition.') }}</p>

    <h6 class="mb-2">{{ __('Pending approval') }}</h6>
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Requisition') }}</th><th>{{ __('Store') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Lines') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pending as $req)
                        <tr wire:key="pending-{{ $req->id }}">
                            <td>{{ $req->requisition_number }}</td>
                            <td>{{ $req->store->code }}</td>
                            <td>{{ $req->purpose }}</td>
                            <td>
                                @foreach ($req->lines as $line)
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span style="min-width: 10rem">{{ $line->item->name }} ({{ $line->quantity_requested }})</span>
                                        <input type="number" step="0.0001" class="form-control form-control-sm" style="max-width: 7rem" wire:model="overrides.{{ $req->id }}.{{ $line->id }}" placeholder="{{ __('approve qty') }}">
                                    </div>
                                @endforeach
                            </td>
                            <td><button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $req->id }})">{{ __('Approve') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Nothing pending.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">{{ __('Approved — ready to issue') }}</h6>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Requisition') }}</th><th>{{ __('Store') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Lines') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($approved as $req)
                        <tr wire:key="approved-{{ $req->id }}">
                            <td>{{ $req->requisition_number }}</td>
                            <td>{{ $req->store->code }}</td>
                            <td>{{ $req->purpose }}</td>
                            <td>
                                @foreach ($req->lines as $line)
                                    <div>{{ $line->item->name }} — {{ $line->quantity_approved }}</div>
                                @endforeach
                            </td>
                            <td><button type="button" class="btn btn-sm btn-success" wire:click="issue({{ $req->id }})" wire:confirm="{{ __('Issue this requisition? FIFO lots will be consumed and a journal posted.') }}">{{ __('Issue') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Nothing approved yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

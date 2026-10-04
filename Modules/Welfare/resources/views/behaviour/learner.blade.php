<div>
    <h4 class="mb-1">{{ __('Behaviour') }} — {{ $student->first_name }} {{ $student->last_name }}</h4>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    {{ __('Point balances') }}
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="rebuildBalance">{{ __('Rebuild current term') }}</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Term') }}</th><th>{{ __('Merit') }}</th><th>{{ __('Demerit') }}</th><th>{{ __('Net') }}</th><th>{{ __('Conduct') }}</th></tr></thead>
                        <tbody>
                            @forelse ($balances as $balance)
                                <tr>
                                    <td>{{ $balance->term_id }}</td>
                                    <td class="text-success">+{{ $balance->merit_points }}</td>
                                    <td class="text-danger">-{{ $balance->demerit_points }}</td>
                                    <td>{{ $balance->net_points }}</td>
                                    <td>{{ $balance->conduct_grade ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No balance computed yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Timeline') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Category') }}</th><th>{{ __('Points') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($records as $record)
                                <tr wire:key="record-{{ $record->id }}">
                                    <td>{{ $record->occurred_at->toDateString() }}</td>
                                    <td>
                                        @if ($record->is_confidential)
                                            <span class="text-body-secondary">{{ __('Under safeguarding review') }}</span>
                                        @else
                                            {{ $record->category?->name }}
                                        @endif
                                    </td>
                                    <td class="{{ $record->polarity === 'positive' ? 'text-success' : 'text-danger' }}">
                                        @unless ($record->is_confidential)
                                            {{ $record->polarity === 'positive' ? '+' : '' }}{{ $record->points }}
                                        @endunless
                                    </td>
                                    <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $record->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No behaviour records.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

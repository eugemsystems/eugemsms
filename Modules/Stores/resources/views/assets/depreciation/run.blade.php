<div>
    <h4 class="mb-1">{{ __('Depreciation run') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Preview, approve, then post — one journal per run, grouped by category and cost centre.') }}</p>

    <div class="card mb-4" style="max-width: 24rem">
        <div class="card-body d-flex gap-2">
            <input type="month" class="form-control" wire:model="periodMonth">
            <button type="button" class="btn btn-primary" wire:click="preview">{{ __('Preview') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Assets') }}</th><th>{{ __('Total depreciation') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr wire:key="run-{{ $run->id }}">
                            <td>{{ $run->period_month }}</td>
                            <td>{{ $run->asset_count }}</td>
                            <td>{{ number_format($run->total_depreciation_minor / 100, 2) }}</td>
                            <td>{{ $run->status }}</td>
                            <td class="d-flex gap-1">
                                @if ($run->status === 'preview')
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $run->id }})">{{ __('Approve') }}</button>
                                @endif
                                @if ($run->status === 'approved')
                                    <button type="button" class="btn btn-sm btn-success" wire:click="post({{ $run->id }})">{{ __('Post') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No runs yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div>
    <h4 class="mb-1">{{ __('Variance review') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('First/second marker variance exceeded the configured threshold — a third mark settles the candidate.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Paper') }}</th><th>{{ __('Variance') }}</th><th>{{ __('Third mark') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($marks as $mark)
                        <tr wire:key="mark-{{ $mark->id }}">
                            <td>{{ $mark->student?->first_name }} {{ $mark->student?->last_name }}</td>
                            <td>{{ $mark->paper?->subject?->name }} — {{ $mark->paper?->paper_name }}</td>
                            <td>{{ $mark->mark_variance }}</td>
                            <td style="width: 120px"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="thirdMarks.{{ $mark->id }}"></td>
                            <td><button type="button" class="btn btn-sm btn-primary" wire:click="settle({{ $mark->id }})">{{ __('Settle') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('Nothing awaiting variance review.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

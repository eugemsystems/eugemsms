<div>
    <h4 class="mb-1">{{ __('Moderate examination marks') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A moderated mark supersedes the settled mark for aggregation — the marker\'s raw mark remains visible.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="paperId">
                <option value="">{{ __('Select paper') }}</option>
                @foreach ($papers as $paper)
                    <option value="{{ $paper->id }}">{{ $paper->subject?->name }} — {{ $paper->paper_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Raw mark') }}</th><th>{{ __('Moderated mark') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($marks as $mark)
                        <tr wire:key="mark-{{ $mark->id }}">
                            <td>{{ $mark->student?->first_name }} {{ $mark->student?->last_name }}</td>
                            <td>{{ $mark->raw_mark }}</td>
                            <td style="width: 120px"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="moderatedMarks.{{ $mark->candidate_id }}"></td>
                            <td><button type="button" class="btn btn-sm btn-primary" wire:click="moderate({{ $mark->candidate_id }})">{{ __('Moderate') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No final marks to moderate yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('My reports') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Sharing lets a colleague run a report. What they see is always limited to what their own permissions allow, whatever you could see.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('insights.reports.shared', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Shared with me') }}</a>
            <a href="{{ route('insights.reports.builder', $school) }}" class="btn btn-primary btn-sm" wire:navigate>{{ __('New report') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Report') }}</th><th>{{ __('About') }}</th><th class="text-end">{{ __('Shared with') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr wire:key="rep-{{ $report->id }}">
                            <td>{{ $report->name }} @if ($report->description) <div class="small text-body-secondary">{{ $report->description }}</div> @endif</td>
                            <td class="small">{{ str_replace('_', ' ', $report->primary_entity_key) }}</td>
                            <td class="text-end">{{ $shareCounts[$report->id] ?? 0 }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-xs btn-outline-primary" wire:click="run({{ $report->id }})">{{ __('Run') }}</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="$set('shareReportId', {{ $report->id }})">{{ __('Share') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('You have not saved any reports yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($shareReportId)
        <div class="card mt-3">
            <div class="card-header">{{ __('Share with a colleague') }}</div>
            <div class="card-body">
                <select class="form-select mb-2" wire:model="shareWithUserId">
                    <option value="">{{ __('Colleague…') }}</option>
                    @foreach ($colleagues as $colleague) <option value="{{ $colleague->id }}">{{ $colleague->name }}</option> @endforeach
                </select>
                @error('shareWithUserId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="share">{{ __('Share') }}</button>
            </div>
        </div>
    @endif

    @include('intelligence::insights.reports.partials.result', ['result' => $result])
</div>

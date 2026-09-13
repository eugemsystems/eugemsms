<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __(':name\'s fees', ['name' => $student->fullName()]) }}</h4>
        <p class="text-body-secondary mb-0">{{ $student->admission_number }}</p>
    </div>

    @forelse ($assignments as $assignment)
        <div class="card mb-3" wire:key="assignment-{{ $assignment->id }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">{{ $assignment->term?->name }} — {{ $assignment->structure->name }} (v{{ $assignment->structure_version }})</h6>
                    <span class="text-body-secondary small">{{ __('Computed') }} {{ $assignment->computed_at->format('d M Y') }}</span>
                </div>
                <span class="badge {{ match ($assignment->status) { 'invoiced' => 'text-bg-success', 'approved' => 'text-bg-info', default => 'text-bg-warning' } }}">
                    {{ \Illuminate\Support\Str::headline($assignment->status) }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Component') }}</th>
                            <th>{{ __('Note') }}</th>
                            <th class="text-end">{{ __('Gross') }}</th>
                            <th class="text-end">{{ __('Discount') }}</th>
                            <th class="text-end">{{ __('Net') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignment->lines as $line)
                            <tr wire:key="line-{{ $line->id }}">
                                <td>{{ $line->component->name }}</td>
                                <td class="small text-body-secondary">{{ $line->calculation_note }}</td>
                                <td class="text-end">{{ number_format($line->gross_minor / 100, 2) }}</td>
                                <td class="text-end">{{ $line->discount_minor > 0 ? number_format($line->discount_minor / 100, 2) : '—' }}</td>
                                <td class="text-end">{{ number_format($line->net_minor / 100, 2) }} {{ $line->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleTrace({{ $assignment->id }})">
                    {{ $expandedAssignmentId === $assignment->id ? __('Hide') : __('Why this amount?') }}
                </button>
                @if ($expandedAssignmentId === $assignment->id)
                    <pre class="small bg-body-tertiary p-3 mt-2 mb-0" style="white-space: pre-wrap;">{{ json_encode($assignment->resolution_trace, JSON_PRETTY_PRINT) }}</pre>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center text-body-secondary py-4">{{ __('No fee assignments recorded for this learner yet.') }}</div>
        </div>
    @endforelse
</div>

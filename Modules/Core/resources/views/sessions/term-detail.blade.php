<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Term :number — :name', ['number' => $term->number, 'name' => $term->name]) }}</h4>
            <p class="text-body-secondary mb-0">
                {{ $term->starts_on->format('d M Y') }} &ndash; {{ $term->ends_on->format('d M Y') }}
            </p>
        </div>
        <a href="{{ route('sessions.years', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to years') }}
        </a>
    </div>

    <div class="row g-4 mb-4">
        @foreach ($periodTypes as $periodType)
            @php $state = $term->stateFor($periodType); @endphp
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-uppercase text-body-secondary small mb-2">{{ __(':type period', ['type' => ucfirst($periodType->value)]) }}</h6>
                        <span class="badge fs-6 text-bg-light text-capitalize mb-3">{{ str_replace('_', ' ', $state->value) }}</span>
                        <div class="d-flex gap-2">
                            <a href="{{ route('sessions.period', [$school, $term, $periodType->value]) }}" class="btn btn-sm btn-outline-primary" wire:navigate>
                                {{ __('Manage state') }}
                            </a>
                            <a href="{{ route('sessions.checklist', [$school, $term, $periodType->value]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>
                                {{ __('Close checklist') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">{{ __('Key dates') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6 text-body-secondary fw-normal">{{ __('Half term') }}</dt>
                        <dd class="col-6">
                            @if ($term->half_term_starts_on && $term->half_term_ends_on)
                                {{ $term->half_term_starts_on->format('d M') }} &ndash; {{ $term->half_term_ends_on->format('d M Y') }}
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-6 text-body-secondary fw-normal">{{ __('Fee due') }}</dt>
                        <dd class="col-6">{{ $term->fee_due_on?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-6 text-body-secondary fw-normal">{{ __('Results due') }}</dt>
                        <dd class="col-6">{{ $term->results_due_on?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-6 text-body-secondary fw-normal">{{ __('Reports release') }}</dt>
                        <dd class="col-6">{{ $term->reports_release_on?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-6 text-body-secondary fw-normal">{{ __('Teaching days') }}</dt>
                        <dd class="col-6 mb-0">{{ $term->teaching_days ?? __('Not computed yet') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">{{ __('Weeks') }}</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="generateWeeks" wire:loading.attr="disabled">
                        <i class="ri ri-refresh-line me-1"></i>{{ __('Generate weeks') }}
                    </button>
                </div>
                <div class="table-responsive" style="max-height: 20rem;">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('#') }}</th>
                                <th>{{ __('Starts') }}</th>
                                <th>{{ __('Ends') }}</th>
                                <th>{{ __('Type') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($weeks as $week)
                                <tr wire:key="week-{{ $week->id }}">
                                    <td>{{ $week->week_number }}</td>
                                    <td>{{ $week->starts_on->format('d M') }}</td>
                                    <td>{{ $week->ends_on->format('d M') }}</td>
                                    <td>
                                        @if ($week->is_teaching_week)
                                            <span class="badge text-bg-success">{{ __('Teaching') }}</span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ $week->label ?? __('Non-teaching') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-4">
                                        {{ __('No weeks generated yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

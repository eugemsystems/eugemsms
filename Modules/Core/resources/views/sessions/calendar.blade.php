<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Academic calendar') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Holidays for :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('sessions.years', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to years') }}
        </a>
    </div>

    <div class="mb-3" style="max-width: 20rem;">
        <select class="form-select" wire:model.live="selectedYearId">
            @foreach ($years as $year)
                <option value="{{ $year->id }}">{{ $year->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Starts') }}</th>
                        <th>{{ __('Ends') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($holidays as $holiday)
                        <tr wire:key="holiday-{{ $holiday->id }}">
                            <td>{{ $holiday->name }}</td>
                            <td class="text-capitalize">{{ $holiday->type }}</td>
                            <td>{{ $holiday->starts_on->format('d M Y') }}</td>
                            <td>{{ $holiday->ends_on->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-4">
                                {{ __('No holidays recorded for this year.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

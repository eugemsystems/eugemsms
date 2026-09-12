<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Approvals SLA report') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Average time from request to a final decision, across completed requests.') }}</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('By type') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Requests') }}</th>
                                <th>{{ __('Avg. hours') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($byType as $row)
                                <tr>
                                    <td>{{ \Illuminate\Support\Str::headline($row['label']) }}</td>
                                    <td>{{ $row['count'] }}</td>
                                    <td>{{ $row['averageHours'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No completed requests yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('By approver') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Approver') }}</th>
                                <th>{{ __('Decisions') }}</th>
                                <th>{{ __('Avg. hours') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($byApprover as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['count'] }}</td>
                                    <td>{{ $row['averageHours'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No completed requests yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

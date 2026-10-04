<div>
    <h4 class="mb-1">{{ __('Overdue safeguarding reviews') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Reference only — open the case for full, audited detail.') }}</p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Overdue risk assessments') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Case id') }}</th><th>{{ __('Review was due') }}</th></tr></thead>
                        <tbody>
                            @forelse ($overdueRiskAssessments as $row)
                                <tr><td>{{ $row['case_id'] }}</td><td class="text-danger">{{ $row['review_due_on']->toDateString() }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('Nothing overdue.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Overdue vulnerable-register reviews') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Review was due') }}</th></tr></thead>
                        <tbody>
                            @forelse ($overdueVulnerable as $registration)
                                @php $student = $vulnerableStudents->get($registration->student_id); @endphp
                                <tr><td>{{ $student?->first_name }} {{ $student?->last_name }}</td><td class="text-danger">{{ $registration->next_review_on?->toDateString() }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('Nothing overdue.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

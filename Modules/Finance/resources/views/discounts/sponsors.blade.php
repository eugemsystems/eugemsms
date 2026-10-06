<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Sponsor awards') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('The sponsor is billed through a fee liability; no discount is posted and the school’s income is unchanged.') }}</p>
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Scheme') }}</th><th>{{ __('Sponsor') }}</th><th>{{ __('From') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($awards as $award)
                    @php($sponsor = $sponsors[$award->sponsor_guardian_id] ?? null)
                    <tr wire:key="sa-{{ $award->id }}">
                        <td>{{ $students[$award->student_id]?->fullName() ?? '—' }}</td>
                        <td>{{ $schemeNames[$award->scheme_id] ?? '—' }}</td>
                        <td>{{ $sponsor ? (trim($sponsor->first_name.' '.$sponsor->last_name) ?: $sponsor->organisation_name) : '—' }}</td>
                        <td class="small">{{ $award->effective_from?->toFormattedDateString() }}</td>
                        <td>{{ __(ucfirst(str_replace('_', ' ', $award->status))) }}</td>
                        <td class="text-end"><a href="{{ route('finance.liabilities.editor', [$school, $award->student_id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Liabilities') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No sponsor-funded awards.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>

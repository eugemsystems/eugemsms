<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Overdue loans') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Reminders go out first; a fine is only charged when the copy comes back late. The fine shown is what it would be today.') }}</p>
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Borrower') }}</th><th>{{ __('Title') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Days late') }}</th><th class="text-end">{{ __('Fine today') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($loans as $loan)
                    @php
                        $daysLate = (int) $loan->due_on->diffInDays(now()->startOfDay());
                        $cap = $loan->copy->item->replacement_cost_minor;
                        $fine = $daysLate * $dailyRate;
                        $fine = $cap === null ? $fine : min($fine, $cap);
                        $borrower = $loan->borrower_type === 'student' ? $students->get($loan->borrower_id)?->fullName() : $staff->get($loan->borrower_id)?->fullName();
                    @endphp
                    <tr wire:key="od-{{ $loan->id }}">
                        <td>{{ $borrower }} <span class="small text-body-secondary">{{ $loan->borrower_type }}</span></td>
                        <td>{{ $loan->copy->item->title }}<div class="small text-body-secondary">{{ $loan->copy->accession_number }}</div></td>
                        <td>{{ $loan->due_on->format('d M Y') }}</td><td class="text-end">{{ $daysLate }}</td>
                        <td class="text-end">{{ $loan->borrower_type === 'student' ? number_format($fine / 100, 2).' '.($loan->copy->item->currency ?? '') : '—' }}</td>
                        <td class="text-end">@if ($canRemind) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="remind({{ $loan->id }})">{{ __('Remind') }}</button> @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('Nothing is overdue.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>

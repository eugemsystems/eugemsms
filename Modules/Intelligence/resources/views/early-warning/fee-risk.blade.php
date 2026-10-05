<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Fee default risk') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('A recommendation only. The fee reminder ladder is untouched and nothing here contacts a family.') }}</p>
        </div>
    </div>
    @if ($canRecompute)
        <div class="mb-3"><button type="button" class="btn btn-outline-primary btn-sm" wire:click="recompute">{{ __('Recompute') }}</button></div>
    @endif
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Guardian') }}</th><th>{{ __('Learner') }}</th><th class="text-end">{{ __('Risk') }}</th><th>{{ __('Why') }}</th><th>{{ __('Suggested action') }}</th></tr></thead>
                <tbody>
                    @forelse ($scores as $score)
                        <tr wire:key="fr-{{ $score->id }}">
                            <td>@php($guardian = $guardians[$score->guardian_id] ?? null){{ $guardian === null ? '—' : (trim($guardian->first_name.' '.$guardian->last_name) ?: ($guardian->organisation_name ?? '—')) }}</td>
                            <td>{{ $students[$score->student_id]?->fullName() ?? '—' }}</td>
                            <td class="text-end">{{ number_format($score->risk_score, 0) }}</td>
                            <td class="small">{{ collect($score->contributing_factors)->pluck('plain_language')->implode('; ') }}</td>
                            <td>{{ ['early_contact' => __('Early contact'), 'offer_payment_plan' => __('Offer a payment plan')][$score->recommended_action] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No households at risk.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

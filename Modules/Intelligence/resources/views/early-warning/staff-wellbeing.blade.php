<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-0">{{ __('Staff wellbeing') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('You see your own indicator and your direct reports’ — no one else’s. Use it to start a supportive conversation, not to judge performance.') }}</p>
        </div>
    </div>
    <div class="mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="termId">
            @foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach
        </select>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Staff member') }}</th><th class="text-end">{{ __('Workload %') }}</th><th class="text-end">{{ __('Terms over ceiling') }}</th><th>{{ __('Sick leave') }}</th><th>{{ __('Flag') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($staff as $member)
                        @php($indicator = $indicators[$member->id] ?? null)
                        <tr wire:key="sw-{{ $member->id }}">
                            <td>{{ $member->fullName() }} @if ($member->id === $ownStaffId) <span class="badge text-bg-light">{{ __('You') }}</span> @endif</td>
                            <td class="text-end">{{ $indicator?->workload_utilisation_percent !== null ? number_format($indicator->workload_utilisation_percent, 0) : '—' }}</td>
                            <td class="text-end">{{ $indicator?->consecutive_terms_over_ceiling ?? '—' }}</td>
                            <td>{{ $indicator?->sick_leave_days_trend ? __(ucfirst($indicator->sick_leave_days_trend)) : '—' }}</td>
                            <td>@if ($indicator) <span class="badge text-bg-{{ ['concern' => 'danger', 'watch' => 'warning', 'none' => 'success'][$indicator->flag_level] ?? 'secondary' }}">{{ __(ucfirst($indicator->flag_level)) }}</span> @else — @endif</td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="recalculate({{ $member->id }})">{{ __('Recalculate') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No staff record is linked to your account, and no one reports to you.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

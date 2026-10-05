<div>
    <h4 class="mb-1">{{ __('Emergency drills') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Conducted') }}</th><th>{{ __('Type') }}</th><th>{{ __('Expected') }}</th><th>{{ __('Mustered') }}</th><th>{{ __('Unaccounted') }}</th><th>{{ __('Evac (s)') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($drills as $drill)
                                <tr wire:key="drill-{{ $drill->id }}">
                                    <td>{{ $drill->conducted_at->format('d M Y H:i') }}</td>
                                    <td>{{ $drill->drill_type }}</td>
                                    <td>{{ $drill->expected_headcount }}</td>
                                    <td>{{ $drill->mustered_headcount ?? '—' }}</td>
                                    <td>
                                        <span class="{{ ($drill->unaccounted_count ?? 0) > 0 ? 'badge bg-danger' : '' }}">{{ $drill->unaccounted_count ?? '—' }}</span>
                                    </td>
                                    <td>{{ $drill->evacuation_seconds ?? '—' }}</td>
                                    <td><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="select({{ $drill->id }})">{{ __('Review') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No drills recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            @if ($selectedDrillId !== null)
                <div class="card">
                    <div class="card-header">{{ __('Review drill #') }}{{ $selectedDrillId }}</div>
                    <div class="card-body">
                        <textarea class="form-control mb-2" wire:model="findings" placeholder="{{ __('Findings') }}"></textarea>
                        <textarea class="form-control mb-2" wire:model="actionsRequired" placeholder="{{ __('Actions required') }}"></textarea>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="save">{{ __('Save') }}</button>
                    </div>
                </div>
            @else
                <div class="text-body-secondary">{{ __('Select a drill to record findings.') }}</div>
            @endif
        </div>
    </div>
</div>

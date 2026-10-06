<div>
    <h4 class="mb-1">{{ __('Catering costs') }}</h4>
    <p class="text-body-secondary small">{{ __('Estimated from each meal\'s priced ingredients at current stock cost, scaled to the servings actually served. A meal with any unpriced ingredient has no cost and is never shown as zero.') }}</p>

    <div class="row g-2 mb-3" style="max-width:30rem">
        <div class="col-6"><label class="form-label small mb-0">{{ __('From') }}</label><input type="date" class="form-control form-control-sm" wire:model.live="from"></div>
        <div class="col-6"><label class="form-label small mb-0">{{ __('To') }}</label><input type="date" class="form-control form-control-sm" wire:model.live="to"></div>
    </div>
    @php($fmt = fn (?int $minor): string => $minor === null ? __('unavailable') : $currency.' '.number_format($minor / 100, 2))

    @if ($unpriced > 0)
        <div class="alert alert-warning py-2 small">{{ trans_choice(':count closed service has no cost because an ingredient is not priced in stores.|:count closed services have no cost because an ingredient is not priced in stores.', $unpriced, ['count' => $unpriced]) }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Meal') }}</th><th class="text-end">{{ __('Services') }}</th><th class="text-end">{{ __('Served') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Per serving') }}</th><th class="text-end">{{ __('Over-produced') }}</th></tr></thead>
                <tbody>
                    @forelse ($byMeal as $row)
                        <tr wire:key="m-{{ $row['meal'] }}"><td>{{ ucfirst($row['meal']) }}</td><td class="text-end">{{ $row['services'] }}</td><td class="text-end">{{ $row['served'] }}</td><td class="text-end">{{ $row['cost_minor'] > 0 ? $fmt($row['cost_minor']) : __('unavailable') }}</td><td class="text-end">{{ $fmt($row['per_serving_minor']) }}</td><td class="text-end">{{ $row['over_percent'] !== null ? $row['over_percent'].'%' : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No closed services in this range.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div></div>
        </div>
        <div class="col-lg-5">
            <div class="card"><div class="card-header">{{ __('Cost per serving by week') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($weekly as $row)
                        <li class="list-group-item d-flex justify-content-between" wire:key="w-{{ $row['week'] }}"><span>{{ __('Week of :d', ['d' => $row['week']]) }}</span><strong>{{ $fmt($row['per_serving_minor']) }}</strong></li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('Nothing costed yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

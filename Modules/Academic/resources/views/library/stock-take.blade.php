<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Library stock-take') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Scan every copy on the shelves. Whatever is still missing after a confirmatory second pass is marked lost, and charged to the borrower if it was on loan.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-6">
            @if ($current === null)
                <div class="card"><div class="card-body"><p class="text-body-secondary">{{ __('No stock-take is in progress.') }}</p><button type="button" class="btn btn-primary btn-sm" wire:click="start">{{ __('Start a stock-take') }}</button></div></div>
            @else
                <div class="card"><div class="card-body">
                    <div class="mb-2"><strong>{{ $current->scanned_count }}</strong> / {{ $current->expected_count }} {{ __('scanned') }} · {{ __('started :date', ['date' => $current->conducted_on->format('d M Y')]) }}</div>
                    <div class="progress mb-3" style="height: .5rem;"><div class="progress-bar" style="width: {{ $current->expected_count > 0 ? min(100, round($current->scanned_count / $current->expected_count * 100)) : 0 }}%"></div></div>
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="scanCode" wire:keydown.enter="scan" placeholder="{{ __('Scan accession number or barcode') }}" autofocus>
                    @error('scanCode') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm mb-3" wire:click="scan">{{ __('Record scan') }}</button>
                    <hr>
                    @if (! $current->confirmatory_pass_done)
                        <p class="small text-body-secondary">{{ __('When the first walk-through is done, scan again whatever is listed as unscanned, then record the second pass.') }}</p>
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="confirmSecondPass" wire:confirm="{{ __('Record that the confirmatory second pass is finished?') }}">{{ __('Second pass done') }}</button>
                    @else
                        <p class="small text-danger">{{ __('Completing marks every unscanned copy lost.') }}</p>
                        <select class="form-select form-select-sm mb-2" wire:model="feeComponentId"><option value="">{{ __('Fee component for borrowers…') }}</option>@foreach ($components as $component) <option value="{{ $component->id }}">{{ $component->name }}</option> @endforeach</select>
                        @error('feeComponentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-danger btn-sm" wire:click="complete" wire:confirm="{{ __('Mark all unscanned copies lost and charge borrowers?') }}">{{ __('Complete stock-take') }}</button>
                    @endif
                </div></div>
            @endif
            @if ($past->isNotEmpty())
                <div class="card mt-3"><div class="card-header">{{ __('Previous stock-takes') }}</div><ul class="list-group list-group-flush small">@foreach ($past as $take) <li class="list-group-item">{{ $take->conducted_on->format('d M Y') }} — {{ $take->scanned_count }} / {{ $take->expected_count }} {{ __('scanned') }}, {{ $take->missing_count }} {{ __('lost') }}</li> @endforeach</ul></div>
            @endif
        </div>
        <div class="col-xl-6">
            @if ($current !== null)
                <div class="card"><div class="card-header">{{ __('Not yet scanned') }} ({{ $unscanned->count() }})</div><div class="table-responsive" style="max-height: 28rem;"><table class="table table-sm mb-0">
                    <tbody>@forelse ($unscanned as $copy) <tr wire:key="u-{{ $copy->id }}"><td>{{ $copy->accession_number }}</td><td>{{ $copy->item->title }}</td><td class="small text-body-secondary">{{ str_replace('_', ' ', $copy->status) }}</td></tr> @empty <tr><td class="text-center text-body-secondary py-3">{{ __('Everything has been scanned.') }}</td></tr> @endforelse</tbody>
                </table></div></div>
            @endif
        </div>
    </div>
</div>

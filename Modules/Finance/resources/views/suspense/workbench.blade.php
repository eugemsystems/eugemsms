<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Suspense workbench') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Unidentified deposits, oldest first. Confirm a match to a learner — nothing here allocates automatically.') }}</p>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Deposit date') }}</th>
                        <th>{{ __('Age') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Depositor') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->items() as $item)
                        <tr wire:key="item-{{ $item->id }}">
                            <td>{{ $item->deposit_date->format('d M Y') }}</td>
                            <td>
                                @php $age = $item->deposit_date->diffInDays(now()); @endphp
                                <span class="badge {{ $age > 14 ? 'bg-label-danger' : ($age > 7 ? 'bg-label-warning' : 'bg-label-secondary') }}">
                                    {{ __(':days days', ['days' => $age]) }}
                                </span>
                            </td>
                            <td>{{ ucfirst(str_replace('_', ' ', $item->source)) }}</td>
                            <td>{{ $item->depositor_name ?? '—' }}</td>
                            <td>{{ $item->reference_text ?? '—' }}</td>
                            <td class="text-end">{{ $item->currency }} {{ number_format($item->amount_minor / 100, 2) }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startResolving({{ $item->id }})">{{ __('Resolve') }}</button>
                            </td>
                        </tr>
                        @if ($resolvingItemId === $item->id)
                            <tr>
                                <td colspan="7">
                                    <div class="card bg-body-secondary mb-2">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label" for="studentSearch">{{ __('Match to learner') }}</label>
                                                @if ($selectedStudentId !== null)
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" value="{{ $selectedStudentLabel }}" disabled>
                                                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('selectedStudentId', null)">{{ __('Change') }}</button>
                                                    </div>
                                                @else
                                                    <input type="text" class="form-control @error('selectedStudentId') is-invalid @enderror" id="studentSearch" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search by admission number or name…') }}">
                                                    @error('selectedStudentId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                    @if ($studentSearch !== '')
                                                        <div class="list-group mt-1">
                                                            @forelse ($this->studentResults() as $result)
                                                                <button type="button" wire:key="result-{{ $result->id }}" class="list-group-item list-group-item-action" wire:click="selectStudent({{ $result->id }})">
                                                                    {{ $result->admission_number }} — {{ $result->fullName() }}
                                                                </button>
                                                            @empty
                                                                <div class="list-group-item text-body-secondary">{{ __('No matches.') }}</div>
                                                            @endforelse
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-floating form-floating-outline">
                                                    <input type="text" class="form-control" id="resolutionNote" wire:model="resolutionNote" placeholder=" ">
                                                    <label for="resolutionNote">{{ __('Note (optional)') }}</label>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-primary" wire:click="resolve">{{ __('Confirm & allocate') }}</button>
                                                <button type="button" class="btn btn-outline-secondary" wire:click="cancelResolving">{{ __('Cancel') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No unidentified deposits.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

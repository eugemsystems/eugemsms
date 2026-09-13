<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.journals.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('New manual journal') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Saved as a draft — a different user must approve it before it posts.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('narration') is-invalid @enderror" id="narration" wire:model="narration" placeholder=" ">
                            <label for="narration">{{ __('Narration') }}</label>
                            @error('narration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('effectiveAt') is-invalid @enderror" id="effectiveAt" wire:model="effectiveAt" placeholder=" ">
                            <label for="effectiveAt">{{ __('Effective date') }}</label>
                            @error('effectiveAt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('reference') is-invalid @enderror" id="reference" wire:model="reference" placeholder=" ">
                            <label for="reference">{{ __('Reference (optional)') }}</label>
                            @error('reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 14rem;">{{ __('Account') }}</th>
                                <th style="width: 6rem;">{{ __('DR/CR') }}</th>
                                <th style="width: 6rem;">{{ __('Currency') }}</th>
                                <th style="width: 10rem;">{{ __('Amount') }}</th>
                                <th style="min-width: 10rem;">{{ __('Cost centre') }}</th>
                                <th style="min-width: 10rem;">{{ __('Narration') }}</th>
                                <th style="width: 3rem;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $index => $line)
                                <tr wire:key="journal-line-{{ $index }}">
                                    <td>
                                        <select class="form-select form-select-sm @error("lines.{$index}.account_id") is-invalid @enderror" wire:model="lines.{{ $index }}.account_id">
                                            <option value="">{{ __('Select an account') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.direction">
                                            <option value="DR">{{ __('DR') }}</option>
                                            <option value="CR">{{ __('CR') }}</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.currency">
                                            <option value="USD">USD</option>
                                            <option value="ZWG">ZWG</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal" class="form-control form-control-sm @error("lines.{$index}.amount") is-invalid @enderror" wire:model="lines.{{ $index }}.amount" placeholder="0.00">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.cost_centre_id">
                                            <option value="">{{ __('None') }}</option>
                                            @foreach ($costCentres as $costCentre)
                                                <option value="{{ $costCentre->id }}">{{ $costCentre->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" wire:model="lines.{{ $index }}.narration" placeholder="{{ __('Optional') }}">
                                    </td>
                                    <td>
                                        @if (count($lines) > 2)
                                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeLine({{ $index }})" title="{{ __('Remove line') }}" aria-label="{{ __('Remove line') }}">
                                                <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mb-4" wire:click="addLine">
                    <i class="ri ri-add-line me-1"></i>{{ __('Add line') }}
                </button>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save as draft') }}</button>
                    <a href="{{ route('finance.journals.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>

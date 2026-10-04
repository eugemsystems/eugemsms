<div>
    <h4 class="mb-1">{{ __('Purchase requisitions') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Exceeding available budget never blocks submission — it is shown for a higher approval.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Justification') }}</th><th>{{ __('Est. total') }}</th><th>{{ __('Budget') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($requisitions as $req)
                                <tr wire:key="preq-{{ $req->id }}">
                                    <td>{{ $req->requisition_number }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($req->justification, 40) }}</td>
                                    <td>{{ number_format($req->estimated_total_minor / 100, 2) }}</td>
                                    <td>
                                        @if ($req->budget_check_result === 'exceeds')
                                            <span class="badge text-bg-danger">{{ __('exceeds') }}</span>
                                        @elseif ($req->budget_check_result === 'within')
                                            <span class="badge text-bg-success">{{ __('within') }}</span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ $req->budget_check_result ?? __('not checked') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $req->status }}</td>
                                    <td>
                                        @if ($req->status === 'pending')
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $req->id }})">{{ __('Approve') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No requisitions.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New requisition') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="departmentId">
                        <option value="">{{ __('Department') }}</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }} — {{ $cc->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="budgetLineId">
                        <option value="">{{ __('Budget line (optional)') }}</option>
                        @foreach ($budgetLines as $line)
                            <option value="{{ $line->id }}">#{{ $line->id }} — {{ __('available') }} {{ number_format($line->available_minor / 100, 2) }}</option>
                        @endforeach
                    </select>
                    <textarea class="form-control mb-2" wire:model="justification" placeholder="{{ __('Justification') }}"></textarea>
                    <select class="form-select mb-3" wire:model="urgency">
                        <option value="normal">{{ __('Normal') }}</option>
                        <option value="urgent">{{ __('Urgent') }}</option>
                        <option value="emergency">{{ __('Emergency') }}</option>
                    </select>
                    @foreach ($lines as $index => $line)
                        <div class="row g-1 mb-2" wire:key="preq-line-{{ $index }}">
                            <div class="col-12">
                                <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.item_id">
                                    <option value="">{{ __('Item (optional, for services leave blank)') }}</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5"><input type="text" class="form-control form-control-sm" wire:model="lines.{{ $index }}.description" placeholder="{{ __('Description') }}"></div>
                            <div class="col-2"><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="lines.{{ $index }}.quantity" placeholder="{{ __('Qty') }}"></div>
                            <div class="col-2"><input type="text" class="form-control form-control-sm" wire:model="lines.{{ $index }}.unit" placeholder="{{ __('Unit') }}"></div>
                            <div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="lines.{{ $index }}.estimated_unit_minor" placeholder="{{ __('Est. unit (minor)') }}"></div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" wire:click="addLine">{{ __('Add line') }}</button>
                    <div><button type="button" class="btn btn-primary" wire:click="submit">{{ __('Submit') }}</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

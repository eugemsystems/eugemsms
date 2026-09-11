<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __(':type close checklist — Term :number', ['type' => ucfirst($type->value), 'number' => $term->number]) }}</h4>
            <p class="text-body-secondary mb-0">{{ $term->name }}</p>
        </div>
        <a href="{{ route('sessions.period', [$school, $term, $type->value]) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to period control') }}
        </a>
    </div>

    @if ($result->passesBlocking())
        <div class="alert alert-success">{{ __('All blocking checks pass — this period can be locked.') }}</div>
    @else
        <div class="alert alert-warning">{{ __('One or more blocking checks are failing — this period cannot be locked yet.') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Check') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Detail') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result->items as $item)
                        <tr wire:key="checklist-{{ $item->code }}">
                            <td>
                                {{ $item->label }}
                                @unless ($item->blocking)
                                    <span class="badge text-bg-light ms-1">{{ __('Advisory') }}</span>
                                @endunless
                            </td>
                            <td>
                                @if ($item->passed)
                                    <span class="badge text-bg-success"><i class="ri ri-check-line"></i> {{ __('Pass') }}</span>
                                @else
                                    <span class="badge {{ $item->blocking ? 'text-bg-danger' : 'text-bg-warning' }}">
                                        <i class="ri ri-close-line"></i> {{ __('Fail') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-body-secondary">{{ $item->message }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-body-secondary py-4">
                                {{ __('No checklist items are registered for this period type.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

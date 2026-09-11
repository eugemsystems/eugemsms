<div>
    @if ($years->isNotEmpty())
        <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri ri-calendar-event-line"></i>
                <span class="d-none d-sm-inline">
                    @php $currentYear = $years->firstWhere('id', $currentYearId); @endphp
                    @if ($currentYear)
                        {{ $currentYear->name }}
                        @if ($currentTermId)
                            &middot; {{ $currentYear->terms->firstWhere('id', $currentTermId)?->name }}
                        @endif
                    @else
                        {{ __('Select session') }}
                    @endif
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width: 16rem;">
                @foreach ($years as $year)
                    <li><h6 class="dropdown-header d-flex align-items-center justify-content-between">
                        {{ $year->name }}
                        @if ($year->id === $currentYearId && ! $currentTermId)
                            <i class="ri ri-check-line"></i>
                        @endif
                    </h6></li>
                    @foreach ($year->terms->sortBy('number') as $term)
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center justify-content-between {{ $term->id === $currentTermId ? 'active' : '' }}" wire:click="switchTo({{ $year->id }}, {{ $term->id }})">
                                <span class="ps-2">{{ $term->name }}</span>
                                @if ($term->id === $currentTermId)
                                    <i class="ri ri-check-line"></i>
                                @endif
                            </button>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif
</div>

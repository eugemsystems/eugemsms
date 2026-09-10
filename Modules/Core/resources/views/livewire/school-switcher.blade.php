<div>
    @if ($schools->count() > 1)
        <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri ri-school-line"></i>
                <span class="d-none d-sm-inline">
                    {{ $schools->firstWhere('id', $currentSchoolId)?->code ?? __('Select school') }}
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach ($schools as $school)
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center justify-content-between {{ $school->id === $currentSchoolId ? 'active' : '' }}" wire:click="switchTo({{ $school->id }})">
                            <span>{{ $school->name }}</span>
                            @if ($school->id === $currentSchoolId)
                                <i class="ri ri-check-line"></i>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

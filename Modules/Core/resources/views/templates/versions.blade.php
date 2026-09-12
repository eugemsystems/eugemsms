<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('templates.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Version history') }}</h4>
            <p class="text-body-secondary mb-0">{{ $templateType }}</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-end">{{ __('Compare') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($versions as $version)
                        <tr wire:key="version-{{ $version->id }}">
                            <td>v{{ $version->version }}</td>
                            <td>{{ $version->name }}</td>
                            <td>
                                @if ($version->is_active)
                                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Superseded') }}</span>
                                @endif
                                @if ($version->is_default)
                                    <span class="badge text-bg-info">{{ __('Default') }}</span>
                                @endif
                            </td>
                            <td>{{ $version->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary {{ $compareLeftId === $version->id ? 'active' : '' }}" wire:click="$set('compareLeftId', {{ $version->id }})">A</button>
                                    <button type="button" class="btn btn-outline-secondary {{ $compareRightId === $version->id ? 'active' : '' }}" wire:click="$set('compareRightId', {{ $version->id }})">B</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($compareLeft && $compareRight)
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><strong>A</strong> · v{{ $compareLeft->version }}</div>
                    <div class="card-body">
                        <pre class="small mb-0" style="white-space: pre-wrap;">{{ $compareLeft->content }}</pre>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><strong>B</strong> · v{{ $compareRight->version }}</div>
                    <div class="card-body">
                        <pre class="small mb-0" style="white-space: pre-wrap;">{{ $compareRight->content }}</pre>
                    </div>
                </div>
            </div>
        </div>
    @else
        <p class="text-body-secondary">{{ __('Pick A and B on two versions above to compare their content side by side.') }}</p>
    @endif
</div>

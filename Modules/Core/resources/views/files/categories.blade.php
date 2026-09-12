<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('files.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('File categories') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Registered by each owning module — upload limits, sensitivity, and expiry rules.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Module') }}</th>
                        <th>{{ __('Allowed types') }}</th>
                        <th>{{ __('Max size') }}</th>
                        <th>{{ __('Sensitive') }}</th>
                        <th>{{ __('Variants') }}</th>
                        <th>{{ __('Expiry required') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td>
                                <div class="fw-medium">{{ $category->label }}</div>
                                <div class="small text-body-secondary font-monospace">{{ $category->key }}</div>
                            </td>
                            <td>{{ $category->module_code }}</td>
                            <td class="small">{{ implode(', ', $category->allowed_mimes) }}</td>
                            <td>{{ number_format($category->max_size_bytes / (1024 * 1024), 1) }} MB</td>
                            <td>
                                @if ($category->is_sensitive)
                                    <span class="badge text-bg-warning">{{ __('Yes') }}</span>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($category->generates_variants)
                                    <span class="badge text-bg-info">{{ __('Yes') }}</span>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($category->requires_expiry)
                                    <span class="badge text-bg-info">{{ __('Yes') }}</span>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No file categories are registered yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

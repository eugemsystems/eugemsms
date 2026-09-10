<x-layouts::app :title="__('Dashboard')">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-body-secondary mb-2">{{ __('Schools') }}</h6>
                    <p class="mb-0 text-body-tertiary small">{{ __('No schools provisioned yet.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-body-secondary mb-2">{{ __('Modules') }}</h6>
                    <p class="mb-0 text-body-tertiary small">{{ __('No modules installed yet.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-body-secondary mb-2">{{ __('Installation') }}</h6>
                    <p class="mb-0 text-body-tertiary small">{{ __('Run the installer to get started.') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>

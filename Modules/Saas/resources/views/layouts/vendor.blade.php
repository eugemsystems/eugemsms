<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body>
        @php
            $vendorNav = [
                'vendor.tenants.index' => 'Tenants',
                'vendor.subscriptions' => 'Subscriptions',
                'vendor.plans' => 'Plans',
                'vendor.invoices' => 'Billing',
                'vendor.keys' => 'Licence keys',
                'vendor.rollouts' => 'Rollouts',
                'vendor.releases' => 'Releases',
                'vendor.broadcasts' => 'Broadcasts',
                'vendor.incidents' => 'Incidents',
                'vendor.onboarding' => 'Onboarding',
                'vendor.support' => 'Support queue',
                'vendor.adoption' => 'Adoption',
                'vendor.churn' => 'Churn risk',
            ];
        @endphp
        <nav class="navbar navbar-expand-lg border-bottom mb-4">
            <div class="container-fluid">
                <span class="navbar-brand fw-semibold">{{ __('Vendor console') }}</span>
                <div class="d-flex flex-wrap gap-1 me-auto">
                    @foreach ($vendorNav as $routeName => $label)
                        @if (Route::has($routeName))
                            <a href="{{ route($routeName) }}" class="btn btn-sm {{ request()->routeIs($routeName.'*') ? 'btn-primary' : 'btn-outline-secondary' }}" wire:navigate>{{ __($label) }}</a>
                        @endif
                    @endforeach
                </div>
                <span class="small text-body-secondary">{{ auth()->user()?->email }}</span>
            </div>
        </nav>

        <main class="container-fluid pb-5">
            {{ $slot }}
        </main>

        <div id="serp-toast-region" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;"></div>
    </body>
</html>

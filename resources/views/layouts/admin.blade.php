@extends('layouts.app')

@push('head')
    <style>
        .admin-side-nav .menu-group {
            margin-bottom: 0.5rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }
        .admin-side-nav .menu-group summary {
            list-style: none;
            cursor: pointer;
            padding: 0.85rem 0.9rem;
            font-weight: 600;
            background: #f8fafc;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .admin-side-nav .menu-group summary::-webkit-details-marker {
            display: none;
        }
        .admin-side-nav .menu-group[open] summary {
            background: #e8f1ff;
            color: #0b5ed7;
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25);
        }
        .admin-side-nav .menu-group summary:focus {
            outline: none;
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.35), 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
        }
        .admin-side-nav .menu-group .chevron {
            font-size: 0.85rem;
            transition: transform 0.2s ease;
        }
        .admin-side-nav .menu-group[open] .chevron {
            transform: rotate(180deg);
        }
        .admin-side-nav .list-group-item {
            border-radius: 8px;
            margin: 4px 0;
            border: none;
        }
        .admin-side-nav .list-group-item.active {
            font-weight: 600;
        }
    </style>
@endpush

@section('content')
    <main class="container-xl py-4 py-md-5">
        <div class="row g-4">
            <aside class="col-12 col-lg-3 col-xl-2">
                <div class="card border-0 shadow-sm admin-side-nav">
                    <div class="card-body p-3">
                        @php
                            /** @var \App\Models\User|null $authUser */
                            $authUser = auth()->user();
                            $isSuperAdmin = $authUser?->role?->value === 'super_admin' || $authUser?->role === 'super_admin';
                            $can = static fn (string $permission): bool => $authUser !== null && ($authUser->hasPermission($permission) || in_array('*', $authUser->permissions(), true));
                            $canOpen = static fn (string $routeName): bool => \Illuminate\Support\Facades\Route::has($routeName);

                            $showDashboard = $canOpen('admin.dashboard') && ($isSuperAdmin || $can('module.dashboard.view'));
                            $showBookings = $canOpen('admin.bookings.index') && ($isSuperAdmin || $can('module.bookings.view'));
                            $showQuotations = $canOpen('admin.quotations.index') && ($isSuperAdmin || $can('module.quotations.view'));
                            $showAgencies = $canOpen('admin.agencies.index') && $isSuperAdmin;
                            $showSupport = $canOpen('admin.support-tickets.index') && ($isSuperAdmin || $can('module.support.view'));

                            $showHotels = $canOpen('admin.hotels.index') && $isSuperAdmin;
                            $showVisa = $canOpen('admin.visa-rates.index') && $isSuperAdmin;
                            $showPackages = $canOpen('admin.packages.index') && ($isSuperAdmin || $can('module.marketing.view'));
                            $showGroups = $canOpen('admin.groups.index') && $isSuperAdmin;
                            $showCms = $canOpen('admin.content-blocks.index') && ($isSuperAdmin || $can('module.marketing.view'));
                            $showBlogs = $canOpen('admin.blog-posts.index') && ($isSuperAdmin || $can('module.marketing.view'));

                            $showIntegrationHub = $canOpen('admin.integrations.index') && $isSuperAdmin;
                            $showIntegrationCredentials = $canOpen('admin.integrations.accounts.index') && $isSuperAdmin;
                            $showIntegrationProviders = $canOpen('admin.integrations.providers.index') && $isSuperAdmin;
                            $showIntegrationSearchResults = $canOpen('admin.integrations.search-results.index') && $isSuperAdmin;
                            $showIntegrationAccessMatrix = $canOpen('admin.integrations.access-matrix.index') && $isSuperAdmin;

                            $showAnalytics = $canOpen('admin.analytics.index') && ($isSuperAdmin || $can('module.analytics.view'));
                            $showSettings = $canOpen('admin.settings.index') && $isSuperAdmin;
                            $showUsersPermissions = $canOpen('admin.system.permissions.index') && $isSuperAdmin;
                            $showSystem = $canOpen('admin.system.tenancy.edit') && $isSuperAdmin;
                            $showMonitoring = $canOpen('admin.monitoring.health-overview') && $isSuperAdmin;
                            $showDocumentSecurity = $canOpen('admin.documents.security-settings.edit') && $isSuperAdmin;
                            $showAirportDirectory = $canOpen('admin.system.airports.edit') && $isSuperAdmin;

                            $showSalesSection = $showDashboard || $showBookings || $showQuotations || $showAgencies || $showSupport;
                            $showInventorySection = $showHotels || $showVisa || $showPackages || $showGroups || $showCms || $showBlogs;
                            $showIntegrationSection = $showIntegrationHub || $showIntegrationCredentials || $showIntegrationProviders || $showIntegrationSearchResults || $showIntegrationAccessMatrix;
                            $showPlatformSection = $showAnalytics || $showSettings || $showUsersPermissions || $showSystem || $showMonitoring || $showDocumentSecurity || $showAirportDirectory;

                            $salesOpen = request()->routeIs('admin.dashboard', 'admin.bookings.*', 'admin.quotations.*', 'admin.agencies.*', 'admin.support-tickets.*');
                            $inventoryOpen = request()->routeIs('admin.hotels.*', 'admin.hotel-*', 'admin.visa-*', 'admin.packages.*', 'admin.groups.*', 'admin.content-blocks.*', 'admin.landing-pages.*', 'admin.blog-posts.*');
                            $integrationOpen = request()->routeIs('admin.integrations.*');
                            $platformOpen = request()->routeIs('admin.analytics.*', 'admin.settings.*', 'admin.system.*', 'admin.monitoring.*', 'admin.documents.*');
                        @endphp
                        <div class="admin-sidebar-brand px-1 pb-3 mb-3 border-bottom">
                            <x-frontend.brand-wordmark context="admin" />
                            <div class="admin-sidebar-brand__tagline">Operations console</div>
                        </div>
                        <div class="small text-uppercase text-muted fw-bold px-1 pb-2" style="font-size: 0.65rem; letter-spacing: 0.08em;">Dashboard menu</div>
                        <div>
                            @if($showSalesSection)
                            <details class="menu-group" {{ $salesOpen ? 'open' : '' }}>
                                <summary>
                                        Sales &amp; operations
                                    <span class="chevron">▾</span>
                                </summary>
                                <div class="list-group list-group-flush p-1">
                                        @if($showDashboard)
                                        <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action py-2 {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
                                        @endif
                                        @if($showBookings)
                                        <a href="{{ route('admin.bookings.index') }}" class="list-group-item list-group-item-action py-2 {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">Bookings</a>
                                        @endif
                                        @if($showQuotations)
                                        <a href="{{ route('admin.quotations.index') }}" class="list-group-item list-group-item-action py-2 {{ request()->routeIs('admin.quotations.*') ? 'active' : '' }}">Quotations</a>
                                        @endif
                                        @if($showAgencies)
                                        <a href="{{ route('admin.agencies.index') }}" class="list-group-item list-group-item-action py-2 {{ request()->routeIs('admin.agencies.*') ? 'active' : '' }}">Agencies / tenants</a>
                                        @endif
                                        @if($showSupport)
                                        <a href="{{ route('admin.support-tickets.index') }}" class="list-group-item list-group-item-action py-2 {{ request()->routeIs('admin.support-tickets.*') ? 'active' : '' }}">Support desk</a>
                                        @endif
                                </div>
                            </details>
                            @endif

                            @if($showInventorySection)
                            <details class="menu-group" {{ $inventoryOpen ? 'open' : '' }}>
                                <summary>
                                        Inventory & Content
                                    <span class="chevron">▾</span>
                                </summary>
                                <div class="list-group list-group-flush p-1">
                                        @if($showHotels)
                                        <a href="{{ route('admin.hotels.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.hotels.*', 'admin.hotel-*') ? 'active' : '' }}">Hotels / Stays</a>
                                        @endif
                                        @if($showVisa)
                                        <a href="{{ route('admin.visa-rates.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.visa-*') ? 'active' : '' }}">Visa</a>
                                        @endif
                                        @if($showPackages)
                                        <a href="{{ route('admin.packages.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}">Tours / Packages</a>
                                        @endif
                                        @if($showGroups)
                                        <a href="{{ route('admin.groups.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.groups.*') ? 'active' : '' }}">Groups / Umrah</a>
                                        @endif
                                        @if($showCms)
                                        <a href="{{ route('admin.content-blocks.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.content-blocks.*', 'admin.landing-pages.*') ? 'active' : '' }}">Pages / CMS</a>
                                        @endif
                                        @if($showBlogs)
                                        <a href="{{ route('admin.blog-posts.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.blog-posts.*') ? 'active' : '' }}">Blogs</a>
                                        @endif
                                </div>
                            </details>
                            @endif

                            @if($showIntegrationSection)
                            <details class="menu-group" {{ $integrationOpen ? 'open' : '' }}>
                                <summary>
                                        API Integrations
                                    <span class="chevron">▾</span>
                                </summary>
                                <div class="list-group list-group-flush p-1">
                                        @if($showIntegrationHub)
                                        <a href="{{ route('admin.integrations.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.integrations.index', 'admin.integrations.create', 'admin.integrations.edit', 'admin.integrations.show') ? 'active' : '' }}">Integration Hub</a>
                                        @endif
                                        @if($showIntegrationCredentials)
                                        <a href="{{ route('admin.integrations.accounts.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.integrations.accounts.*') ? 'active' : '' }}">API Credentials</a>
                                        @endif
                                        @if($showIntegrationProviders)
                                        <a href="{{ route('admin.integrations.providers.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.integrations.providers.*') ? 'active' : '' }}">Provider Modules</a>
                                        @endif
                                        @if($showIntegrationSearchResults)
                                        <a href="{{ route('admin.integrations.search-results.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.integrations.search-results.*') ? 'active' : '' }}">Flight Search Results</a>
                                        @endif
                                        @if($showIntegrationAccessMatrix)
                                        <a href="{{ route('admin.integrations.access-matrix.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.integrations.access-matrix.*') ? 'active' : '' }}">Access Matrix</a>
                                        @endif
                                </div>
                            </details>
                            @endif

                            @if($showPlatformSection)
                            <details class="menu-group" {{ $platformOpen ? 'open' : '' }}>
                                <summary>
                                        Platform Controls
                                    <span class="chevron">▾</span>
                                </summary>
                                <div class="list-group list-group-flush p-1">
                                        @if($showAnalytics)
                                        <a href="{{ route('admin.analytics.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">Reports / Analytics</a>
                                        @endif
                                        @if($showSettings)
                                        <a href="{{ route('admin.settings.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">Settings</a>
                                        @endif
                                        @if($showUsersPermissions)
                                        <a href="{{ route('admin.system.permissions.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.system.permissions.*') ? 'active' : '' }}">Users & Permissions</a>
                                        @endif
                                        @if($showSystem)
                                        <a href="{{ route('admin.system.tenancy.edit') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.system.*') ? 'active' : '' }}">System</a>
                                        @endif
                                        @if($showMonitoring)
                                        <a href="{{ route('admin.monitoring.health-overview') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.monitoring.*') ? 'active' : '' }}">Operations Monitoring</a>
                                        @endif
                                        @if($showDocumentSecurity)
                                        <a href="{{ route('admin.documents.security-settings.edit') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.documents.*') ? 'active' : '' }}">Document Security</a>
                                        @endif
                                        @if($showAirportDirectory)
                                        <a href="{{ route('admin.system.airports.edit') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.system.airports.*') ? 'active' : '' }}">Airport Directory</a>
                                        @endif
                                </div>
                            </details>
                            @endif
                        </div>
                    </div>
                </div>
            </aside>

            <section class="col-12 col-lg-9 col-xl-10 admin-main-column">
                <div class="admin-dash-actions-panel shadow-sm mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="small text-muted">View as</span>
                            <select class="form-select form-select-sm" style="min-width: 108px; width: auto;" disabled aria-label="Currency display">
                                <option selected>PKR</option>
                            </select>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                            @php($u = auth()->user())
                            @php($roleLabel = $u && $u->role instanceof \BackedEnum ? $u->role->value : (string) ($u->role ?? ''))
                            <span class="badge rounded-pill bg-light text-dark border fw-semibold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">{{ $roleLabel !== '' ? str_replace('_', ' ', $roleLabel) : 'User' }}</span>
                            <span class="small fw-semibold text-dark">{{ $u?->name ?? 'Admin' }}</span>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary px-3">Log out</button>
                            </form>
                        </div>
                    </div>
                </div>

                @yield('admin-content')
            </section>
        </div>
    </main>
@endsection

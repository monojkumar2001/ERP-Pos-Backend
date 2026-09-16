<nav class="sidebar">
    <div class="sidebar-header">
        <a href="#" style="display: flex; align-items: center; gap: 10px;" class="sidebar-brand">
           <img style="width: 45px; height: 45px;" src="{{ asset('assets/logo/logo.webp') }}" alt="">
           <p style="font-size: 14px; font-weight: 600; color: #0f172a;">ERP Pos</p>
        </a>
        <div class="sidebar-toggler not-active">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="sidebar-body">
        @php
            $slug = optional(optional(auth()->user())->role)->slug;
            $isAdminPanel = in_array($slug, ['super-admin', 'admin'], true);
            $dashboardRoute = $isAdminPanel ? 'admin.dashboard' : 'user.dashboard';
            $usersRoute = 'admin.user.index';
        @endphp
        <ul class="nav">
            <li class="nav-item nav-category">Main</li>
            <li class="nav-item {{ request()->routeIs($dashboardRoute) ? 'active' : '' }}">
                <a href="{{ route($dashboardRoute) }}" class="nav-link">
                    <i class="link-icon" data-feather="box"></i>
                    <span class="link-title">Dashboard</span>
                </a>
            </li>
            @if ($isAdminPanel)
                <li class="nav-item {{ request()->routeIs('admin.user.*') ? 'active' : '' }}">
                    <a href="{{ route($usersRoute) }}" class="nav-link">
                        <i class="link-icon" data-feather="users"></i>
                        <span class="link-title">Users</span>
                    </a>
                </li>
            @endif
        </ul>
    </div>
</nav>

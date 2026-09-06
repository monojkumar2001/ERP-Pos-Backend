<nav class="sidebar">
    <div class="sidebar-header">
        <a href="#" style="display: flex; align-items: center; gap: 10px;" class="sidebar-brand">
           <img style="width: 45px; height: 45px;" src="{{ asset("assets/logo/logo.webp") }}" alt="">
           <p style="font-size: 14px; font-weight: 600; color: #FFF;">Nawshad Zamir</p>
        </a>
        <div class="sidebar-toggler not-active">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="sidebar-body">
        <ul class="nav">
            <li class="nav-item nav-category">Main</li>
            <li class="nav-item">
                <a href={{ route('admin.dashboard') }} class="nav-link">
                    <i class="link-icon" data-feather="box"></i>
                    <span class="link-title">Dashboard</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

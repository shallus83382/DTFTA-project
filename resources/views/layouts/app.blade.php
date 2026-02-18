<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $branding['app_title'] ?? 'DTFTA CRM')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Dynamic Theme Colors -->
    <style>
        :root {
            --primary-color: {{ $theme['primary_color'] ?? '#667eea' }};
            --secondary-color: {{ $theme['secondary_color'] ?? '#764ba2' }};
            --success-color: {{ $theme['success_color'] ?? '#38a169' }};
            --danger-color: {{ $theme['danger_color'] ?? '#e53e3e' }};
            --warning-color: {{ $theme['warning_color'] ?? '#ed8936' }};
            --info-color: {{ $theme['info_color'] ?? '#3182ce' }};
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-header-content">
                    <h2>{{ $branding['app_name'] ?? 'DTFTA CRM' }}</h2>
                    <p class="subtitle">{{ $branding['app_description'] ?? 'Ops Dashboard' }}</p>
                </div>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">×</button>
            </div>
            <nav class="nav-menu">
                @forelse($navigation as $navItem)
                    @if($navItem['visible'] ?? true)
                        <a href="{{ route($navItem['route']) }}" 
                           class="nav-item {{ request()->routeIs($navItem['route']) ? 'active' : '' }}">
                            <span class="nav-icon {{ $navItem['icon'] }}" aria-hidden="true"></span>
                            <span>{{ $navItem['label'] }}</span>
                        </a>
                    @endif
                @empty
                    <a href="{{ route('crm.dashboard') }}" class="nav-item {{ request()->routeIs('crm.dashboard') ? 'active' : '' }}">
                        <span class="nav-icon nav-icon-dashboard" aria-hidden="true"></span>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('crm.orders') }}" class="nav-item {{ request()->routeIs('crm.orders') ? 'active' : '' }}">
                        <span class="nav-icon nav-icon-orders" aria-hidden="true"></span>
                        <span>Orders / Jobs</span>
                    </a>
                    <a href="{{ route('crm.shipping') }}" class="nav-item {{ request()->routeIs('crm.shipping') ? 'active' : '' }}">
                        <span class="nav-icon nav-icon-shipping" aria-hidden="true"></span>
                        <span>Shipping</span>
                    </a>
                    <a href="{{ route('crm.stores') }}" class="nav-item {{ request()->routeIs('crm.stores') ? 'active' : '' }}">
                        <span class="nav-icon nav-icon-stores" aria-hidden="true"></span>
                        <span>Stores</span>
                    </a>
                    <a href="{{ route('crm.reports') }}" class="nav-item {{ request()->routeIs('crm.reports') ? 'active' : '' }}">
                        <span class="nav-icon nav-icon-reports" aria-hidden="true"></span>
                        <span>Reports</span>
                    </a>
                @endforelse
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="sidebarUserAvatar">A</div>
                    <div class="user-details">
                        <p class="user-name" id="sidebarUserName">Admin</p>
                        <p class="user-role" id="sidebarUserRole">Administrator</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="page-heading">
                    @if(!empty($breadcrumbs ?? []))
                        <nav class="page-breadcrumbs" aria-label="Breadcrumb">
                            @foreach($breadcrumbs as $index => $crumb)
                                @if(!empty($crumb['url']) && $index < count($breadcrumbs) - 1)
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                @else
                                    <span class="current">{{ $crumb['label'] }}</span>
                                @endif
                                @if($index < count($breadcrumbs) - 1)
                                    <span class="separator">/</span>
                                @endif
                            @endforeach
                        </nav>
                    @endif
                    <h1>@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="header-actions">
                    @yield('header-actions')
                    @if($features['notifications']['enabled'] ?? true)
                    <div class="notification-dropdown">
                        <button type="button" class="btn-icon btn-icon-bell notification-trigger" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
                            <span class="notification-badge" id="notificationBadge">{{ $headerNotificationCount ?? 0 }}</span>
                        </button>
                        <div class="notification-dropdown-menu" hidden>
                            <div class="notification-dropdown-header">
                                <span>Notifications</span>
                                <span class="notification-dropdown-count" id="notificationCount">{{ $headerNotificationCount ?? 0 }}</span>
                            </div>
                            <div class="notification-dropdown-list" id="notificationList">
                                @forelse(($headerNotifications ?? []) as $notification)
                                    <a href="{{ $notification['url'] }}" class="notification-dropdown-item">
                                        <span class="notification-item-avatar">{{ $notification['avatar'] }}</span>
                                        <span class="notification-item-text">{!! $notification['text_html'] !!}</span>
                                    </a>
                                @empty
                                    <div class="notification-dropdown-item">
                                        <span class="notification-item-avatar">-</span>
                                        <span class="notification-item-text">No recent notifications</span>
                                    </div>
                                @endforelse
                            </div>
                            <a href="{{ route('crm.notifications') }}" class="notification-dropdown-footer">View all notifications</a>
                        </div>
                    </div>
                    @endif
                    <div class="user-dropdown">
                        <button class="user-dropdown-trigger" type="button" aria-expanded="false" aria-haspopup="true">
                            <span class="user-avatar-sm" id="headerUserAvatar">A</span>
                            <span class="user-dropdown-info">
                                <span class="user-dropdown-name" id="headerUserName">Admin</span>
                                <span class="user-dropdown-plan" id="headerUserRole">Administrator</span>
                            </span>
                            <span class="user-dropdown-chevron" aria-hidden="true"></span>
                        </button>
                        <div class="user-dropdown-menu" hidden>
                            @if($features['settings']['allow_profile_edit'] ?? true)
                            <a href="{{ route('crm.settings') }}" class="user-dropdown-item">
                                <span class="user-dropdown-icon user-dropdown-icon-profile" aria-hidden="true"></span>
                                Profile
                            </a>
                            @endif
                            <button class="user-dropdown-item user-dropdown-item-logout" type="button" onclick="handleLogout()" style="width: 100%; text-align: left; border: none; background: none; cursor: pointer;">
                                <span class="user-dropdown-icon user-dropdown-icon-logout" aria-hidden="true"></span>
                                Log out
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            @yield('content')
        </main>
    </div>

    <!-- Configuration Script -->
    <script>
        // Global configuration object
        window.crmConfig = {
            branding: @json($branding),
            theme: @json($theme),
            features: @json($features),
            statuses: @json($statuses),
            apiBaseUrl: '{{ config("crm.api.base_url") ?? "/api/v1" }}',
            apiTimeout: {{ config("crm.api.timeout") ?? 10000 }}
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.crmAlert = function(message, type = 'info', title = '') {
            if (window.Swal) {
                return Swal.fire({
                    title: title || (type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Notice'),
                    text: String(message || ''),
                    icon: type,
                    confirmButtonColor: '#3b82f6'
                });
            }
            alert(String(message || ''));
            return Promise.resolve();
        };

        window.crmConfirm = async function(message, title = 'Please confirm') {
            if (window.Swal) {
                const result = await Swal.fire({
                    title: String(title),
                    text: String(message || ''),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#64748b'
                });
                return !!result.isConfirmed;
            }
            return confirm(String(message || ''));
        };
    </script>
    <script src="{{ asset('js/api-client.js') }}"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
    <script>
        // Handle logout
        async function handleLogout() {
            try {
                const token = localStorage.getItem('auth_token');
                if (token) {
                    await fetch('/api/v1/auth/logout', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json',
                        }
                    });
                }
            } catch (error) {
                console.error('Logout error:', error);
            }
            
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_info');
            window.location.href = '{{ route("login") }}';
        }

        // Initialize from auth token
        document.addEventListener('DOMContentLoaded', function() {
            let latestUnreadCount = Number(@json((int) ($headerNotificationCount ?? 0)));

            const token = localStorage.getItem('auth_token');
            if (!token) {
                window.location.href = '{{ route("login") }}';
            }
            
            const user = localStorage.getItem('user_info');
            if (user) {
                const userData = JSON.parse(user);
                const avatar = userData.name.charAt(0).toUpperCase();
                document.getElementById('sidebarUserAvatar').textContent = avatar;
                document.getElementById('sidebarUserName').textContent = userData.name;
                document.getElementById('sidebarUserRole').textContent = userData.role === 'admin' ? 'Administrator' : userData.role.charAt(0).toUpperCase() + userData.role.slice(1);
                document.getElementById('headerUserAvatar').textContent = avatar;
                document.getElementById('headerUserName').textContent = userData.name;
                document.getElementById('headerUserRole').textContent = userData.role === 'admin' ? 'Administrator' : userData.role.charAt(0).toUpperCase() + userData.role.slice(1);
            }

            function renderNotifications(items) {
                const list = document.getElementById('notificationList');
                const badge = document.getElementById('notificationBadge');
                const countNode = document.getElementById('notificationCount');
                if (!list || !badge || !countNode) return;

                const count = Array.isArray(items) ? items.length : 0;

                if (!count) {
                    list.innerHTML = '<div class="notification-dropdown-item"><span class="notification-item-avatar">-</span><span class="notification-item-text">No recent notifications</span></div>';
                    return;
                }

                list.innerHTML = items.map(function (item) {
                    const url = item.url || '{{ route("crm.notifications") }}';
                    const avatar = item.avatar || 'N';
                    const text = item.text_html || 'Notification';
                    const unreadClass = item.is_read ? '' : ' unread';
                    const itemId = item.id || '';
                    return '<a href="' + url + '" class="notification-dropdown-item' + unreadClass + '" data-notification-id="' + itemId + '">'
                        + '<span class="notification-item-avatar">' + avatar + '</span>'
                        + '<span class="notification-item-text">' + text + '</span>'
                        + '</a>';
                }).join('');
            }

            function updateNotificationCount(count) {
                const badge = document.getElementById('notificationBadge');
                const countNode = document.getElementById('notificationCount');
                if (!badge || !countNode) return;
                const safeCount = Math.max(0, Number(count || 0));
                latestUnreadCount = safeCount;
                badge.textContent = String(safeCount);
                countNode.textContent = String(safeCount);
                badge.style.display = safeCount > 0 ? 'inline-flex' : 'none';
            }

            function authHeaders() {
                const token = localStorage.getItem('auth_token');
                return token ? { 'Authorization': 'Bearer ' + token } : {};
            }

            async function markNotificationsRead(notificationIds = []) {
                const token = localStorage.getItem('auth_token');
                if (!token) return;
                try {
                    const response = await fetch('/api/v1/notifications/mark-read', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            ...authHeaders()
                        },
                        body: JSON.stringify(notificationIds.length ? { notification_ids: notificationIds } : {})
                    });
                    if (!response.ok) return;
                    const payload = await response.json();
                    updateNotificationCount(payload.unread_count || 0);
                } catch (error) {
                    console.error('Mark read failed', error);
                }
            }

            async function refreshNotifications() {
                try {
                    const response = await fetch('/api/v1/notifications/recent', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            ...authHeaders()
                        }
                    });
                    if (!response.ok) return;
                    const payload = await response.json();
                    if (payload && payload.success) {
                        renderNotifications(payload.notifications || []);
                        updateNotificationCount(payload.unread_count || 0);
                    }
                } catch (error) {
                    console.error('Notification refresh failed', error);
                }
            }

            const notificationList = document.getElementById('notificationList');
            if (notificationList) {
                notificationList.addEventListener('click', function (event) {
                    const anchor = event.target.closest('a.notification-dropdown-item[data-notification-id]');
                    if (!anchor) return;
                    const id = anchor.getAttribute('data-notification-id');
                    if (!id) return;
                    markNotificationsRead([id]);
                });
            }

            window.refreshHeaderNotifications = refreshNotifications;
            window.updateHeaderNotificationCount = updateNotificationCount;
            window.crmNotificationCanOpen = function () {
                return latestUnreadCount > 0;
            };
            window.crmNotificationNoUnreadAction = function () {
                window.location.href = '{{ route("crm.notifications") }}';
            };

            refreshNotifications();
            setInterval(refreshNotifications, 20000);
        });
    </script>
    @stack('scripts')
</body>
</html>




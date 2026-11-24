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

        .crm-order-toast {
            border: 1px solid rgba(59, 130, 246, 0.45) !important;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45) !important;
            border-radius: 10px !important;
        }
    </style>
    
    @stack('styles')

    @if(session('auth_token'))
        <script>
            localStorage.setItem('auth_token', "{{ session('auth_token') }}");
        </script>
    @endif
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
                    <button type="button" class="btn-icon" id="audioNotificationToggle" title="Audio Notifications: Enabled (Click to Mute)" aria-label="Toggle Audio Notifications" style="position:relative; margin-right:4px;">
                        <svg id="audioIconOn" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;">
                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                            <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                        </svg>
                        <svg id="audioIconOff" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none; vertical-align:middle; color:#ef4444;">
                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                            <line x1="23" y1="9" x2="17" y2="15"></line>
                            <line x1="17" y1="9" x2="23" y2="15"></line>
                        </svg>
                    </button>
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
                await fetch('/auth/logout', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Authorization': token ? ('Bearer ' + token) : '',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '',
                    }
                });
            } catch (error) {
                console.error('Logout error:', error);
            }
            
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_info');
            document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
            window.location.href = '{{ route("login") }}';
        }

        // Initialize from auth token
        document.addEventListener('DOMContentLoaded', function() {
            let latestUnreadCount = Number(@json((int) ($headerNotificationCount ?? 0)));
            const user = localStorage.getItem('user_info');
            const authenticatedUser = @json(auth()->user() ? ['name' => auth()->user()->name, 'role' => auth()->user()->role] : null);
            if (user || authenticatedUser) {
                const userData = user ? JSON.parse(user) : authenticatedUser;
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
                try {
                    const response = await fetch('/crm/notifications/mark-read', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '',
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

            // Web Audio API order notification chime
            let audioCtx = null;
            function getAudioContext() {
                if (!audioCtx) {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (AudioContextClass) {
                        audioCtx = new AudioContextClass();
                    }
                }
                if (audioCtx && audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                return audioCtx;
            }

            function unlockAudio() {
                const ctx = getAudioContext();
                if (ctx && ctx.state === 'suspended') {
                    ctx.resume();
                }
                document.removeEventListener('click', unlockAudio);
                document.removeEventListener('keydown', unlockAudio);
                document.removeEventListener('touchstart', unlockAudio);
            }
            document.addEventListener('click', unlockAudio);
            document.addEventListener('keydown', unlockAudio);
            document.addEventListener('touchstart', unlockAudio);

            function playOrderChime() {
                const isEnabled = localStorage.getItem('crm_audio_notifications') !== 'false';
                if (!isEnabled) return;

                try {
                    const ctx = getAudioContext();
                    if (!ctx) return;
                    if (ctx.state === 'suspended') {
                        ctx.resume();
                    }

                    const now = ctx.currentTime;

                    // Note 1: E5 (659.25 Hz)
                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(659.25, now);
                    gain1.gain.setValueAtTime(0.001, now);
                    gain1.gain.exponentialRampToValueAtTime(0.25, now + 0.02);
                    gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.start(now);
                    osc1.stop(now + 0.35);

                    // Note 2: A5 (880.00 Hz) - upbeat notification bell
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(880, now + 0.12);
                    gain2.gain.setValueAtTime(0.001, now + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.3, now + 0.14);
                    gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.85);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start(now + 0.12);
                    osc2.stop(now + 0.85);
                } catch (e) {
                    console.warn('Audio chime playback failed:', e);
                }
            }

            function updateAudioToggleUI() {
                const isEnabled = localStorage.getItem('crm_audio_notifications') !== 'false';
                const iconOn = document.getElementById('audioIconOn');
                const iconOff = document.getElementById('audioIconOff');
                const toggleBtn = document.getElementById('audioNotificationToggle');
                if (iconOn && iconOff) {
                    iconOn.style.display = isEnabled ? 'inline' : 'none';
                    iconOff.style.display = isEnabled ? 'none' : 'inline';
                }
                if (toggleBtn) {
                    toggleBtn.title = isEnabled ? 'Audio Notifications: Enabled (Click to Mute)' : 'Audio Notifications: Muted (Click to Unmute)';
                }
            }

            const audioToggleBtn = document.getElementById('audioNotificationToggle');
            if (audioToggleBtn) {
                updateAudioToggleUI();
                audioToggleBtn.addEventListener('click', function() {
                    const currentlyEnabled = localStorage.getItem('crm_audio_notifications') !== 'false';
                    const newState = !currentlyEnabled;
                    localStorage.setItem('crm_audio_notifications', newState ? 'true' : 'false');
                    updateAudioToggleUI();
                    if (newState) {
                        playOrderChime();
                    }
                });
            }

            function showOrderToast(item) {
                const isJob = (item.entity_type === 'job' || (item.id && String(item.id).startsWith('job_')));
                const isShipment = (item.entity_type === 'shipment' || (item.id && String(item.id).startsWith('shipment_')));
                const title = item.title || (isJob ? 'Job Update' : isShipment ? 'Shipment Update' : 'New Order Received!');
                const message = item.message || (isJob ? 'A job has been updated.' : isShipment ? 'A shipment has been updated.' : 'A new order has been received.');
                const defaultUrl = isShipment ? '{{ route("crm.shipping") }}' : '{{ route("crm.orders") }}';
                const url = item.url || defaultUrl;
                const buttonText = isJob ? 'View Job' : isShipment ? 'View Shipment' : 'View Order';

                if (window.Swal) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: title,
                        html: `<div style="font-size:12px; margin-top:4px; color:#cbd5e1; line-height:1.4;">${message}</div><div style="margin-top:8px;"><a href="${url}" style="display:inline-block; padding:4px 10px; background:#2563eb; color:#fff; border-radius:4px; text-decoration:none; font-size:11px; font-weight:600;">${buttonText}</a></div>`,
                        showConfirmButton: false,
                        timer: 8000,
                        timerProgressBar: true,
                        background: '#0f172a',
                        color: '#f8fafc',
                        customClass: {
                            popup: 'crm-order-toast'
                        },
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                }
            }

            function showBatchNotificationsToast(items) {
                const count = Array.isArray(items) ? items.length : Number(items || 0);
                const itemList = Array.isArray(items) ? items : [];
                const orderCount = itemList.filter(i => (i.entity_type === 'order' || (i.id && String(i.id).startsWith('order_')))).length;
                const jobCount = itemList.filter(i => (i.entity_type === 'job' || (i.id && String(i.id).startsWith('job_')))).length;
                const shipmentCount = itemList.filter(i => (i.entity_type === 'shipment' || (i.id && String(i.id).startsWith('shipment_')))).length;

                let title = `${count} New Notifications`;
                let subtitle = `${count} new notifications received.`;
                let url = '{{ route("crm.notifications") }}';
                let linkText = 'View Notifications &rarr;';

                if (orderCount === count && count > 0) {
                    title = `${count} New Order${count > 1 ? 's' : ''} Received!`;
                    subtitle = `${count} new order${count > 1 ? 's have' : ' has'} just been placed.`;
                    url = '{{ route("crm.orders") }}';
                    linkText = 'View Orders &rarr;';
                } else if (jobCount === count && count > 0) {
                    title = `${count} New Job Update${count > 1 ? 's' : ''}`;
                    subtitle = `${count} job update${count > 1 ? 's' : ''} received.`;
                    url = '{{ route("crm.orders") }}';
                    linkText = 'View Jobs &rarr;';
                } else if (shipmentCount === count && count > 0) {
                    title = `${count} Shipment Update${count > 1 ? 's' : ''}`;
                    subtitle = `${count} shipment update${count > 1 ? 's' : ''} received.`;
                    url = '{{ route("crm.shipping") }}';
                    linkText = 'View Shipments &rarr;';
                } else if (itemList.length > 0) {
                    let parts = [];
                    if (orderCount > 0) parts.push(`${orderCount} order${orderCount > 1 ? 's' : ''}`);
                    if (jobCount > 0) parts.push(`${jobCount} job${jobCount > 1 ? 's' : ''}`);
                    if (shipmentCount > 0) parts.push(`${shipmentCount} shipment${shipmentCount > 1 ? 's' : ''}`);
                    subtitle = parts.join(', ') + ' received.';
                }

                if (window.Swal) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        title: title,
                        html: `<div style="font-size:12px; margin-top:4px; color:#cbd5e1; line-height:1.4;">${subtitle}</div><div style="margin-top:8px;"><a href="${url}" style="display:inline-block; padding:4px 10px; background:#2563eb; color:#fff; border-radius:4px; text-decoration:none; font-size:11px; font-weight:600;">${linkText}</a></div>`,
                        showConfirmButton: false,
                        timer: 8000,
                        timerProgressBar: true,
                        background: '#0f172a',
                        color: '#f8fafc',
                        customClass: {
                            popup: 'crm-order-toast'
                        },
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                }
            }

            function getSeenNotificationIds() {
                try {
                    const raw = sessionStorage.getItem('crm_seen_notifications');
                    return raw ? new Set(JSON.parse(raw)) : new Set();
                } catch (e) {
                    return new Set();
                }
            }

            function saveSeenNotificationIds(set) {
                try {
                    const arr = Array.from(set).slice(-200);
                    sessionStorage.setItem('crm_seen_notifications', JSON.stringify(arr));
                } catch (e) {}
            }

            let seenNotificationIds = getSeenNotificationIds();
            let isFirstPoll = seenNotificationIds.size === 0;

            async function refreshNotifications() {
                try {
                    const response = await fetch('/crm/notifications/recent', {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            ...authHeaders()
                        }
                    });
                    if (!response.ok) return;
                    const payload = await response.json();
                    if (payload && payload.success) {
                        const items = payload.notifications || [];
                        renderNotifications(items);
                        updateNotificationCount(payload.unread_count || 0);

                        if (isFirstPoll) {
                            items.forEach(item => {
                                if (item.id) seenNotificationIds.add(String(item.id));
                            });
                            saveSeenNotificationIds(seenNotificationIds);
                            isFirstPoll = false;
                        } else {
                            let newUnreadItems = [];
                            items.forEach(item => {
                                const itemId = String(item.id || '');
                                if (itemId && !seenNotificationIds.has(itemId)) {
                                    seenNotificationIds.add(itemId);
                                    if (!item.is_read) {
                                        newUnreadItems.push(item);
                                    }
                                }
                            });
                            saveSeenNotificationIds(seenNotificationIds);

                            if (newUnreadItems.length === 1) {
                                playOrderChime();
                                showOrderToast(newUnreadItems[0]);
                            } else if (newUnreadItems.length > 1) {
                                playOrderChime();
                                showBatchNotificationsToast(newUnreadItems);
                            }
                        }
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
            setInterval(refreshNotifications, 10000);
        });
    </script>
    <script src="{{ asset('js/recoverable-form-draft.js') }}?v={{ @filemtime(public_path('js/recoverable-form-draft.js')) ?: '1' }}"></script>
    @stack('scripts')
</body>
</html>



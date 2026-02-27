@extends('layouts.app')

@section('title', 'DTFTA CRM - Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="notifications-toolbar">
    <div class="filter-group">
        <label>Type</label>
        <select class="filter-select" id="notificationTypeFilter">
            <option value="">All</option>
            <option value="order">Orders</option>
            <option value="job">Jobs</option>
            <option value="shipment">Shipments</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Status</label>
        <select class="filter-select" id="notificationReadFilter">
            <option value="all">All</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Action</label>
        <button type="button" class="btn-secondary" id="markAllReadBtn">Mark all as read</button>
    </div>
</div>

<div class="notifications-list" id="notificationsList">
    <div class="notification-item">
        <div class="notification-content">
            <p class="notification-title">Loading notifications...</p>
        </div>
    </div>
</div>

<div class="pagination" id="notificationsPagination"></div>
@endsection

@push('scripts')
<script>
let notificationPage = 1;
let notificationLastPage = 1;

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('notificationTypeFilter')?.addEventListener('change', function () {
        notificationPage = 1;
        loadNotifications();
    });
    document.getElementById('notificationReadFilter')?.addEventListener('change', function () {
        notificationPage = 1;
        loadNotifications();
    });
    document.getElementById('markAllReadBtn')?.addEventListener('click', markAllNotificationsRead);

    loadNotifications();
});

function getAuthHeaders() {
    const token = localStorage.getItem('auth_token');
    return token ? { 'Authorization': 'Bearer ' + token } : {};
}

async function loadNotifications(page = notificationPage) {
    notificationPage = page;
    const type = document.getElementById('notificationTypeFilter')?.value || '';
    const read = document.getElementById('notificationReadFilter')?.value || 'all';
    const params = new URLSearchParams({
        page: String(notificationPage),
        per_page: '12',
        read: read
    });
    if (type) params.set('type', type);

    try {
        const response = await fetch('/crm/notifications/data?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                ...getAuthHeaders()
            }
        });

        if (response.status === 401) {
            window.location.href = '{{ route("login") }}';
            return;
        }

        const payload = await response.json();
        const items = payload.data || [];
        notificationLastPage = payload.pagination?.last_page || 1;
        renderNotificationsList(items);
        renderPagination(payload.pagination);

        if (typeof window.updateHeaderNotificationCount === 'function') {
            window.updateHeaderNotificationCount(payload.unread_count || 0);
        }
    } catch (error) {
        document.getElementById('notificationsList').innerHTML = '<div class="notification-item"><div class="notification-content"><p class="notification-title">Failed to load notifications</p></div></div>';
    }
}

function renderNotificationsList(items) {
    const list = document.getElementById('notificationsList');
    if (!list) return;

    if (!items.length) {
        list.innerHTML = '<div class="notification-item"><div class="notification-content"><p class="notification-title">No notifications found</p></div></div>';
        return;
    }

    list.innerHTML = items.map(function(item) {
        const unreadClass = item.is_read ? '' : 'unread';
        const avatar = escapeHtml(item.avatar || 'N');
        const title = escapeHtml(item.title || 'Notification');
        const message = escapeHtml(item.message || '');
        const timeAgo = escapeHtml(item.time_ago || '');
        const itemUrl = item.url || '{{ route("crm.notifications") }}';
        return '<div class="notification-item ' + unreadClass + '" data-notification-id="' + escapeHtml(item.id) + '">'
            + '<div class="notification-icon status-new">' + avatar + '</div>'
            + '<div class="notification-content">'
            + '<p class="notification-title">' + title + '</p>'
            + '<p class="notification-text">' + message + '</p>'
            + '<span class="notification-time">' + timeAgo + '</span>'
            + '</div>'
            + '<a class="btn-link" href="' + itemUrl + '" onclick="markOneNotificationRead(event, \'' + escapeJs(item.id) + '\')">Open</a>'
            + '</div>';
    }).join('');
}

function renderPagination(pagination) {
    const node = document.getElementById('notificationsPagination');
    if (!node) return;

    if (!pagination || (pagination.last_page || 1) <= 1) {
        node.innerHTML = '';
        return;
    }

    const current = pagination.current_page || 1;
    const last = pagination.last_page || 1;
    const prevDisabled = current <= 1 ? 'disabled' : '';
    const nextDisabled = current >= last ? 'disabled' : '';
    node.innerHTML = '<button class="btn-pagination" ' + prevDisabled + ' onclick="loadNotifications(' + (current - 1) + ')">Previous</button>'
        + '<span class="page-info">Page ' + current + ' of ' + last + '</span>'
        + '<button class="btn-pagination" ' + nextDisabled + ' onclick="loadNotifications(' + (current + 1) + ')">Next</button>';
}

async function markAllNotificationsRead() {
    try {
        await fetch('/crm/notifications/mark-read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '',
                ...getAuthHeaders()
            },
            body: JSON.stringify({})
        });
        await loadNotifications();
        if (typeof window.refreshHeaderNotifications === 'function') {
            window.refreshHeaderNotifications();
        }
    } catch (error) {
        console.error('Failed to mark all notifications read', error);
    }
}

async function markOneNotificationRead(event, notificationId) {
    if (!notificationId) return;
    try {
        await fetch('/crm/notifications/mark-read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '',
                ...getAuthHeaders()
            },
            body: JSON.stringify({ notification_ids: [notificationId] })
        });
        if (typeof window.refreshHeaderNotifications === 'function') {
            window.refreshHeaderNotifications();
        }
    } catch (error) {
        console.error('Failed to mark notification read', error);
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeJs(value) {
    return String(value).replaceAll('\\', '\\\\').replaceAll("'", "\\'");
}
</script>
@endpush

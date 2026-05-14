@extends('layouts.app')

@section('title', 'DTFTA CRM - Users')
@section('page-title', 'Users')

@section('content')
<div class="users-page">
    <div class="users-hero">
        <div>
            <p class="users-eyebrow">Administration</p>
            <h2>Users Control Center</h2>
            <p class="users-subtitle">Manage user access, roles, and account lifecycle from one place.</p>
        </div>
    </div>

    <div class="filters-section users-filters">
        <div class="filter-group">
            <label>Search</label>
            <input type="text" id="usersSearch" class="filter-select" placeholder="Name or email" value="" autocomplete="off" spellcheck="false">
        </div>
        <div class="filter-group">
            <label>Role</label>
            <select id="usersRole" class="filter-select">
                <option value="">All Roles</option>
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="user">User</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select id="usersStatus" class="filter-select">
                <option value="">All</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
        <div class="users-filter-actions">
            <button class="btn-secondary" type="button" onclick="loadUsers(1)">Apply</button>
            <button class="btn-secondary" type="button" onclick="clearUsersFilters()">Clear</button>
            <button class="btn-primary" type="button" onclick="openCreateUserForm()">Add User</button>
        </div>
    </div>

    <div class="users-form-shell" id="userFormShell" style="display:none;">
        <div class="users-form-header">
            <h3 id="userFormTitle">Add User</h3>
        </div>

        <div class="users-form-grid">
            <div class="form-group">
                <label>Name</label>
                <input type="text" id="formUserName" placeholder="Full name">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="formUserEmail" placeholder="Email">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="formUserRole" class="filter-select">
                    <option value="user">User</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="formUserActive" class="filter-select">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" id="formUserPhone" placeholder="+1-000-000-0000">
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea id="formUserAddress" placeholder="Address"></textarea>
            </div>
        </div>

        <div class="users-form-grid" id="createPasswordBlock">
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="formUserPassword" placeholder="At least 8 characters">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" id="formUserPasswordConfirm" placeholder="Confirm password">
            </div>
        </div>

        <div class="users-form-actions">
            <button class="btn-secondary" type="button" onclick="closeUserForm()">Cancel</button>
            <button class="btn-primary" type="button" onclick="saveUser()">Save</button>
        </div>
    </div>

    <div class="table-container users-table-shell">
        <table class="jobs-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Phone</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <tr><td colspan="7" style="text-align:center;">Loading users...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="pagination" id="usersPagination"></div>
</div>
@endsection

@push('styles')
<style>
.users-page {
    display: grid;
    gap: 14px;
}

.users-page .users-hero {
    border-radius: 16px;
    border: 1px solid rgba(96, 165, 250, 0.25);
    background:
        radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), transparent 46%),
        linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.96));
    box-shadow: 0 18px 34px rgba(2, 6, 23, 0.34);
    padding: 16px 18px;
}

.users-page .users-eyebrow {
    margin: 0 0 6px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #93c5fd;
    font-weight: 700;
}

.users-page .users-hero h2 {
    margin: 0;
    color: #f8fafc;
    font-size: 26px;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.users-page .users-subtitle {
    margin: 9px 0 0;
    color: #a9bddb;
    font-size: 14px;
}

.users-page .users-filters {
    margin: 0;
    padding: 14px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.24);
    background:
        radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 48%),
        linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.92));
    box-shadow: 0 14px 28px rgba(2, 6, 23, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.03);
    display: grid;
    grid-template-columns: repeat(3, minmax(170px, 1fr)) auto;
    gap: 12px;
    align-items: end;
}

.users-page .users-filters .filter-group {
    margin: 0;
}

.users-page .users-filters .filter-group label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #b5c9e8;
    font-weight: 700;
}

.users-page .users-filters .filter-select {
    height: 42px;
    min-height: 42px;
    border-radius: 10px;
    border-color: rgba(148, 163, 184, 0.32);
    background-color: rgba(15, 23, 42, 0.8);
    color: #f8fafc;
    font-size: 13px;
    font-weight: 600;
    min-width: 0;
}

.users-page .users-filter-actions {
    display: grid;
    grid-template-columns: repeat(3, minmax(102px, 1fr));
    gap: 10px;
    min-width: 340px;
}

.users-page .users-filter-actions .btn-secondary,
.users-page .users-filter-actions .btn-primary {
    height: 42px;
    min-height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 14px;
    margin: 0;
    line-height: 1;
    font-size: 13px;
    font-weight: 700;
    box-sizing: border-box;
    appearance: none;
    -webkit-appearance: none;
    white-space: nowrap;
}

.users-page .users-filter-actions .btn-primary {
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.28);
}

.users-form-shell {
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.24);
    background:
        radial-gradient(circle at top right, rgba(56, 189, 248, 0.09), transparent 45%),
        linear-gradient(165deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.94));
    box-shadow: 0 14px 30px rgba(2, 6, 23, 0.28);
    padding: 16px;
    margin-bottom: 2px;
}

.users-form-header {
    margin-bottom: 14px;
}

.users-form-header h3 {
    color: #f8fafc;
    font-size: 21px;
    letter-spacing: -0.01em;
}

.users-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 10px;
}

.users-form-shell .form-group {
    margin-bottom: 0;
}

.users-form-shell .form-group label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #a9bddb;
    font-weight: 700;
}

.users-form-shell .form-group input,
.users-form-shell .form-group textarea,
.users-form-shell .form-group select {
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.3);
    background-color: rgba(15, 23, 42, 0.82);
    color: #f8fafc;
    font-size: 13px;
}

.users-form-shell textarea {
    min-height: 84px;
    resize: vertical;
}

.users-form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.users-form-actions .btn-secondary,
.users-form-actions .btn-primary {
    height: 40px;
    min-height: 40px;
    min-width: 110px;
    padding: 0 14px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.role-pill {
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    border-radius: 999px;
    padding: 4px 10px;
    letter-spacing: 0.3px;
}

.role-pill.role-admin {
    background: rgba(239, 68, 68, 0.2);
    color: #fda4af;
}

.role-pill.role-manager {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}

.role-pill.role-user {
    background: rgba(16, 185, 129, 0.2);
    color: #6ee7b7;
}

.users-table-shell {
    margin-top: 0;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background:
        radial-gradient(circle at top right, rgba(56, 189, 248, 0.08), transparent 44%),
        linear-gradient(165deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.94));
    box-shadow: 0 14px 30px rgba(2, 6, 23, 0.28);
}

.users-table-shell .jobs-table th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #a9bddf;
    background: rgba(15, 23, 42, 0.52);
    border-bottom-color: rgba(148, 163, 184, 0.2);
}

.users-table-shell .jobs-table td {
    border-bottom-color: rgba(148, 163, 184, 0.15);
    vertical-align: middle;
}

.users-table-shell .jobs-table tbody tr:hover {
    background: linear-gradient(120deg, rgba(59, 130, 246, 0.1), rgba(15, 23, 42, 0.16));
}

.users-action-group {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 8px;
}

.users-action-btn {
    background: linear-gradient(135deg, rgba(71, 85, 105, 0.9), rgba(51, 65, 85, 0.82));
    border: 1px solid rgba(148, 163, 184, 0.35);
    color: #e5eefb;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    padding: 0 10px;
    height: 32px;
    min-height: 32px;
    border-radius: 8px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.users-action-btn:hover {
    border-color: rgba(125, 211, 252, 0.55);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.28), rgba(37, 99, 235, 0.16));
    color: #f8fbff;
}

.users-action-btn.danger {
    color: #fee2e2;
    border-color: rgba(248, 113, 113, 0.4);
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.82), rgba(185, 28, 28, 0.84));
}

.users-action-btn.danger:hover {
    border-color: rgba(252, 165, 165, 0.7);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.94), rgba(220, 38, 38, 0.92));
}

.users-page .pagination {
    margin-top: 2px;
}

@media (max-width: 900px) {
    .users-page .users-filters {
        grid-template-columns: 1fr;
    }

    .users-page .users-filter-actions {
        grid-template-columns: 1fr;
        min-width: 0;
    }

    .users-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@push('scripts')
<script>
let usersPage = 1;
let usersLastPage = 1;
let editingUserId = null;
let usersCache = [];

document.addEventListener('DOMContentLoaded', async function () {
    const role = await getCurrentRole();
    if (role !== 'admin') {
        await window.crmAlert('Only admin can access Users module', 'warning');
        window.location.href = '/crm/dashboard';
        return;
    }

    const searchInput = document.getElementById('usersSearch');
    searchInput.value = '';
    searchInput.addEventListener('input', debounce(() => loadUsers(1), 350));
    loadUsers(1);
});

function getAuthHeaders() {
    const token = localStorage.getItem('auth_token');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headers = {
        'X-CSRF-TOKEN': csrf
    };
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    return headers;
}

async function getCurrentRole() {
    try {
        const response = await fetch('/auth/me', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', ...getAuthHeaders() }
        });
        if (!response.ok) return null;
        const payload = await response.json();
        return payload.user?.role || null;
    } catch (error) {
        return null;
    }
}

function clearUsersFilters() {
    document.getElementById('usersSearch').value = '';
    document.getElementById('usersRole').value = '';
    document.getElementById('usersStatus').value = '';
    loadUsers(1);
}

async function loadUsers(page) {
    usersPage = page;
    const role = document.getElementById('usersRole').value;
    const active = document.getElementById('usersStatus').value;
    const search = document.getElementById('usersSearch').value.trim().toLowerCase();

    const params = new URLSearchParams({ page: String(page), per_page: '12' });
    if (role) params.set('role', role);
    if (active !== '') params.set('active', active);

    try {
        const response = await fetch('/crm/admin/users?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', ...getAuthHeaders() }
        });

        if (response.status === 401) {
            window.location.href = '/login';
            return;
        }

        const payload = await response.json();
        usersCache = (payload.data || []).filter(u => {
            if (!search) return true;
            return (u.name || '').toLowerCase().includes(search) || (u.email || '').toLowerCase().includes(search);
        });
        usersLastPage = payload.pagination?.last_page || 1;
        renderUsersTable();
        renderPagination(payload.pagination);
    } catch (error) {
        document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="7" style="text-align:center;">Failed to load users</td></tr>';
    }
}

function prependCreatedUserToTable(user) {
    if (!user || typeof user !== 'object') return;
    usersCache = [user, ...usersCache.filter(u => u.id !== user.id)];
    renderUsersTable();
}

function renderUsersTable() {
    const tbody = document.getElementById('usersTableBody');
    if (!usersCache.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No users found</td></tr>';
        return;
    }

    tbody.innerHTML = usersCache.map((user) => {
        const roleClass = 'role-' + (user.role || 'user');
        const statusClass = user.is_active ? 'status-shipped' : 'status-exception';
        const statusText = user.is_active ? 'Active' : 'Inactive';
        const createdAt = user.created_at ? new Date(user.created_at).toLocaleDateString() : '-';
        return `
            <tr>
                <td>${escapeHtml(user.name || '-')}</td>
                <td>${escapeHtml(user.email || '-')}</td>
                <td><span class="role-pill ${roleClass}">${escapeHtml(user.role || 'user')}</span></td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>${escapeHtml(user.phone || '-')}</td>
                <td>${escapeHtml(createdAt)}</td>
                <td>
                    <div class="users-action-group">
                    <button class="users-action-btn" type="button" onclick="openEditUserForm(${user.id})">Edit</button>
                    <button class="users-action-btn" type="button" onclick="toggleUserStatus(${user.id})">${user.is_active ? 'Deactivate' : 'Activate'}</button>
                    <button class="users-action-btn danger" type="button" onclick="deleteUser(${user.id})">Delete</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination(pagination) {
    const node = document.getElementById('usersPagination');
    if (!pagination || (pagination.last_page || 1) <= 1) {
        node.innerHTML = '';
        return;
    }

    const current = pagination.current_page || 1;
    const last = pagination.last_page || 1;
    const prevDisabled = current <= 1 ? 'disabled' : '';
    const nextDisabled = current >= last ? 'disabled' : '';

    node.innerHTML = `
        <button class="btn-pagination" ${prevDisabled} onclick="loadUsers(${current - 1})">Previous</button>
        <span class="page-info">Page ${current} of ${last}</span>
        <button class="btn-pagination" ${nextDisabled} onclick="loadUsers(${current + 1})">Next</button>
    `;
}

function openCreateUserForm() {
    editingUserId = null;
    document.getElementById('userFormTitle').textContent = 'Add User';
    document.getElementById('createPasswordBlock').style.display = 'grid';
    resetUserForm();
    document.getElementById('userFormShell').style.display = 'block';
    document.getElementById('userFormShell').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function openEditUserForm(userId) {
    const user = usersCache.find(u => u.id === userId);
    if (!user) return;

    editingUserId = userId;
    document.getElementById('userFormTitle').textContent = 'Edit User';
    document.getElementById('createPasswordBlock').style.display = 'none';

    document.getElementById('formUserName').value = user.name || '';
    document.getElementById('formUserEmail').value = user.email || '';
    document.getElementById('formUserRole').value = user.role || 'user';
    document.getElementById('formUserActive').value = user.is_active ? '1' : '0';
    document.getElementById('formUserPhone').value = user.phone || '';
    document.getElementById('formUserAddress').value = user.address || '';

    document.getElementById('userFormShell').style.display = 'block';
    document.getElementById('userFormShell').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeUserForm() {
    document.getElementById('userFormShell').style.display = 'none';
    resetUserForm();
}

function resetUserForm() {
    document.getElementById('formUserName').value = '';
    document.getElementById('formUserEmail').value = '';
    document.getElementById('formUserRole').value = 'user';
    document.getElementById('formUserActive').value = '1';
    document.getElementById('formUserPhone').value = '';
    document.getElementById('formUserAddress').value = '';
    document.getElementById('formUserPassword').value = '';
    document.getElementById('formUserPasswordConfirm').value = '';
}

async function saveUser() {
    const payload = {
        name: document.getElementById('formUserName').value.trim(),
        email: document.getElementById('formUserEmail').value.trim(),
        role: document.getElementById('formUserRole').value,
        is_active: document.getElementById('formUserActive').value === '1',
        phone: document.getElementById('formUserPhone').value.trim(),
        address: document.getElementById('formUserAddress').value.trim()
    };

    if (!payload.name || !payload.email) {
        window.crmAlert('Name and email are required', 'warning');
        return;
    }

    try {
        if (editingUserId) {
            const response = await fetch('/crm/admin/users/' + editingUserId, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...getAuthHeaders() },
                body: JSON.stringify(payload)
            });
            if (!response.ok) throw new Error('Unable to update user');
            await loadUsers(usersPage);
        } else {
            const pass = document.getElementById('formUserPassword').value;
            const passConf = document.getElementById('formUserPasswordConfirm').value;
            if (pass.length < 8) {
                window.crmAlert('Password must be at least 8 characters', 'warning');
                return;
            }
            if (pass !== passConf) {
                window.crmAlert('Password confirmation does not match', 'warning');
                return;
            }
            const response = await fetch('/crm/admin/users', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...getAuthHeaders() },
                body: JSON.stringify({
                    ...payload,
                    password: pass,
                    password_confirmation: passConf
                })
            });
            if (!response.ok) throw new Error('Unable to create user');

            const createdPayload = await response.json();
            if (createdPayload && createdPayload.user) {
                prependCreatedUserToTable(createdPayload.user);
            }
            await loadUsers(1);
        }

        closeUserForm();
    } catch (error) {
        window.crmAlert(error.message || 'Failed to save user', 'error');
    }
}

async function toggleUserStatus(userId) {
    const user = usersCache.find(u => u.id === userId);
    if (!user) return;
    try {
        const response = await fetch('/crm/admin/users/' + userId, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...getAuthHeaders() },
            body: JSON.stringify({ is_active: !user.is_active })
        });
        if (!response.ok) throw new Error('Unable to update status');
        loadUsers(usersPage);
    } catch (error) {
        window.crmAlert(error.message || 'Failed to update status', 'error');
    }
}

async function deleteUser(userId) {
    const ok = await window.crmConfirm('Delete this user?', 'Confirm Delete');
    if (!ok) return;
    try {
        const response = await fetch('/crm/admin/users/' + userId, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', ...getAuthHeaders() }
        });
        if (!response.ok) throw new Error('Unable to delete user');
        loadUsers(usersPage);
    } catch (error) {
        window.crmAlert(error.message || 'Failed to delete user', 'error');
    }
}

function debounce(fn, wait) {
    let t;
    return function () {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, arguments), wait);
    };
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>
@endpush

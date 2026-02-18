@extends('layouts.app')

@section('title', 'DTFTA CRM - Users')
@section('page-title', 'Users')

@section('content')
<div class="users-page">
    <div class="filters-section">
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
        <button class="btn-secondary" type="button" onclick="loadUsers(1)">Apply</button>
        <button class="btn-secondary" type="button" onclick="clearUsersFilters()">Clear</button>
        <button class="btn-primary" type="button" onclick="openCreateUserForm()">Add User</button>
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

    <div class="table-container">
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
.users-page .filters-section .filter-select {
    min-width: 160px;
}

.users-form-shell {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 18px;
}

.users-form-header {
    margin-bottom: 12px;
}

.users-form-header h3 {
    color: var(--text-primary);
    font-size: 18px;
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

.users-form-shell textarea {
    min-height: 84px;
    resize: vertical;
}

.users-form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
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

.users-action-btn {
    background: transparent;
    border: none;
    color: var(--accent-blue);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    margin-right: 10px;
}

.users-action-btn:hover {
    text-decoration: underline;
}

.users-action-btn.danger {
    color: #f87171;
}

@media (max-width: 900px) {
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
    return token ? { 'Authorization': 'Bearer ' + token } : {};
}

async function getCurrentRole() {
    try {
        const response = await fetch('/api/v1/auth/me', {
            method: 'GET',
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
        const response = await fetch('/api/v1/users?' + params.toString(), {
            method: 'GET',
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
                    <button class="users-action-btn" type="button" onclick="openEditUserForm(${user.id})">Edit</button>
                    <button class="users-action-btn" type="button" onclick="toggleUserStatus(${user.id})">${user.is_active ? 'Deactivate' : 'Activate'}</button>
                    <button class="users-action-btn danger" type="button" onclick="deleteUser(${user.id})">Delete</button>
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
            const response = await fetch('/api/v1/users/' + editingUserId, {
                method: 'PUT',
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
            const response = await fetch('/api/v1/users', {
                method: 'POST',
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
        const response = await fetch('/api/v1/users/' + userId, {
            method: 'PUT',
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
        const response = await fetch('/api/v1/users/' + userId, {
            method: 'DELETE',
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

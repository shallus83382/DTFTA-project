@extends('layouts.app')

@section('title', 'DTFTA CRM - Settings')
@section('page-title', 'Settings')

@section('content')
<div class="settings-page">
    <div class="settings-shell">
        <div class="settings-shell-header">
            <h2>Account Settings</h2>
            <p>Manage your profile, credentials, and notification preferences.</p>
        </div>

        <div class="settings-tabs">
            <button class="settings-tab active" onclick="switchTab('profile')">Profile</button>
            <button class="settings-tab" onclick="switchTab('password')">Password</button>
        </div>

        <div id="profileTab" class="settings-content">
            <div class="settings-panel">
                <h3>Your Profile</h3>
                <p class="panel-subtitle">Keep your contact details up to date for team visibility.</p>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="fullName" placeholder="Your full name">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="email" placeholder="your@email.com" disabled>
                    <p class="input-hint">Email cannot be changed</p>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" placeholder="+1-800-000-0000">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea id="address" placeholder="Your address..."></textarea>
                </div>

                <div class="settings-grid-2">
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" id="role" disabled>
                        <p class="input-hint">Role cannot be changed</p>
                    </div>

                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" id="memberSince" disabled>
                    </div>
                </div>

                <button class="btn-primary" onclick="saveProfile()">Save Changes</button>
            </div>
        </div>

        <div id="passwordTab" class="settings-content" style="display: none;">
            <div class="settings-panel">
                <h3>Change Password</h3>
                <p class="panel-subtitle">Use a strong password to secure your account access.</p>

                <div class="form-group">
                    <label>Current Password</label>
                    <div class="password-field">
                        <input type="password" id="currentPassword" placeholder="Enter current password">
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('currentPassword')">Show</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-field">
                        <input type="password" id="newPassword" placeholder="Enter new password">
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword')">Show</button>
                    </div>
                    <p class="input-hint">Password must be at least 8 characters</p>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" id="confirmPassword" placeholder="Confirm new password">
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')">Show</button>
                    </div>
                </div>

                <button class="btn-primary" onclick="changePassword()">Update Password</button>
            </div>
        </div>

    </div>

    <div class="danger-zone">
        <h3>Danger Zone</h3>
        <p>These actions cannot be undone. Please proceed carefully.</p>
        <button class="btn-danger" onclick="logout()">Logout All Devices</button>
    </div>
</div>
@endsection

@push('styles')
<style>
.settings-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.settings-shell {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.settings-shell-header {
    padding: 22px 24px 0 24px;
}

.settings-shell-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.settings-shell-header p {
    color: var(--text-muted);
    font-size: 14px;
}

.settings-tabs {
    margin-top: 18px;
    display: flex;
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}

.settings-tab {
    flex: 1;
    padding: 14px 16px;
    background: none;
    border: none;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.25s ease;
}

.settings-tab.active {
    color: var(--text-primary);
    box-shadow: inset 0 -2px 0 var(--accent-blue);
    background: rgba(59, 130, 246, 0.12);
}

.settings-tab:hover {
    background: rgba(148, 163, 184, 0.08);
}

.settings-content {
    padding: 24px;
}

.settings-panel {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 22px;
}

.settings-panel h3 {
    color: var(--text-primary);
    font-size: 18px;
    margin-bottom: 4px;
}

.panel-subtitle {
    color: var(--text-muted);
    font-size: 13px;
    margin-bottom: 18px;
}

.settings-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.settings-option-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
}

.password-field {
    position: relative;
}

.form-group {
    margin-bottom: 14px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
    letter-spacing: 0.2px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
}

.form-group input:disabled {
    background: rgba(148, 163, 184, 0.12);
    color: var(--text-muted);
}

.input-hint {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 6px;
}

.password-toggle {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    padding: 4px 8px;
    cursor: pointer;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-primary);
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    cursor: pointer;
}

.checkbox-label span {
    display: flex;
    flex-direction: column;
    color: var(--text-primary);
}

.checkbox-label small {
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 12px;
}

.btn-primary {
    margin-top: 4px;
}

.danger-zone {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.35);
    border-left: 4px solid #f56565;
    border-radius: 12px;
    padding: 20px;
}

.danger-zone h3 {
    color: #f87171;
    margin-bottom: 6px;
}

.danger-zone p {
    color: #fecaca;
    margin-bottom: 14px;
    font-size: 14px;
}

.btn-danger {
    background: #f56565;
    color: white;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background: #e53e3e;
}

@media (max-width: 900px) {
    .settings-grid-2 {
        grid-template-columns: 1fr;
    }

    .settings-tabs {
        flex-wrap: wrap;
    }
}
</style>
@endpush

@push('scripts')
<script>
let currentUser = null;

document.addEventListener('DOMContentLoaded', async function() {
    await loadUserProfile();
});

async function loadUserProfile() {
    try {
        const response = await apiClient.getCurrentUser();
        
        if (response.ok && response.data) {
            currentUser = response.data;
            
            // Populate profile tab
            document.getElementById('fullName').value = currentUser.name || '';
            document.getElementById('email').value = currentUser.email || '';
            document.getElementById('phone').value = currentUser.phone || '';
            document.getElementById('address').value = currentUser.address || '';
            document.getElementById('role').value = (currentUser.role || 'user').toUpperCase();
            document.getElementById('memberSince').value = currentUser.created_at ? new Date(currentUser.created_at).toLocaleDateString() : '';
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

function switchTab(tab) {
    document.querySelectorAll('.settings-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.settings-tab').forEach(el => el.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tab + 'Tab').style.display = 'block';
    event.target.classList.add('active');
}

async function saveProfile() {
    try {
        const response = await apiClient.updateUser(currentUser.id, {
            name: document.getElementById('fullName').value,
            phone: document.getElementById('phone').value,
            address: document.getElementById('address').value
        });
        
        if (response.ok) {
            window.crmAlert('Profile updated successfully', 'success');
            await loadUserProfile();
        }
    } catch (error) {
        window.crmAlert('Error updating profile', 'error');
    }
}

async function changePassword() {
    const current = document.getElementById('currentPassword').value;
    const newPass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;

    if (!current || !newPass || !confirm) {
        window.crmAlert('Please fill in all password fields', 'warning');
        return;
    }
    if (newPass !== confirm) {
        window.crmAlert('Passwords do not match', 'warning');
        return;
    }
    if (newPass.length < 8) {
        window.crmAlert('Password must be at least 8 characters', 'warning');
        return;
    }

    try {
        const response = await apiClient.changePassword({
            current_password: current,
            new_password: newPass,
            new_password_confirmation: confirm
        });
        
        if (response.ok) {
            window.crmAlert('Password changed successfully', 'success');
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        } else {
            window.crmAlert(response.error || 'Error changing password', 'error');
        }
    } catch (error) {
        window.crmAlert('Error changing password: ' + error.message, 'error');
    }
}

function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

async function logout() {
    const ok = await window.crmConfirm('Are you sure you want to logout from all devices?', 'Confirm Logout');
    if (!ok) {
        return;
    }
    
    try {
        await apiClient.logout();
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        window.location.href = '/login';
    } catch (error) {
        console.error('Error logging out:', error);
    }
}
</script>
@endpush

@extends('layouts.app')

@section('title', 'DTFTA CRM - Settings')
@section('page-title', 'Settings')

@section('content')
<!-- Settings Tabs -->
<div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
    <div style="border-bottom: 1px solid #e2e8f0; display: flex;">
        <button class="settings-tab active" onclick="switchTab('profile')">Profile</button>
        <button class="settings-tab" onclick="switchTab('password')">Password</button>
        <button class="settings-tab" onclick="switchTab('preferences')">Preferences</button>
    </div>

    <!-- Profile Tab -->
    <div id="profileTab" class="settings-content">
        <div style="padding: 2rem;">
            <h3 style="margin-bottom: 1.5rem;">Your Profile</h3>
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="fullName" placeholder="Your full name">
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="email" placeholder="your@email.com" disabled>
                <p style="color: #718096; font-size: 0.85rem; margin-top: 0.25rem;">Email cannot be changed</p>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="phone" placeholder="+1-800-000-0000">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea id="address" placeholder="Your address..." style="height: 100px;"></textarea>
            </div>

            <div class="form-group">
                <label>Role</label>
                <input type="text" id="role" disabled>
                <p style="color: #718096; font-size: 0.85rem; margin-top: 0.25rem;">Role cannot be changed</p>
            </div>

            <div class="form-group">
                <label>Member Since</label>
                <input type="text" id="memberSince" disabled>
            </div>

            <button class="btn-primary" onclick="saveProfile()" style="margin-top: 1rem;">Save Changes</button>
        </div>
    </div>

    <!-- Password Tab -->
    <div id="passwordTab" class="settings-content" style="display: none;">
        <div style="padding: 2rem;">
            <h3 style="margin-bottom: 1.5rem;">Change Password</h3>
            
            <div class="form-group">
                <label>Current Password</label>
                <div style="position: relative;">
                    <input type="password" id="currentPassword" placeholder="Enter current password">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('currentPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #718096;">
                        👁️
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <div style="position: relative;">
                    <input type="password" id="newPassword" placeholder="Enter new password">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #718096;">
                        👁️
                    </button>
                </div>
                <p style="color: #718096; font-size: 0.85rem; margin-top: 0.25rem;">Password must be at least 8 characters</p>
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <div style="position: relative;">
                    <input type="password" id="confirmPassword" placeholder="Confirm new password">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #718096;">
                        👁️
                    </button>
                </div>
            </div>

            <button class="btn-primary" onclick="changePassword()" style="margin-top: 1rem;">Update Password</button>
        </div>
    </div>

    <!-- Preferences Tab -->
    <div id="preferencesTab" class="settings-content" style="display: none;">
        <div style="padding: 2rem;">
            <h3 style="margin-bottom: 1.5rem;">Preferences</h3>
            
            <div style="margin-bottom: 1.5rem;">
                <label class="checkbox-label">
                    <input type="checkbox" id="emailNotifications" checked>
                    <span><strong>Email Notifications</strong><br><small style="color: #718096;">Receive email updates</small></span>
                </label>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="checkbox-label">
                    <input type="checkbox" id="smsNotifications">
                    <span><strong>SMS Notifications</strong><br><small style="color: #718096;">Receive SMS alerts</small></span>
                </label>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="checkbox-label">
                    <input type="checkbox" id="dailySummary" checked>
                    <span><strong>Daily Summary</strong><br><small style="color: #718096;">Get daily summary emails</small></span>
                </label>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="checkbox-label">
                    <input type="checkbox" id="importantAlerts" checked>
                    <span><strong>Important Alerts Only</strong><br><small style="color: #718096;">Only notify for important issues</small></span>
                </label>
            </div>

            <button class="btn-primary" onclick="savePreferences()" style="margin-top: 1rem;">Save Preferences</button>
        </div>
    </div>
</div>

<!-- Danger Zone -->
<div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 2rem; padding: 2rem; border-left: 4px solid #f56565;">
    <h3 style="color: #f56565; margin-bottom: 1rem;">Danger Zone</h3>
    <p style="color: #718096; margin-bottom: 1rem;">These actions cannot be undone. Please be careful.</p>
    
    <button class="btn-danger" onclick="logout()">Logout All Devices</button>
</div>

@endsection

@push('styles')
<style>
.settings-tab {
    flex: 1;
    padding: 1rem;
    background: none;
    border: none;
    font-size: 0.95rem;
    font-weight: 600;
    color: #718096;
    cursor: pointer;
    transition: all 0.3s ease;
}

.settings-tab.active {
    color: #4299e1;
    border-bottom: 3px solid #4299e1;
}

.settings-tab:hover {
    background: #f7fafc;
}

.settings-content {
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #1a202c;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.95rem;
}

.form-group input:disabled {
    background: #f7fafc;
    color: #718096;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin-top: 0.25rem;
    cursor: pointer;
}

.btn-danger {
    background: #f56565;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    background: #e53e3e;
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
    // Hide all tabs
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
            alert('Profile updated successfully');
            await loadUserProfile();
        }
    } catch (error) {
        alert('Error updating profile');
    }
}

async function changePassword() {
    const current = document.getElementById('currentPassword').value;
    const newPass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    
    if (!current || !newPass || !confirm) {
        alert('Please fill in all password fields');
        return;
    }
    
    if (newPass !== confirm) {
        alert('Passwords do not match');
        return;
    }
    
    if (newPass.length < 8) {
        alert('Password must be at least 8 characters');
        return;
    }
    
    try {
        const response = await apiClient.changePassword({
            current_password: current,
            new_password: newPass
        });
        
        if (response.ok) {
            alert('Password changed successfully');
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        } else {
            alert(response.error || 'Error changing password');
        }
    } catch (error) {
        alert('Error changing password: ' + error.message);
    }
}

function savePreferences() {
    // TODO: Save preferences to API
    alert('Preferences saved successfully');
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
    if (!confirm('Are you sure you want to logout from all devices?')) {
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

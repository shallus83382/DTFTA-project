@extends('layouts.app')

@section('title', 'DTFTA CRM - Settings')
@section('page-title', 'Settings')

@section('content')
<div class="settings-page">
    <div class="settings-shell">
        <div class="settings-shell-header">
            <h2>System Settings</h2>
            <p>Manage application-level notification controls.</p>
        </div>

        <div class="settings-tabs">
            <button class="settings-tab active">System</button>
        </div>

        <div id="systemTab" class="settings-content">
            <div class="settings-panel">
                <h3>System Settings</h3>
                <p class="panel-subtitle">Control app-level notifications.</p>

                <div class="settings-option-list">
                    <label class="checkbox-label">
                        <input type="checkbox" id="emailNotificationsEnabled">
                        <span>
                            Enable Email Notifications
                            <small>Send system notifications to a configured email address.</small>
                        </span>
                    </label>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Notification Email Address</label>
                        <input type="email" id="notificationEmail" placeholder="alerts@yourdomain.com">
                    </div>

                </div>

                <div style="display:flex; gap:10px; flex-wrap: wrap;">
                    <button class="btn-primary" onclick="saveSystemSettings()">Save System Settings</button>
                    <button id="sendTestEmailBtn" class="btn-primary" style="background: #0f766e; display:none;" onclick="sendTestNotificationEmail()">Send Test Email</button>
                </div>
            </div>
        </div>

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

.settings-option-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
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

@media (max-width: 900px) {
    .settings-tabs {
        flex-wrap: wrap;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async function() {
    await loadSystemSettings();
    const emailToggle = document.getElementById('emailNotificationsEnabled');
    if (emailToggle) {
        emailToggle.addEventListener('change', toggleTestEmailButtonVisibility);
    }
});

async function loadSystemSettings() {
    const token = localStorage.getItem('auth_token');
    if (!token) return;

    try {
        const response = await fetch('/api/v1/settings', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Failed to load settings');
        }

        const data = payload.data || {};
        document.getElementById('emailNotificationsEnabled').checked = Boolean(data.email_notifications_enabled);
        document.getElementById('notificationEmail').value = data.notification_email || '';
        toggleTestEmailButtonVisibility();
    } catch (error) {
        window.crmAlert('Unable to load system settings', 'error');
    }
}

function toggleTestEmailButtonVisibility() {
    const enabled = document.getElementById('emailNotificationsEnabled')?.checked;
    const btn = document.getElementById('sendTestEmailBtn');
    if (!btn) return;
    btn.style.display = enabled ? 'inline-flex' : 'none';
}

async function saveSystemSettings() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.crmAlert('You are not authenticated.', 'error');
        return;
    }

    const payload = {
        email_notifications_enabled: document.getElementById('emailNotificationsEnabled').checked,
        notification_email: document.getElementById('notificationEmail').value.trim()
    };

    try {
        const response = await fetch('/api/v1/settings', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to save system settings');
        }

        window.crmAlert(data.message || 'Settings updated successfully', 'success');
        await loadSystemSettings();
    } catch (error) {
        window.crmAlert(error.message || 'Error saving system settings', 'error');
    }
}

async function sendTestNotificationEmail() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.crmAlert('You are not authenticated.', 'error');
        return;
    }

    try {
        const response = await fetch('/api/v1/settings/test-email', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to send test email');
        }

        window.crmAlert(data.message || 'Test email sent successfully.', 'success');
    } catch (error) {
        window.crmAlert(error.message || 'Error sending test email', 'error');
    }
}
</script>
@endpush

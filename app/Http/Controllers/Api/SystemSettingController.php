<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SystemSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'email_notifications_enabled' => SystemSetting::getBoolean('email_notifications_enabled', true),
                'notification_email' => (string) SystemSetting::getValue('notification_email', ''),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_notifications_enabled' => ['required', 'boolean'],
            'notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        $emailEnabled = (bool) $validated['email_notifications_enabled'];
        $notificationEmail = trim((string) ($validated['notification_email'] ?? ''));

        if ($emailEnabled && $notificationEmail === '') {
            return response()->json([
                'success' => false,
                'message' => 'Notification email is required when email notifications are enabled.',
            ], 422);
        }

        SystemSetting::setValue('email_notifications_enabled', $emailEnabled ? '1' : '0');
        SystemSetting::setValue('notification_email', $notificationEmail);

        AdminActivityLog::logActivity(
            auth()->id(),
            'Updated system settings',
            'SystemSetting',
            null,
            [
                'email_notifications_enabled' => $emailEnabled,
                'notification_email' => $notificationEmail,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => [
                'email_notifications_enabled' => $emailEnabled,
                'notification_email' => $notificationEmail,
            ],
        ]);
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $recipient = trim((string) SystemSetting::getValue('notification_email', ''));
        $isEnabled = SystemSetting::getBoolean('email_notifications_enabled', false);

        if (!$isEnabled) {
            return response()->json([
                'success' => false,
                'message' => 'Email notifications are disabled.',
            ], 422);
        }

        if ($recipient === '') {
            return response()->json([
                'success' => false,
                'message' => 'Notification email is not configured.',
            ], 422);
        }

        $body = implode("\n", [
            'This is a test notification email from DTFTA CRM.',
            '',
            'If you received this email, your notification email settings are working.',
            'Sent at: ' . now()->toDateTimeString(),
            'Triggered by user ID: ' . (string) (auth()->id() ?? 'system'),
        ]);

        try {
            Mail::raw($body, function ($mail) use ($recipient) {
                $mail->to($recipient)->subject('DTFTA CRM Test Notification Email');
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send test notification email', [
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send test email. Check your mail configuration.',
            ], 500);
        }

        AdminActivityLog::logActivity(
            auth()->id(),
            'Sent test notification email',
            'SystemSetting',
            null,
            ['recipient' => $recipient]
        );

        return response()->json([
            'success' => true,
            'message' => 'Test email sent successfully to ' . $recipient . '.',
        ]);
    }
}


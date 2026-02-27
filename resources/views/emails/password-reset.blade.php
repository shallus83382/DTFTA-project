<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: 600; margin: 16px 0; }
        .muted { color: #666; font-size: 14px; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Your Password</h2>
        <p>You requested a password reset for your DTFTA CRM account.</p>
        <p>Click the button below to set a new password:</p>
        <p>
            <a href="{{ $link }}" class="button">Reset My Password</a>
        </p>
        <p class="muted">Or copy and paste this link into your browser:</p>
        <p class="muted" style="word-break: break-all;">{{ $link }}</p>
        <p class="muted">This link will expire in 24 hours. If you did not request this, you can ignore this email.</p>
        <div class="footer">
            <p>DTFTA CRM – Shopify Fulfillment Automation</p>
        </div>
    </div>
</body>
</html>

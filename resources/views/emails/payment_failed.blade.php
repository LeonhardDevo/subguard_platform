<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; padding: 30px; color: #333;">
    <h2 style="color: #dc3545;">Payment Failed</h2>
    <p>Hello {{ $customer_name }},</p>
    <p>We were unable to process your payment of <strong>{{ $currency }} {{ number_format($amount, 2) }}</strong>.</p>
    <p>We will automatically retry the payment. Please ensure your payment method is up to date.</p>
    <p>If the payment continues to fail, your subscription may be canceled.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
    <p style="color: #888; font-size: 12px;">SubGuard — Subscription Management Platform</p>
</body>
</html>

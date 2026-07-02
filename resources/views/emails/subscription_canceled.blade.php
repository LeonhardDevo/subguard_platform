<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; padding: 30px; color: #333;">
    <h2 style="color: #1a1a2e;">Subscription Canceled</h2>
    <p>Hello {{ $customer_name }},</p>
    <p>Your subscription to <strong>{{ $plan_name }}</strong> has been canceled.</p>
    <p>Canceled on: <strong>{{ $canceled_at }}</strong></p>
    @if(!empty($reason))
    <p>Reason: {{ $reason }}</p>
    @endif
    <p>We're sorry to see you go. You can resubscribe at any time.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
    <p style="color: #888; font-size: 12px;">SubGuard — Subscription Management Platform</p>
</body>
</html>

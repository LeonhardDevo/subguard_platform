<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; padding: 30px; color: #333;">
    <h2 style="color: #155724;">Payment Received</h2>
    <p>Hello {{ $customer_name }},</p>
    <p>We have received your payment of <strong>{{ $currency }} {{ number_format($amount, 2) }}</strong>.</p>
    <p>Invoice: <strong>{{ $invoice_number }}</strong></p>
    <p>Thank you for your continued trust.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
    <p style="color: #888; font-size: 12px;">SubGuard — Subscription Management Platform</p>
</body>
</html>

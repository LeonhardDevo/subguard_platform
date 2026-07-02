<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #333; padding: 40px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 3px solid #1a1a2e; padding-bottom: 20px; }
        .company-name { font-size: 28px; font-weight: 700; color: #1a1a2e; }
        .invoice-title { font-size: 32px; font-weight: 300; color: #666; text-align: right; }
        .invoice-meta { text-align: right; margin-top: 8px; color: #888; }
        .invoice-meta strong { color: #333; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px; font-weight: 600; }
        .customer-info { background: #f8f9fa; padding: 15px; border-radius: 4px; }
        .customer-info p { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        thead th { background: #1a1a2e; color: #fff; padding: 12px 15px; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        tbody td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .totals { margin-left: auto; width: 300px; }
        .totals table { margin-bottom: 0; }
        .totals td { border: none; padding: 8px 15px; }
        .totals .total-row { font-size: 18px; font-weight: 700; border-top: 2px solid #1a1a2e; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-sent { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .footer { margin-top: 60px; text-align: center; color: #aaa; font-size: 12px; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="company-name">SubGuard</div>
            <p style="color: #888; margin-top: 4px;">Subscription Management Platform</p>
        </div>
        <div>
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-meta">
                <p><strong>{{ $invoice->number }}</strong></p>
                <p>Date: {{ $invoice->created_at->format('M d, Y') }}</p>
                <p>Due: {{ $invoice->due_date->format('M d, Y') }}</p>
                <p>
                    <span class="status status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                </p>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Bill To</div>
        <div class="customer-info">
            <p><strong>{{ $customer->name }}</strong></p>
            <p>{{ $customer->email }}</p>
            @if($customer->phone)
            <p>{{ $customer->phone }}</p>
            @endif
        </div>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Period</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $plan->name }}</strong>
                        <br><small style="color: #888;">{{ ucfirst($plan->interval) }} subscription</small>
                    </td>
                    <td>{{ $invoice->created_at->format('M d') }} - {{ $invoice->created_at->addMonth()->format('M d, Y') }}</td>
                    <td style="text-align: right;">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td style="text-align: right;">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount, 2) }}</td>
            </tr>
            <tr>
                <td>Tax</td>
                <td style="text-align: right;">{{ strtoupper($invoice->currency) }} 0.00</td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td style="text-align: right;">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($invoice->paid_at)
    <p style="color: #155724; font-weight: 600; margin-top: 20px;">
        ✓ Paid on {{ $invoice->paid_at->format('M d, Y \a\t H:i') }}
    </p>
    @endif

    @if($invoice->notes)
    <div class="section" style="margin-top: 30px;">
        <div class="section-title">Notes</div>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Thank you for your business.</p>
        <p>SubGuard — Subscription Management Platform</p>
    </div>
</body>
</html>

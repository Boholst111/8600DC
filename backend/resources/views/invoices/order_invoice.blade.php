<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - 8600DC SHOP</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 14px; color: #333; }
        .invoice-header { border-bottom: 2px solid #ef4444; padding-bottom: 20px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #000; }
        .logo span { color: #ef4444; }
        .invoice-info { display: table; width: 100%; margin-bottom: 30px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th { background: #f4f4f5; text-align: left; padding: 10px; border-bottom: 1px solid #e4e4e7; }
        .table td { padding: 10px; border-bottom: 1px solid #e4e4e7; }
        .totals { width: 100%; display: table; }
        .totals-col { display: table-cell; width: 60%; }
        .totals-val { display: table-cell; width: 40%; text-align: right; }
        .footer { margin-top: 50px; text-align: center; color: #71717a; font-size: 12px; }
    </style>
</head>
<body>
    <div className="invoice-header">
        <div className="logo">8600DC <span>SHOP</span></div>
        <p>Premium Diecast Collectors' Hub</p>
    </div>

    <div className="invoice-info">
        <div className="info-col">
            <strong>Billed To:</strong><br>
            {{ $order->user->name }}<br>
            {{ $order->shipping_address }}<br>
            Email: {{ $order->user->email }}
        </div>
        <div className="info-col" style="text-align: right;">
            <strong>Invoice Detail:</strong><br>
            Invoice #: INV-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}<br>
            Date: {{ $order->created_at->format('M d, Y') }}<br>
            Payment: {{ strtoupper($order->payment_method) }}
        </div>
    </div>

    <table className="table">
        <thead>
            <tr>
                <th>Item Description</th>
                <th>Qty</th>
                <th style="text-align: right;">Price</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td style="text-align: right;">₱{{ number_format($item->price, 2) }}</td>
                <td style="text-align: right;">₱{{ number_format($item->price * $item->quantity, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div className="totals">
        <div className="totals-col"></div>
        <div className="totals-val">
            <p><strong>Total Amount: ₱{{ number_format($order->total_amount, 2) }}</strong></p>
            <p style="color: #ef4444;">Status: {{ strtoupper($order->status) }}</p>
        </div>
    </div>

    <div className="footer">
        Thank you for your purchase from 8600DC SHOP!<br>
        For inquiries, contact support@8600dc.com
    </div>
</body>
</html>

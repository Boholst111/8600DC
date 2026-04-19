<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; }
        .invoice-container { padding: 40px; max-width: 800px; margin: 0 auto; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 3px solid #e63946; padding-bottom: 20px; }
        .company-name { font-size: 28px; font-weight: bold; color: #e63946; letter-spacing: -1px; }
        .company-tagline { font-size: 10px; color: #666; margin-top: 2px; }
        .invoice-badge { background: #e63946; color: white; padding: 6px 16px; border-radius: 4px; font-size: 16px; font-weight: bold; }
        .invoice-number { font-size: 11px; color: #666; margin-top: 4px; text-align: right; }

        /* Bill To */
        .billing-grid { display: flex; gap: 40px; margin-bottom: 30px; }
        .billing-block h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 8px; }
        .billing-block p { font-size: 12px; line-height: 1.6; }
        .billing-block strong { color: #1a1a2e; }

        /* Status Badge */
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d1e7dd; color: #0a3622; }

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table th { background: #1a1a2e; color: white; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .items-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; }
        .items-table tr:nth-child(even) td { background: #fafafa; }
        .items-table tfoot td { border-top: 2px solid #e63946; }

        /* Totals */
        .totals { display: flex; justify-content: flex-end; }
        .totals-table { width: 260px; }
        .totals-table td { padding: 6px 0; }
        .totals-table td:last-child { text-align: right; font-weight: bold; }
        .totals-table tr:last-child td { font-size: 15px; color: #e63946; border-top: 2px solid #e63946; padding-top: 8px; }

        /* Footer */
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #f0f0f0; padding-top: 20px; }
    </style>
</head>
<body>
<div class="invoice-container">
    <!-- Header -->
    <div class="header">
        <div>
            <div class="company-name">8600DC SHOP</div>
            <div class="company-tagline">Premium Diecast Marketplace · Philippines</div>
        </div>
        <div style="text-align: right;">
            <div class="invoice-badge">INVOICE</div>
            <div class="invoice-number">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div class="invoice-number">{{ $order->created_at->format('F d, Y') }}</div>
        </div>
    </div>

    <!-- Billing Info -->
    <div class="billing-grid">
        <div class="billing-block">
            <h3>Billed To</h3>
            <p><strong>{{ $order->user->name ?? 'N/A' }}</strong></p>
            <p>{{ $order->user->email ?? '' }}</p>
            <p>{{ $order->shipping_address }}</p>
        </div>
        <div class="billing-block">
            <h3>Delivery Info</h3>
            <p><strong>Courier:</strong> {{ $order->courier }}</p>
            <p><strong>Order Status:</strong>
                <span class="status-badge {{ $order->status === 'Delivered' ? 'status-completed' : 'status-pending' }}">
                    {{ $order->status }}
                </span>
            </p>
            @if($order->payment)
            <p><strong>Payment:</strong> {{ $order->payment->payment_method }}</p>
            <p><strong>Txn:</strong> {{ $order->payment->transaction_id }}</p>
            @endif
        </div>
    </div>

    <!-- Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Brand / Scale</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Unit Price</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    {{ $item->product->name ?? 'Unknown Product' }}
                    @if($item->product && $item->product->is_preorder)
                        <br><small style="color:#6c757d;">(Pre-order – Downpayment)</small>
                    @endif
                </td>
                <td>{{ $item->product->brand ?? '—' }} / {{ $item->product->scale ?? '—' }}</td>
                <td style="text-align: center;">{{ $item->quantity }}</td>
                <td style="text-align: right;">₱{{ number_format($item->price, 2) }}</td>
                <td style="text-align: right;">₱{{ number_format($item->price * $item->quantity, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        <table class="totals-table">
            <tr>
                <td>Subtotal</td>
                <td>₱{{ number_format($order->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Shipping ({{ $order->courier }})</td>
                <td>₱{{ number_format($order->shipping_fee, 2) }}</td>
            </tr>
            <tr>
                <td>TOTAL DUE</td>
                <td>₱{{ number_format($order->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Thank you for shopping at 8600DC SHOP! All Philippine diecast collectors welcome.</p>
        <p style="margin-top:4px;">For questions, contact support@8600dc.shop</p>
    </div>
</div>
</body>
</html>

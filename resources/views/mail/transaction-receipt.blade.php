<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f1efdf;font-family:Arial,sans-serif;color:#153a2e;">
    <main style="max-width:560px;margin:0 auto;padding:28px;background:#fffef9;border:1px solid #ddd5ad;border-radius:14px;">
        <header style="text-align:center;margin-bottom:24px;">
            <h1 style="margin:0 0 6px;font-size:22px;">{{ $shopName }}</h1>
            <p style="margin:0;color:#66776e;font-size:13px;">{{ $shopAddress }}</p>
            <h2 style="margin:22px 0 0;font-size:18px;">Transaction receipt</h2>
        </header>

        <p style="margin:0 0 14px;">Hello {{ $customerName }}, here is your receipt.</p>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <tr><td style="padding:5px 0;color:#66776e;">Order</td><td style="padding:5px 0;text-align:right;">#{{ $transaction->id }}</td></tr>
            <tr><td style="padding:5px 0;color:#66776e;">Date</td><td style="padding:5px 0;text-align:right;">{{ $transaction->created_at?->format('M j, Y, g:i A') }}</td></tr>
            <tr><td style="padding:5px 0;color:#66776e;">Cashier</td><td style="padding:5px 0;text-align:right;">{{ $cashierName }}</td></tr>
        </table>

        <table style="width:100%;margin-top:18px;border-collapse:collapse;font-size:14px;">
            @foreach ($items as $item)
                <tr>
                    <td style="padding:6px 0;">{{ $item['name'] }}@if ($item['quantity']) × {{ $item['quantity'] }}@endif</td>
                    <td style="padding:6px 0;text-align:right;white-space:nowrap;">₱{{ number_format($item['amount'], 2) }}</td>
                </tr>
            @endforeach
            @if ($hasService && $transaction->load_weight_kg)
                <tr><td colspan="2" style="padding:0 0 8px;color:#66776e;font-size:12px;">Load: {{ number_format((float) $transaction->load_weight_kg, 2) }} kg · Garments: {{ $transaction->garmentTypes->sum('pivot.quantity') }} pcs</td></tr>
            @endif
            <tr>
                <td style="padding:12px 0 6px;border-top:1px dashed #bdb58c;font-size:17px;font-weight:bold;">Total</td>
                <td style="padding:12px 0 6px;border-top:1px dashed #bdb58c;text-align:right;font-size:17px;font-weight:bold;">₱{{ number_format((float) $transaction->total_amount, 2) }}</td>
            </tr>
            <tr><td style="padding:4px 0;color:#66776e;">Cash</td><td style="padding:4px 0;text-align:right;">₱{{ number_format((float) $transaction->cash_tendered, 2) }}</td></tr>
            <tr><td style="padding:4px 0;color:#66776e;">Change</td><td style="padding:4px 0;text-align:right;">₱{{ number_format((float) $transaction->change_given, 2) }}</td></tr>
        </table>

        <p style="margin:24px 0 0;text-align:center;color:#66776e;font-size:13px;">Thank you for choosing {{ $shopName }}!</p>
    </main>
</body>
</html>

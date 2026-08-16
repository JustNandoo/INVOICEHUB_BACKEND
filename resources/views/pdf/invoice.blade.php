<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #10172f; font-size: 12px; }
        .header { border-bottom: 2px solid #1026c7; padding-bottom: 22px; margin-bottom: 28px; }
        .brand { color: #1026c7; font-size: 24px; font-weight: bold; }
        .title { float: right; text-align: right; font-size: 22px; font-weight: bold; }
        .muted { color: #697087; }
        .columns { width: 100%; margin-bottom: 30px; }
        .columns td { width: 50%; vertical-align: top; line-height: 1.6; }
        table.items { width: 100%; border-collapse: collapse; }
        .items th { background: #f1f2ff; text-transform: uppercase; color: #555c73; font-size: 10px; padding: 11px 9px; text-align: left; }
        .items td { padding: 13px 9px; border-bottom: 1px solid #e5e7f1; }
        .number { text-align: right; }
        .totals { margin-top: 26px; margin-left: 55%; width: 45%; border-collapse: collapse; }
        .totals td { padding: 7px 0; }
        .total { border-top: 2px solid #1026c7; color: #1026c7; font-size: 16px; font-weight: bold; }
        .status { display: inline-block; padding: 5px 9px; border-radius: 10px; background: #e6f9ed; color: #159447; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; color: #8b90a3; font-size: 9px; }
    </style>
</head>
<body>
<div class="header">
    <span class="brand">InvoiceHub</span>
    <div class="title">INVOICE<br><span class="muted" style="font-size:12px">#{{ $invoice->number }}</span></div>
    <div style="clear:both"></div>
</div>

<table class="columns">
    <tr>
        <td><strong>{{ $invoice->issuer_name }}</strong><br><span class="muted">{{ $invoice->issuer_email }}<br>{!! nl2br(e($invoice->issuer_address ?? '')) !!}</span></td>
        <td style="text-align:right"><span class="muted">TAGIHAN UNTUK</span><br><strong>{{ $invoice->customer_name }}</strong><br><span class="muted">{{ $invoice->customer_email }}<br>{!! nl2br(e($invoice->customer_address ?? '')) !!}</span></td>
    </tr>
</table>

<table class="columns">
    <tr><td><span class="muted">Tanggal invoice</span><br><strong>{{ $invoice->issue_date->translatedFormat('d M Y') }}</strong></td><td style="text-align:right"><span class="muted">Jatuh tempo</span><br><strong>{{ $invoice->due_date->translatedFormat('d M Y') }}</strong></td></tr>
</table>

<table class="items">
    <thead><tr><th>Deskripsi</th><th class="number">Qty</th><th class="number">Harga</th><th class="number">Total</th></tr></thead>
    <tbody>
    @foreach ($invoice->items as $item)
        <tr><td>{{ $item->description }}</td><td class="number">{{ $item->quantity }}</td><td class="number">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td><td class="number"><strong>Rp {{ number_format($item->line_total, 0, ',', '.') }}</strong></td></tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="number">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td></tr>
    @if ($invoice->tax_amount > 0)<tr><td>Pajak ({{ $invoice->tax_rate_basis_points / 100 }}%)</td><td class="number">Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</td></tr>@endif
    @if ($invoice->discount_amount > 0)<tr><td>Diskon</td><td class="number">- Rp {{ number_format($invoice->discount_amount, 0, ',', '.') }}</td></tr>@endif
    <tr class="total"><td>Total</td><td class="number">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td></tr>
</table>

@if ($invoice->notes)<div style="margin-top:30px"><strong>Catatan</strong><p class="muted">{{ $invoice->notes }}</p></div>@endif
<div style="margin-top:28px"><span class="status">{{ strtoupper($invoice->effectiveStatus()) }}</span></div>
<div class="footer">Dokumen resmi {{ $invoice->number }} · Dibuat oleh InvoiceHub</div>
</body>
</html>

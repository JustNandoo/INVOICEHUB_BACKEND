<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111831; font-size: 12px; }
        .header { background: #071033; color: white; padding: 26px; }
        .brand { color: #ff3da5; font-size: 22px; font-weight: bold; }
        h1 { margin: 22px 0 6px; font-size: 24px; }
        .muted { color: #71758a; }
        .grid { width: 100%; margin-top: 24px; border-collapse: collapse; }
        .grid td { width: 33%; border: 1px solid #d7d9e8; padding: 16px; vertical-align: top; }
        .value { font-size: 20px; font-weight: bold; color: #071cc3; margin-top: 8px; }
        table.detail { width: 100%; margin-top: 28px; border-collapse: collapse; }
        .detail th, .detail td { border-bottom: 1px solid #e4e5ed; padding: 10px; text-align: left; }
        .notice { background: #fff9d9; border-left: 4px solid #ead23f; padding: 12px; margin-top: 28px; }
    </style>
</head>
<body>
<div class="header"><div class="brand">INVOICEHUB</div><div>Laporan Pajak {{ sprintf('%02d', $report->month) }}/{{ $report->year }}</div></div>
<h1>Laporan Pajak Bulanan</h1>
<div class="muted">{{ $report->owner->business_name }} · Status: {{ strtoupper($report->status) }}</div>
<table class="grid"><tr>
    <td>Peredaran Bruto<div class="value">Rp {{ number_format($report->total_revenue, 0, ',', '.') }}</div></td>
    <td>Peredaran Kena Pajak<div class="value">Rp {{ number_format($report->taxable_revenue, 0, ',', '.') }}</div></td>
    <td>Estimasi PPh {{ $report->tax_rate_basis_points / 100 }}%<div class="value">Rp {{ number_format($report->estimated_tax, 0, ',', '.') }}</div></td>
</tr></table>
<table class="detail">
    <tr><th>Invoice Lunas</th><td>{{ $report->paid_invoice_count }}</td></tr>
    <tr><th>Pendapatan Pending</th><td>Rp {{ number_format($report->pending_revenue, 0, ',', '.') }}</td></tr>
    <tr><th>Dasar Aturan</th><td>{{ $report->taxRule->regulation }}</td></tr>
    <tr><th>Dihitung</th><td>{{ optional($report->calculated_at)->format('d M Y H:i') }}</td></tr>
    <tr><th>Hash Dokumen</th><td style="font-size: 9px">{{ $report->locked_hash }}</td></tr>
</table>
<div class="notice">Dokumen ini adalah laporan pendukung berdasarkan data InvoiceHub, bukan bukti penerimaan atau pelaporan resmi dari DJP.</div>
</body>
</html>

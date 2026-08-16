<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111831; font-size: 12px; }
        .header { background: #071033; color: white; padding: 26px; }
        .brand { color: #ff3da5; font-size: 22px; font-weight: bold; }
        h1 { font-size: 24px; margin-top: 26px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th { background: #f0efff; }
        th, td { border: 1px solid #d9daea; padding: 10px; text-align: right; }
        th:first-child, td:first-child { text-align: left; }
        .total { font-weight: bold; background: #fff9d9; }
        .notice { margin-top: 24px; color: #6f7286; }
    </style>
</head>
<body>
<div class="header"><div class="brand">INVOICEHUB</div><div>Ringkasan Laporan Pajak Tahunan {{ $year }}</div></div>
<h1>{{ $user->business_name }}</h1>
<table>
    <thead><tr><th>Bulan</th><th>Peredaran Bruto</th><th>Kena Pajak</th><th>Estimasi Pajak</th><th>Status</th></tr></thead>
    <tbody>
    @foreach ($reports as $report)
        <tr><td>{{ sprintf('%02d', $report->month) }}/{{ $report->year }}</td><td>Rp {{ number_format($report->total_revenue, 0, ',', '.') }}</td><td>Rp {{ number_format($report->taxable_revenue, 0, ',', '.') }}</td><td>Rp {{ number_format($report->estimated_tax, 0, ',', '.') }}</td><td>{{ strtoupper($report->status) }}</td></tr>
    @endforeach
        <tr class="total"><td>TOTAL</td><td>Rp {{ number_format($reports->sum('total_revenue'), 0, ',', '.') }}</td><td>Rp {{ number_format($reports->sum('taxable_revenue'), 0, ',', '.') }}</td><td>Rp {{ number_format($reports->sum('estimated_tax'), 0, ',', '.') }}</td><td></td></tr>
    </tbody>
</table>
<div class="notice">Dokumen ini adalah ringkasan pendukung. Pastikan data dan kelayakan aturan pajak diverifikasi sebelum pelaporan resmi.</div>
</body>
</html>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Mingguan InvoiceHub</title>
    <style>
        body { margin: 40px; color: #121936; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        header { padding: 24px; color: #fff; border-radius: 12px; background: #0b145d; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        header p { margin: 0; color: #d9dcff; }
        .business { margin: 28px 0 14px; font-size: 15px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 14px 16px; border: 1px solid #e0e2ef; }
        td:first-child { width: 58%; color: #676b80; background: #f8f8fd; }
        td:last-child { font-weight: bold; text-align: right; }
        footer { margin-top: 30px; color: #8a8da0; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <header>
        <h1>Laporan Keuangan Mingguan</h1>
        <p>{{ $report->period_start->translatedFormat('d M Y') }} – {{ $report->period_end->translatedFormat('d M Y') }}</p>
    </header>
    <div class="business">{{ $report->owner->business_name }}</div>
    <table>
        <tr><td>Dana diterima</td><td>Rp {{ number_format($report->metrics['receivedAmount'], 0, ',', '.') }}</td></tr>
        <tr><td>Jumlah pembayaran</td><td>{{ number_format($report->metrics['paymentCount'], 0, ',', '.') }}</td></tr>
        <tr><td>Invoice lunas</td><td>{{ number_format($report->metrics['paidInvoiceCount'], 0, ',', '.') }}</td></tr>
        <tr><td>Invoice diterbitkan</td><td>{{ number_format($report->metrics['issuedInvoiceCount'], 0, ',', '.') }}</td></tr>
        <tr><td>Nilai invoice diterbitkan</td><td>Rp {{ number_format($report->metrics['issuedAmount'], 0, ',', '.') }}</td></tr>
        <tr><td>Piutang berjalan</td><td>Rp {{ number_format($report->metrics['outstandingAmount'], 0, ',', '.') }}</td></tr>
        <tr><td>Invoice jatuh tempo</td><td>{{ number_format($report->metrics['overdueInvoiceCount'], 0, ',', '.') }}</td></tr>
        <tr><td>Pelanggan baru</td><td>{{ number_format($report->metrics['newCustomerCount'], 0, ',', '.') }}</td></tr>
    </table>
    <footer>Dibuat otomatis oleh InvoiceHub pada {{ $report->generated_at->translatedFormat('d M Y H:i') }}</footer>
</body>
</html>

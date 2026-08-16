<!doctype html>
<html lang="id">
<body style="margin:0;background:#f5f6ff;font-family:Arial,sans-serif;color:#10172f">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;background:#f5f6ff">
    <tr><td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:18px;overflow:hidden">
            <tr><td style="padding:24px 32px;background:#0d1249;color:#fff"><strong style="font-size:24px;color:#ff40a8">INVOICEHUB</strong></td></tr>
            <tr><td style="padding:32px">
                <h1 style="margin:0 0 16px;font-size:24px">Invoice {{ $invoice->number }}</h1>
                <p>Halo {{ $invoice->customer_name }},</p>
                <p>{{ $customMessage ?: 'Invoice terbaru Anda sudah diterbitkan. Detail lengkap tersedia pada lampiran PDF di email ini.' }}</p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="12" style="margin:24px 0;background:#f7f7ff;border-radius:12px">
                    <tr><td>Total tagihan</td><td align="right"><strong>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</strong></td></tr>
                    <tr><td>Jatuh tempo</td><td align="right">{{ $invoice->due_date->translatedFormat('d M Y') }}</td></tr>
                </table>
                <p style="color:#697087;font-size:13px">Email ini dikirim otomatis oleh {{ $invoice->issuer_name }} melalui InvoiceHub.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>

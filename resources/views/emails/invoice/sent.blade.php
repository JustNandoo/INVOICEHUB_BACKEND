@php
    $terlambat = $invoice->due_date?->isPast()
        && $invoice->status !== \App\Models\Invoice::STATUS_PAID
        && $invoice->balance_due > 0;
    $sisa = $invoice->balance_due > 0 ? $invoice->balance_due : $invoice->total_amount;
    $rupiah = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    // Disiapkan di sini, bukan sebagai @if di tengah kalimat: direktif Blade yang menempel
    // langsung pada kata sebelumnya (ini@if) tidak dikenali dan hanya menyisakan @endif.
    $kontak = $invoice->issuer_email ? ', atau hubungi kami di '.$invoice->issuer_email : '';
@endphp
{{--
    Surat penagihan.

    Kepala surat menyebut nama usaha penerbit, bukan "INVOICEHUB". Yang menerima surat ini
    adalah pelanggan si pemilik usaha; mereka tidak punya hubungan dengan platformnya, dan
    surat tagihan yang mengatasnamakan pihak ketiga mudah disangka penipuan. InvoiceHub
    hanya disebut sekali di kaki surat.
--}}
<!doctype html>
<html lang="id">
<body style="margin:0;background:#f4f5fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#16213b">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 14px;background:#f4f5fb">
    <tr><td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e6e8f2">

            <tr><td style="padding:26px 32px;background:#0e1444">
                <div style="font-size:19px;font-weight:700;color:#ffffff;letter-spacing:-.01em">{{ $invoice->issuer_name }}</div>
                @if ($invoice->issuer_address)
                    <div style="margin-top:5px;font-size:12px;line-height:1.5;color:rgba(232,233,255,.66)">{{ $invoice->issuer_address }}</div>
                @endif
            </td></tr>

            <tr><td style="padding:30px 32px 0">
                <div style="font-size:11px;font-weight:700;letter-spacing:.14em;color:{{ $terlambat ? '#c2185b' : '#6b7280' }}">
                    {{ $terlambat ? 'TAGIHAN JATUH TEMPO' : 'TAGIHAN BARU' }}
                </div>
                <h1 style="margin:10px 0 0;font-size:23px;letter-spacing:-.02em">Invoice {{ $invoice->number }}</h1>

                <p style="margin:22px 0 0;font-size:14px;line-height:1.65">Halo {{ $invoice->customer_name }},</p>
                <p style="margin:12px 0 0;font-size:14px;line-height:1.7;color:#3f4560">
                    {{ $customMessage ?: ($terlambat
                        ? 'Kami ingin mengingatkan bahwa tagihan berikut sudah melewati tanggal jatuh tempo. Bila pembayaran sudah dilakukan, mohon abaikan pesan ini.'
                        : 'Berikut kami kirimkan tagihan atas transaksi Anda. Rincian lengkapnya ada pada lampiran PDF.') }}
                </p>
            </td></tr>

            <tr><td style="padding:24px 32px 0">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e6e8f2;border-radius:12px">
                    <tr>
                        <td style="padding:14px 18px;font-size:13px;color:#6b7280;border-bottom:1px solid #f0f1f7">Nomor invoice</td>
                        <td align="right" style="padding:14px 18px;font-size:13px;font-weight:600;border-bottom:1px solid #f0f1f7">{{ $invoice->number }}</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 18px;font-size:13px;color:#6b7280;border-bottom:1px solid #f0f1f7">Tanggal terbit</td>
                        <td align="right" style="padding:14px 18px;font-size:13px;border-bottom:1px solid #f0f1f7">{{ $invoice->issue_date?->translatedFormat('d F Y') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:14px 18px;font-size:13px;color:#6b7280;border-bottom:1px solid #f0f1f7">Jatuh tempo</td>
                        <td align="right" style="padding:14px 18px;font-size:13px;font-weight:600;color:{{ $terlambat ? '#c2185b' : '#16213b' }};border-bottom:1px solid #f0f1f7">
                            {{ $invoice->due_date?->translatedFormat('d F Y') ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 18px;font-size:13px;font-weight:600">{{ $invoice->balance_due > 0 && $invoice->balance_due < $invoice->total_amount ? 'Sisa tagihan' : 'Total tagihan' }}</td>
                        <td align="right" style="padding:16px 18px;font-size:20px;font-weight:700;letter-spacing:-.02em">{{ $rupiah($sisa) }}</td>
                    </tr>
                </table>
            </td></tr>

            @if ($invoice->notes)
                <tr><td style="padding:20px 32px 0">
                    <div style="padding:14px 16px;background:#f7f8fd;border-radius:10px;font-size:12.5px;line-height:1.6;color:#3f4560">{{ $invoice->notes }}</div>
                </td></tr>
            @endif

            <tr><td style="padding:24px 32px 30px">
                <p style="margin:0;font-size:13px;line-height:1.65;color:#3f4560">
                    Rincian lengkap terlampir sebagai berkas PDF. Ada pertanyaan mengenai tagihan ini?
                    Balas saja email ini{{ $kontak }}.
                </p>
                <p style="margin:18px 0 0;font-size:13px;line-height:1.65;color:#3f4560">Terima kasih,<br><strong>{{ $invoice->issuer_name }}</strong></p>
            </td></tr>

            <tr><td style="padding:16px 32px;background:#fafbff;border-top:1px solid #eef0f7">
                <div style="font-size:11px;line-height:1.55;color:#8b90a6">
                    Dikirim oleh {{ $invoice->issuer_name }} menggunakan InvoiceHub.
                </div>
            </td></tr>

        </table>
    </td></tr>
</table>
</body>
</html>

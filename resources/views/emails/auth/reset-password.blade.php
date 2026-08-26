<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Password InvoiceHub</title>
</head>
<body style="margin:0;background:#f5f7ff;font-family:Arial,Helvetica,sans-serif;color:#111a35;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Atur ulang password akun InvoiceHub Anda.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7ff;padding:36px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 14px 38px rgba(20,31,82,.10);">
                    <tr>
                        <td style="height:6px;background:linear-gradient(90deg,#ff3fab 0%,#7b36d8 48%,#1027d6 100%);"></td>
                    </tr>
                    <tr>
                        <td style="padding:36px 42px 10px;">
                            <div style="font-size:28px;font-weight:800;letter-spacing:-1px;color:#ff3fab;">INVOICEHUB</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 42px 40px;">
                            <h1 style="margin:0 0 16px;font-size:28px;line-height:1.25;color:#0d1835;">Atur ulang password Anda</h1>
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#525a71;">Halo, {{ $name }}!</p>
                            <p style="margin:0 0 26px;font-size:16px;line-height:1.7;color:#525a71;">
                                Kami menerima permintaan untuk mengatur ulang password akun InvoiceHub Anda. Klik tombol berikut untuk membuat password baru.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:9px;background:#1027d6;">
                                        <a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 26px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Atur Password Baru</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:26px 0 8px;font-size:14px;line-height:1.6;color:#71788d;">
                                Tautan ini berlaku selama {{ $expiresInMinutes }} menit dan hanya dapat dipakai satu kali. Jika tombol tidak dapat dibuka, salin URL berikut ke browser:
                            </p>
                            <p style="margin:0;word-break:break-all;font-size:12px;line-height:1.6;color:#1027d6;">{{ $resetUrl }}</p>

                            <div style="margin-top:30px;padding-top:22px;border-top:1px solid #e8eaf3;">
                                <p style="margin:0;font-size:13px;line-height:1.6;color:#8a90a2;">
                                    Jika Anda tidak meminta perubahan password, abaikan email ini — password Anda tidak akan berubah. Jangan pernah membagikan tautan ini kepada siapa pun, termasuk yang mengaku dari InvoiceHub.
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>

                <p style="margin:20px 0 0;font-size:12px;color:#9298aa;">© {{ date('Y') }} InvoiceHub. Solusi Rekonsiliasi UMKM Indonesia.</p>
            </td>
        </tr>
    </table>
</body>
</html>

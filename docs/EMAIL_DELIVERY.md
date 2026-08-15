# Pengiriman Email InvoiceHub dengan Gmail SMTP

InvoiceHub mengirim email verifikasi melalui SMTP Gmail menggunakan Laravel Mail. Credential hanya disimpan di `.env` dan tidak boleh dimasukkan ke Git.

## 1. Siapkan akun Gmail

1. Buat atau gunakan akun Gmail khusus aplikasi, misalnya `invoicehub@gmail.com` jika alamat tersebut tersedia.
2. Aktifkan **2-Step Verification** pada akun Google.
3. Buka [Google App Passwords](https://myaccount.google.com/apppasswords).
4. Buat App Password dengan nama `InvoiceHub Laravel`.
5. Salin kode 16 karakter yang ditampilkan Google.

Gunakan App Password, bukan password utama akun Gmail.

## 2. Konfigurasi `.env`

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=invoicehub@gmail.com
MAIL_PASSWORD=app_password_16_karakter
MAIL_FROM_ADDRESS=invoicehub@gmail.com
MAIL_FROM_NAME="InvoiceHub"
```

Gunakan alamat Gmail yang sama pada `MAIL_USERNAME` dan `MAIL_FROM_ADDRESS`. Masukkan App Password tanpa spasi.

## 3. Terapkan konfigurasi

```bash
php artisan config:clear
```

Kemudian registrasikan user melalui `POST /api/v1/auth/register`. Response `data.verificationEmailSent` bernilai `true` jika Gmail menerima permintaan pengiriman dari Laravel.

## 4. Keamanan

- Jangan membagikan App Password melalui chat, screenshot, atau source control.
- Revoke dan buat App Password baru apabila pernah terekspos.
- Jangan menggunakan password login utama Gmail sebagai `MAIL_PASSWORD`.
- Link verifikasi InvoiceHub memiliki signature dan kedaluwarsa setelah 60 menit.
- Endpoint kirim ulang dilindungi rate limit.

Gmail SMTP cocok untuk development atau volume kecil. Untuk production bervolume tinggi, gunakan penyedia email transaksional dengan domain sendiri.

Dokumentasi resmi: [Google App Passwords](https://support.google.com/accounts/answer/185833) dan [Laravel Mail](https://laravel.com/docs/13.x/mail).

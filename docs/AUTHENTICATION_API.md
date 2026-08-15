# InvoiceHub Authentication API

Base URL lokal: `http://localhost:8000/api/v1`

Semua request dan response menggunakan JSON. Kirim header:

```http
Accept: application/json
Content-Type: application/json
```

## Registrasi

`POST /auth/register`

```json
{
  "fullName": "Budi Santoso",
  "email": "budi@example.com",
  "businessName": "Toko Sejahtera",
  "password": "Secure1234",
  "termsAccepted": true
}
```

Password minimal delapan karakter dan harus memiliki huruf besar, huruf kecil, serta angka. Registrasi berhasil mengirim email verifikasi, tetapi belum menerbitkan access token.

## Login

`POST /auth/login`

```json
{
  "email": "budi@example.com",
  "password": "Secure1234",
  "remember": true
}
```

Login hanya berhasil setelah email diverifikasi. Gunakan token dari `data.accessToken` untuk request terlindungi:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
```

Token standar berlaku 24 jam. Jika `remember` bernilai `true`, token berlaku 30 hari. Durasi dapat diubah melalui environment backend.

Frontend sebaiknya menyimpan token hanya di memory aplikasi. Hindari `localStorage` karena token dapat dicuri apabila terjadi XSS.

## Verifikasi email

- Link email: `GET /auth/email/verify/{id}/{hash}`
- Kirim ulang: `POST /auth/email/resend`

Payload kirim ulang:

```json
{
  "email": "budi@example.com"
}
```

Link verifikasi memiliki signature dan kedaluwarsa setelah 60 menit. Endpoint kirim ulang selalu memberikan response generik agar tidak membocorkan apakah akun tersedia.

Pengiriman email menggunakan Laravel SMTP melalui Gmail. Ikuti [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) untuk membuat App Password dan menguji pengiriman nyata. Jangan pernah memasukkan credential email ke Git.

## User aktif

`GET /auth/me`

Wajib menggunakan Bearer token dan email terverifikasi.

## Logout

`POST /auth/logout`

Wajib menggunakan Bearer token. Hanya token yang sedang digunakan yang dicabut, sehingga login di perangkat lain tetap aktif.

## Status response utama

- `200`: request berhasil
- `201`: registrasi berhasil
- `202`: permintaan email verifikasi diterima
- `401`: kredensial/token tidak valid
- `403`: email belum diverifikasi atau signature tidak valid
- `422`: validasi payload gagal
- `429`: terlalu banyak request

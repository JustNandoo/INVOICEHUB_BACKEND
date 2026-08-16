# Profile API

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Semua endpoint membutuhkan Bearer Token dan email yang sudah diverifikasi.

```http
Accept: application/json
Authorization: Bearer YOUR_ACCESS_TOKEN
```

## Lihat profil

### GET `/profile`

```json
{
  "success": true,
  "data": {
    "profile": {
      "id": 1,
      "fullName": "Bella Hadid",
      "email": "bella.hadid@bisnis.id",
      "businessName": "Dior",
      "whatsapp": "+6281234567890",
      "city": "Probolinggo",
      "businessType": "reseller",
      "photoUrl": "http://127.0.0.1:8000/storage/profile-photos/1/avatar.jpg",
      "role": "user",
      "emailVerifiedAt": "2026-08-16T09:00:00+00:00",
      "passwordChangedAt": null,
      "createdAt": "2026-08-15T09:00:00+00:00"
    }
  }
}
```

## Ubah profil

### PATCH `/profile`

Seluruh field bersifat opsional.

```json
{
  "fullName": "Bella Hadid",
  "businessName": "Dior",
  "whatsapp": "081234567890",
  "city": "Probolinggo",
  "businessType": "reseller"
}
```

Nilai `businessType`:

- `reseller`
- `retail`
- `service`
- `manufacturing`
- `other`

Nomor WhatsApp dinormalisasi ke format internasional dan harus unik antarakun.

### Mengubah email

Perubahan email wajib menyertakan kata sandi saat ini:

```json
{
  "email": "new-email@example.com",
  "currentPassword": "CurrentPassword123"
}
```

Jika berhasil:

- Email lama langsung diganti.
- `emailVerifiedAt` menjadi `null`.
- Email verifikasi baru dikirim.
- Endpoint yang membutuhkan email terverifikasi akan mengembalikan `403` sampai email baru diverifikasi.

Response menyertakan:

```json
{
  "emailChanged": true,
  "emailVerificationRequired": true,
  "verificationEmailSent": true
}
```

## Upload foto profil

### POST `/profile/photo`

Gunakan `multipart/form-data`:

| Key | Type | Ketentuan |
| --- | --- | --- |
| `photo` | File | JPG, JPEG, PNG, atau WebP; maksimal 2 MB |

Contoh Postman:

```text
Body → form-data → photo → File
```

Nama file dibuat acak dan disimpan pada public storage. Foto lama otomatis dihapus setelah foto baru berhasil disimpan.

Jalankan perintah berikut satu kali saat setup deployment:

```bash
php artisan storage:link
```

## Hapus foto profil

### DELETE `/profile/photo`

Menghapus file foto dan mengembalikan `photoUrl: null`.

## Ubah kata sandi

### PUT `/profile/password`

```json
{
  "currentPassword": "CurrentPassword123",
  "newPassword": "NewStrongPassword456",
  "newPasswordConfirmation": "NewStrongPassword456"
}
```

Kata sandi baru harus memenuhi standar password aplikasi: minimal delapan karakter, mengandung huruf besar, huruf kecil, dan angka.

Setelah berhasil:

- Password disimpan dalam bentuk hash.
- `passwordChangedAt` diperbarui.
- Token perangkat lain dicabut.
- Bearer Token yang digunakan untuk request ini tetap aktif.

Endpoint perubahan password dibatasi maksimal lima request per menit untuk setiap user.

# InvoiceHub Blog API

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Semua endpoint blog bersifat publik. Header `Authorization` atau Bearer Token tidak diperlukan.

Gunakan header berikut agar respons selalu berupa JSON:

```http
Accept: application/json
```

## Daftar artikel

```http
GET /blogs
```

Query parameter opsional:

| Parameter | Tipe | Keterangan |
| --- | --- | --- |
| `search` | string | Mencari pada judul dan ringkasan, maksimal 100 karakter. |
| `category` | string | Filter nama kategori, misalnya `Tips Keuangan`. |
| `featured` | `1` atau `0` | Filter artikel unggulan. |
| `perPage` | integer | Jumlah data per halaman, antara 1 sampai 24. Default 9. |
| `page` | integer | Nomor halaman, minimal 1. |

Contoh:

```http
GET /blogs?search=pajak&category=Panduan%20Pajak&perPage=9&page=1
```

Respons sukses:

```json
{
  "success": true,
  "data": {
    "articles": [
      {
        "id": 1,
        "slug": "panduan-lengkap-lapor-spt-pajak-umkm-2024",
        "title": "Panduan Lengkap Lapor SPT Pajak UMKM 2024",
        "excerpt": "Pahami langkah demi langkah cara melaporkan SPT tahunan...",
        "category": "Panduan Pajak",
        "coverImageUrl": "/images/blog-featured-tax.png",
        "coverImageAlt": "Ilustrasi pemilik UMKM menyiapkan laporan pajak",
        "authorName": "Tim Pajak InvoiceHub",
        "readingTimeMinutes": 8,
        "isFeatured": true,
        "publishedAt": "2024-10-12T09:00:00+00:00"
      }
    ],
    "categories": ["Panduan Pajak", "Tips Keuangan"],
    "pagination": {
      "currentPage": 1,
      "perPage": 9,
      "lastPage": 1,
      "total": 6,
      "from": 1,
      "to": 6,
      "previousPageUrl": null,
      "nextPageUrl": null
    }
  }
}
```

## Detail artikel

```http
GET /blogs/{slug}
```

Contoh:

```http
GET /blogs/panduan-lengkap-lapor-spt-pajak-umkm-2024
```

Detail mempunyai seluruh field dari list ditambah `content` terstruktur dan `updatedAt`.

```json
{
  "success": true,
  "data": {
    "article": {
      "slug": "panduan-lengkap-lapor-spt-pajak-umkm-2024",
      "title": "Panduan Lengkap Lapor SPT Pajak UMKM 2024",
      "content": [
        {
          "heading": "Dokumen yang perlu disiapkan",
          "paragraphs": ["Kumpulkan seluruh dokumen pendukung."],
          "bullets": ["NPWP dan data identitas wajib pajak."]
        }
      ]
    }
  }
}
```

Artikel draft, artikel dengan jadwal terbit di masa depan, dan slug yang tidak tersedia mengembalikan HTTP `404`.

## CRUD artikel untuk admin

Endpoint pengelolaan blog membutuhkan:

- akun dengan email yang sudah diverifikasi;
- role akun `admin`;
- Bearer Token dari endpoint login.

Tambahkan header berikut:

```http
Accept: application/json
Authorization: Bearer ACCESS_TOKEN_ADMIN
```

Jadikan user yang sudah terdaftar sebagai admin dari terminal backend:

```bash
php artisan tinker --execute="App\\Models\\User::where('email', 'EMAIL_ANDA')->update(['role' => App\\Models\\User::ROLE_ADMIN]);"
```

Login ulang setelah role diperbarui untuk mendapatkan token, kemudian gunakan endpoint berikut:

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `GET` | `/admin/blogs` | Daftar seluruh artikel, termasuk draft dan scheduled. |
| `POST` | `/admin/blogs` | Membuat artikel baru. |
| `GET` | `/admin/blogs/{id}` | Detail artikel berdasarkan ID. |
| `PATCH` atau `PUT` | `/admin/blogs/{id}` | Memperbarui artikel. |
| `DELETE` | `/admin/blogs/{id}` | Menghapus artikel dan cover yang dikelola aplikasi. |

List admin mendukung filter publik serta parameter `status` dengan nilai `published`, `draft`, atau `scheduled`.

### Membuat artikel

```http
POST /admin/blogs
Content-Type: multipart/form-data
```

Gunakan Body `form-data` di Postman:

| Key | Type | Contoh / aturan |
| --- | --- | --- |
| `title` | Text | `Panduan Mengelola Invoice` |
| `slug` | Text | Opsional. Jika kosong dibuat otomatis dari judul. |
| `excerpt` | Text | Ringkasan maksimal 1.000 karakter. |
| `category` | Text | `Tips Keuangan` |
| `authorName` | Text | `Admin InvoiceHub` |
| `readingTimeMinutes` | Text | `5` |
| `isFeatured` | Text | `1` atau `0` |
| `publishedAt` | Text | ISO-8601. Hilangkan field ini untuk menyimpan draft. |
| `coverImage` | File | JPG, PNG, atau WebP, maksimal 5 MB. |
| `coverImageAlt` | Text | Deskripsi gambar untuk aksesibilitas. |
| `content` | Text | JSON array terstruktur seperti contoh di bawah. |

Nilai `content` pada form-data:

```json
[
  {
    "heading": "Mulai dari data pelanggan",
    "paragraphs": [
      "Pastikan data pelanggan sudah lengkap sebelum membuat invoice."
    ],
    "bullets": [
      "Nama pelanggan",
      "Email",
      "Nomor WhatsApp"
    ]
  }
]
```

### Memperbarui artikel

Jika tidak mengganti cover, gunakan `PATCH` dengan Body raw JSON dan kirim hanya field yang berubah:

```json
{
  "title": "Judul Artikel Terbaru",
  "isFeatured": true,
  "publishedAt": "2026-08-15T22:00:00+07:00"
}
```

Kirim `publishedAt: null` untuk mengembalikan artikel menjadi draft.

Jika mengganti cover, gunakan `POST /admin/blogs/{id}` dengan Body `form-data`, tambahkan `_method` bernilai `PATCH`, lalu masukkan `coverImage` dan `coverImageAlt`.

### Menghapus artikel

```http
DELETE /admin/blogs/{id}
```

File cover yang diunggah melalui API ikut dihapus. File statis atau eksternal tidak akan disentuh.

## Menyiapkan data lokal

```bash
php artisan migrate
php artisan db:seed --class=BlogPostSeeder
php artisan storage:link
```

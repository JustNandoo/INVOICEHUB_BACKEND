# Customer API

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Semua endpoint memerlukan Bearer Token dan email yang sudah diverifikasi.

```http
Accept: application/json
Authorization: Bearer YOUR_ACCESS_TOKEN
```

Semua nilai uang menggunakan integer Rupiah. Data pelanggan selalu dibatasi berdasarkan pemilik token.

## Summary

### GET `/customers/summary?month=10&year=2026`

Mengembalikan data tiga kartu ringkasan halaman pelanggan.

```json
{
  "success": true,
  "data": {
    "summary": {
      "totalCustomers": 1248,
      "activeCustomers": 1102,
      "newCustomersThisMonth": 12,
      "customersWithOutstanding": 83,
      "totalOutstanding": 45200000,
      "averageMonthlyTransactionValue": 8400000,
      "periodTransactionValue": 168000000
    }
  }
}
```

`averageMonthlyTransactionValue` adalah nilai invoice pada periode dibagi jumlah pelanggan yang bertransaksi pada periode tersebut.

## List pelanggan

### GET `/customers`

Query parameter:

| Parameter | Nilai |
| --- | --- |
| `search` | Kode pelanggan, nama, email, WhatsApp, atau kota |
| `status` | `all`, `active`, `inactive` |
| `hasOutstanding` | `true` atau `false` |
| `source` | `manual`, `tokopedia`, `shopee`, `other` |
| `sortBy` | `name`, `totalTransactionValue`, `totalOutstanding`, `lastInvoiceDate`, `newest` |
| `sortDirection` | `asc`, `desc` |
| `page` | Nomor halaman |
| `perPage` | 1–100, default 5 |

Contoh:

```http
GET /customers?search=makmur&status=active&hasOutstanding=true&sortBy=totalOutstanding&sortDirection=desc&page=1&perPage=5
```

Setiap item berisi data identitas, `metrics`, dan `lastInvoice`. Nilai metrics dihitung dari invoice dan tidak disimpan ganda di tabel pelanggan.

## Autocomplete invoice

### GET `/customers/search?search=toko&limit=10`

Mengembalikan pelanggan aktif untuk field pencarian pada pembuatan invoice. `limit` menerima 1–50.

## Tambah pelanggan

### POST `/customers`

```json
{
  "name": "Toko Makmur Raya",
  "whatsapp": "081355554444",
  "email": "halo@makmurraya.id",
  "city": "Surabaya",
  "address": "Jl. Pemuda No. 7",
  "source": "tokopedia"
}
```

Backend akan:

- Membuat kode berurutan seperti `CUST-001` untuk setiap akun.
- Menormalisasi WhatsApp ke format internasional seperti `+6281355554444`.
- Menolak nomor WhatsApp duplikat pada akun yang sama.
- Mengaktifkan pelanggan secara default.

## Detail pelanggan

### GET `/customers/{id}`

Mengembalikan identitas, status, metrik invoice, total transaksi, total pembayaran, total piutang, dan invoice terakhir.

## Ubah pelanggan

### PATCH `/customers/{id}`

Seluruh field bersifat opsional.

```json
{
  "name": "Toko Makmur Utama",
  "whatsapp": "081299998888",
  "isActive": false
}
```

Pelanggan nonaktif tidak ditampilkan pada autocomplete invoice, tetapi histori datanya tetap tersedia.

## Hapus pelanggan

### DELETE `/customers/{id}`

Menggunakan soft delete sehingga snapshot dan histori invoice tetap tersimpan. Penghapusan ditolak apabila pelanggan masih mempunyai invoice dengan piutang. Gunakan `PATCH` dengan `isActive: false` untuk menonaktifkannya.

## Riwayat invoice pelanggan

### GET `/customers/{id}/invoices?status=unpaid&page=1&perPage=10`

Status opsional: `draft`, `unpaid`, `paid`, `overdue`, atau `void`. Response berisi list invoice dan pagination.

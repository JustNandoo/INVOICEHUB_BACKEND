# InvoiceHub Invoice API

Modul ini menangani daftar invoice, summary UI, draft/terbit, detail, edit, pembatalan, pengiriman, pembayaran, PDF, dan pencarian pelanggan.

## Akses

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Semua endpoint membutuhkan user terautentikasi dan email terverifikasi:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer YOUR_SANCTUM_TOKEN
```

Data selalu dibatasi berdasarkan pemilik token. ID invoice/pelanggan user lain menghasilkan `404`.

## Endpoint

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `GET` | `/invoices` | List, search, filter, sort, pagination |
| `GET` | `/invoices/summary` | Angka summary card invoice |
| `POST` | `/invoices` | Membuat draft/invoice beserta item |
| `GET` | `/invoices/{id}` | Detail, item, total, payment, activity |
| `PATCH` | `/invoices/{id}` | Mengubah invoice |
| `DELETE` | `/invoices/{id}` | Menghapus draft/membatalkan invoice |
| `POST` | `/invoices/{id}/send` | Kirim email/buat tautan WhatsApp |
| `POST` | `/invoices/{id}/payments` | Mencatat pembayaran |
| `GET` | `/invoices/{id}/pdf` | Unduh PDF resmi |
| `GET` | `/customers?search=...` | Autocomplete pelanggan |

## Status

Status database: `draft`, `unpaid`, `paid`, `void`. `overdue` merupakan status response turunan ketika invoice `unpaid` sudah melewati `dueDate`. Frontend dapat memetakannya menjadi DRAFT, BELUM BAYAR, LUNAS, DIBATALKAN, dan JATUH TEMPO.

## List dan filter

```http
GET /api/v1/invoices?search=makmur&status=unpaid&issueFrom=2026-01-01&issueTo=2026-12-31&sort=newest&perPage=15&page=1
```

- `search`: nomor, nama, atau email pelanggan.
- `status`: `draft`, `unpaid`, `overdue`, `paid`, `void`.
- `issueFrom`, `issueTo`, `dueFrom`, `dueTo`: `YYYY-MM-DD`.
- `sort`: `newest`, `oldest`, `dueSoon`, `amountHigh`, `amountLow`.
- `perPage`: 1–100; `page`: minimal 1.

## Summary

```http
GET /api/v1/invoices/summary
```

```json
{
  "success": true,
  "data": {
    "summary": {
      "totalInvoices": 164,
      "totalBilled": 24500000,
      "paidInvoices": 96,
      "unpaidInvoices": 60,
      "unpaidAmount": 8750000,
      "overdueInvoices": 8,
      "overdueAmount": 3500000,
      "draftInvoices": 4
    }
  }
}
```

## Buat invoice

```http
POST /api/v1/invoices
```

```json
{
  "customer": {
    "name": "Bapak Budi (Toko Makmur)",
    "email": "budi@example.com",
    "whatsapp": "+62 812 3456 7890",
    "address": "Jl. Melati Blok C No. 12, Bandung"
  },
  "issuerAddress": "Jl. Sudirman No. 45, Jakarta Selatan",
  "issueDate": "2026-08-16",
  "dueDate": "2026-09-16",
  "status": "draft",
  "taxRate": 11,
  "discountAmount": 50000,
  "notes": "Pembayaran melalui rekening perusahaan.",
  "items": [
    { "description": "Premium Kopi Beans 1kg", "quantity": 10, "unitPrice": 120000 },
    { "description": "Packaging Pouch 500g", "quantity": 50, "unitPrice": 2500 }
  ]
}
```

Untuk pelanggan tersimpan, ganti objek `customer` dengan `"customerId": 12`. Nomor invoice dan seluruh total dihitung backend. Client tidak dapat menentukan nomor, subtotal, pajak final, total, saldo, atau status lunas.

## Update

```http
PATCH /api/v1/invoices/12
```

```json
{
  "dueDate": "2026-10-01",
  "notes": "Jatuh tempo diperpanjang sesuai kesepakatan."
}
```

Draft dapat diubah sepenuhnya. Setelah terbit, hanya `dueDate` dan `notes` yang dapat diubah. Invoice lunas/dibatalkan tidak dapat diubah.

## Kirim invoice

Email dikirim melalui SMTP Laravel dengan lampiran PDF:

```http
POST /api/v1/invoices/12/send
```

```json
{ "channel": "email", "message": "Terima kasih atas kepercayaan Anda." }
```

WhatsApp:

```json
{ "channel": "whatsapp" }
```

`recipient` opsional; default dari snapshot pelanggan. Response WhatsApp memuat `data.delivery.actionUrl` untuk dibuka frontend. Mengirim draft mengubah status menjadi `unpaid` dan mencatat activity `sent`; pengiriman berikutnya menjadi `resent`.

## Pembayaran

```http
POST /api/v1/invoices/12/payments
```

```json
{
  "amount": 450000,
  "method": "bank_transfer",
  "reference": "BCA-2026-00192",
  "paidAt": "2026-08-16T13:30:00+07:00",
  "notes": "Transfer diterima dan diverifikasi."
}
```

Method: `bank_transfer`, `cash`, `e_wallet`, `marketplace`, `other`. `paidAt` opsional (default sekarang). Overpayment ditolak. Pembayaran sebagian mempertahankan `unpaid`; saldo nol otomatis menjadi `paid`.

## Delete, void, dan PDF

- `DELETE /invoices/{id}`: draft di-soft-delete, unpaid menjadi `void`, paid ditolak.
- `GET /invoices/{id}/pdf`: response binary `application/pdf`. Di Postman gunakan **Send and Download**.

## Customer search

```http
GET /api/v1/customers/search?search=toko&limit=10
```

Hanya pelanggan aktif milik user. Search mencakup nama, email, dan WhatsApp.

## Database dan integritas

- `customers`: profil pelanggan.
- `invoice_number_sequences`: urutan nomor per user/tahun.
- `invoices`: header, snapshot penerbit/pelanggan, status, tanggal, total.
- `invoice_items`: barang/jasa.
- `invoice_payments`: ledger pembayaran.
- `invoice_activities`: audit trail.

Nominal disimpan sebagai integer Rupiah. Create/update/payment memakai transaction dan row locking agar tetap konsisten pada request bersamaan.

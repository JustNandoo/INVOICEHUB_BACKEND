# InvoiceHub Reconciliation API

API pembayaran/rekonsiliasi berbasis aturan deterministik (tanpa AI). Semua endpoint memerlukan bearer token Sanctum dan email terverifikasi.

```http
Accept: application/json
Authorization: Bearer YOUR_TOKEN
```

Base URL lokal: `http://127.0.0.1:8000/api/v1`

## Endpoint

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `GET` | `/reconciliation/summary` | Data hero, legend, dan progress halaman |
| `GET` | `/bank-accounts` | Card rekening, saldo, last sync |
| `POST` | `/bank-accounts/{id}/sync` | Menjalankan sinkronisasi rekening |
| `GET` | `/bank-transactions` | List/filter/search mutasi |
| `GET` | `/bank-transactions/{id}` | Detail mutasi |
| `GET` | `/bank-transactions/{id}/candidates` | Kandidat invoice berbasis aturan |
| `POST` | `/bank-transactions/{id}/reject-candidate` | Menolak kandidat |
| `POST` | `/bank-transactions/{id}/ignore` | Mengabaikan mutasi non-invoice |
| `POST` | `/bank-transactions/imports` | Upload CSV/XLS/XLSX maksimal 10 MB |
| `GET` | `/bank-transactions/imports/{id}` | Hasil dan error import |
| `GET` | `/reconciliations` | Log rekonsiliasi |
| `POST` | `/reconciliations` | Konfirmasi mutasi dan invoice |
| `POST` | `/reconciliations/{id}/reverse` | Batalkan pencocokan dan kembalikan saldo |

## Summary halaman

```http
GET /api/v1/reconciliation/summary
```

```json
{
  "success": true,
  "data": {
    "summary": {
      "totalTransactions": 154,
      "matchedTransactions": 134,
      "unmatchedTransactions": 17,
      "needsConfirmation": 3,
      "ignoredTransactions": 2,
      "matchPercentage": 87,
      "totalIncomingAmount": 180000000
    }
  }
}
```

## Rekening bank

```http
GET /api/v1/bank-accounts
POST /api/v1/bank-accounts/1/sync
```

Nomor rekening lengkap disimpan terenkripsi. API hanya mengirim format `•••• 4412`. Endpoint sync saat ini memperbarui status/last sync; adapter open-banking dapat ditambahkan pada service yang sama tanpa mengubah kontrak frontend. Mutasi juga dapat dimasukkan melalui import Excel/CSV.

## List mutasi

```http
GET /api/v1/bank-transactions?status=unmatched&bankAccountId=1&search=sri&dateFrom=2026-08-01&dateTo=2026-08-31&perPage=20&page=1
```

Filter:

- `status`: `unmatched`, `needs_confirmation`, `matched`, `ignored`.
- `type`: `credit` atau `debit`.
- `bankAccountId`, `search`, `dateFrom`, `dateTo`, `perPage`, `page`.

## Kandidat invoice

```http
GET /api/v1/bank-transactions/81/candidates
```

Aturan memberikan skor dari:

- Nomor invoice pada deskripsi/referensi.
- Nominal sama atau selisih biaya admin maksimal.
- Nama pengirim sesuai nama pelanggan.
- Waktu transfer berada pada periode invoice.

Kandidat berisi `score`, `reasons`, `suggestedAppliedAmount`, `differenceAmount`, dan `differenceType`. Ini perhitungan rule-based, bukan AI.

Saat import menemukan nomor invoice pada deskripsi/referensi dan nominalnya sama persis dengan sisa tagihan, sistem langsung merekonsiliasi dengan `matchedBy: exact_rule`. Kondisi selain exact match tetap masuk kandidat dan membutuhkan konfirmasi pengguna.

## Konfirmasi rekonsiliasi

```http
POST /api/v1/reconciliations
Content-Type: application/json
```

Exact match:

```json
{
  "bankTransactionId": 81,
  "invoiceId": 42,
  "appliedAmount": 450000,
  "notes": "Nominal dan nomor invoice sesuai"
}
```

Dengan biaya admin:

```json
{
  "bankTransactionId": 81,
  "invoiceId": 42,
  "appliedAmount": 450000,
  "adjustments": [
    {
      "type": "bank_fee",
      "amount": 1500,
      "description": "Biaya admin BCA"
    }
  ]
}
```

Rumus validasi:

```text
appliedAmount = bankTransaction.amount + total adjustments
```

Dalam satu database transaction, API membuat reconciliation dan invoice payment, mengubah saldo/status invoice, mengubah mutasi menjadi `matched`, serta menulis activity log. Mutasi yang sama tidak dapat dicocokkan dua kali.

## Reverse

```http
POST /api/v1/reconciliations/15/reverse
```

```json
{ "reason": "Invoice yang dipilih salah" }
```

Payment ditandai void (tidak dihapus), saldo invoice dikembalikan, invoice menjadi `unpaid`, mutasi menjadi `unmatched`, dan audit log tetap tersedia.

## Reject dan ignore

```http
POST /api/v1/bank-transactions/81/reject-candidate
```

```json
{ "invoiceId": 42, "reason": "Bukan pembayaran pelanggan ini" }
```

```http
POST /api/v1/bank-transactions/81/ignore
```

```json
{ "reason": "Setoran modal pemilik" }
```

## Import Excel/CSV

Gunakan `multipart/form-data`:

```text
bankAccountId: 1
file: mutasi-agustus.xlsx
```

Header yang dikenali:

- `Tanggal` / `Date`
- `Nominal` / `Amount`
- `Pengirim` / `Sender`
- `Keterangan` / `Description`
- `Referensi` / `Reference`
- `ID Transaksi` / `Transaction ID`
- `Tipe` / `Type`

Kolom wajib: tanggal dan nominal. Template tersedia di `docs/reconciliation-import-template.csv`. File disimpan pada private storage. Fingerprint per rekening mencegah row yang sama diimport dua kali.

## Database

- `bank_accounts`: rekening terenkripsi dan status koneksi.
- `bank_transaction_imports`: riwayat serta error import.
- `bank_transactions`: ledger mutasi dan fingerprint idempotency.
- `reconciliation_suggestions`: kandidat serta alasan rule-based.
- `reconciliations`: pencocokan terkonfirmasi/reversed.
- `reconciliation_adjustments`: biaya bank/marketplace/pembulatan.
- `invoice_payments`: payment hasil reconciliation, termasuk reversal marker.

Semua query scoped berdasarkan user. Operasi confirm/reverse memakai transaction dan row lock untuk mencegah double payment serta race condition.

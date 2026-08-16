# Tax Report API

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Semua endpoint membutuhkan akun terautentikasi dan email terverifikasi.

```http
Accept: application/json
Authorization: Bearer YOUR_ACCESS_TOKEN
```

Nilai uang dikirim sebagai integer Rupiah. Contoh `243750` berarti Rp243.750. JSON memakai key English `camelCase`, sama seperti modul API InvoiceHub lainnya.

## Tax profile

### GET `/tax-profile`

Mengambil konfigurasi wajib pajak. `isConfigured` bernilai `false` sebelum profil dibuat.

### PUT `/tax-profile`

```json
{
  "taxpayerType": "entity",
  "taxpayerName": "PT Sahabat UMKM",
  "businessName": "InvoiceHub Store",
  "npwp": "1234567890123456",
  "taxScheme": "final_umkm",
  "accountingMethod": "cash_basis",
  "effectiveFrom": "2026-01-01"
}
```

`taxpayerType` menerima `individual` atau `entity`. NPWP dienkripsi di database dan API hanya mengembalikan bentuk tersamarkan.

## Dashboard laporan

### GET `/tax-reports/overview?month=10&year=2026`

Mengembalikan profil pajak, ringkasan bulan terpilih, ringkasan berjalan dalam satu tahun, serta perbandingan dengan bulan sebelumnya.

### GET `/tax-reports/monthly?year=2026&months=6`

Data chart dan tabel untuk 1–12 bulan.

### GET `/tax-reports/2026/10`

Detail laporan periode. Jika laporan belum dikunci, angka berupa preview terbaru dari ledger pajak.

Contoh struktur laporan:

```json
{
  "success": true,
  "data": {
    "report": {
      "id": 12,
      "period": { "year": 2026, "month": 10, "monthName": "Oktober" },
      "monthlySummary": {
        "totalRevenue": 48750000,
        "taxableRevenue": 48750000,
        "pendingRevenue": 0,
        "paidInvoiceCount": 142,
        "taxRate": 0.5,
        "estimatedTax": 243750
      },
      "reportStatus": "draft",
      "totalFindings": 0,
      "isLocked": false,
      "canDownload": false
    }
  }
}
```

## Calculation workflow

### POST `/tax-reports/2026/10/recalculate`

Menyinkronkan pembayaran invoice aktif ke tax ledger, menyimpan snapshot sumber data, menghitung ulang nilai laporan, dan menjalankan pemeriksaan transaksi bank deterministik.

Pembayaran yang dibatalkan tidak dihitung. Untuk `individual`, ambang tidak kena pajak tahunan pada versi aturan aktif diterapkan secara year-to-date. Pajak dihitung server-side:

```text
estimatedTax = taxableRevenue × taxRate
```

### POST `/tax-reports/2026/10/finalize`

Memfinalisasi snapshot dan membuat hash integritas. Finalisasi ditolak bila masih ada temuan terbuka atau ledger berubah setelah perhitungan. Laporan final tidak dapat dihitung ulang.

### POST `/tax-reports/2026/10/mark-reported`

```json
{
  "referenceNumber": "DJP-2026-10-001",
  "reportedAt": "2026-11-10T09:00:00+07:00",
  "notes": "Reported through the official tax channel."
}
```

Status laporan: `draft`, `revision_required`, `ready`, dan `reported`.

## Tax audit findings

### GET `/tax-audit-findings?year=2026&month=10&status=open`

Mengambil hasil pemeriksaan. Status opsional: `open`, `resolved`, atau `dismissed`.

### POST `/tax-audit-findings/{id}/include`

Memasukkan transaksi bank pada temuan ke tax ledger. Response mengembalikan `reportNeedsRecalculation: true`; panggil endpoint `recalculate` sebelum finalisasi.

### POST `/tax-audit-findings/{id}/resolve`

```json
{
  "resolution": "not_taxable",
  "notes": "This transaction is an owner capital deposit."
}
```

`resolution` menerima `not_taxable`, `duplicate`, `corrected`, atau `other`.

## PDF

### GET `/tax-reports/2026/10/pdf`

Mengunduh PDF laporan bulanan yang sudah difinalisasi.

### GET `/tax-reports/2026/annual-pdf`

Mengunduh ringkasan seluruh laporan bulanan yang telah difinalisasi pada tahun tersebut.

PDF InvoiceHub merupakan laporan pendukung berdasarkan data aplikasi, bukan bukti penerimaan atau pelaporan resmi dari DJP.

## Aturan pajak

Tarif, ambang omzet, masa berlaku, dan referensi regulasi disimpan pada tabel `tax_rule_versions`. Default konfigurasi adalah estimasi PPh final UMKM 0,5% dengan referensi PP 55/2022 dan perubahan yang berlaku. Kelayakan wajib pajak dan batas masa penggunaan skema tetap perlu disesuaikan dengan kondisi pengguna serta regulasi terbaru.

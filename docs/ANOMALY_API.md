# InvoiceHub Anomaly Detection API

Deteksi potensi kebocoran uang. **Deteksinya deterministik** — dikerjakan aturan Laravel atas
catatan pengguna sendiri, tanpa AI sama sekali. AI hanya dipakai belakangan, untuk *menjelaskan*
satu temuan yang dibuka pengguna.

Semua endpoint memerlukan bearer token Sanctum dan email terverifikasi.

Base URL lokal: `http://127.0.0.1:8000/api/v1`

## Endpoint

| Method | Endpoint | AI? | Fungsi |
| --- | --- | --- | --- |
| `GET` | `/anomalies/summary` | ✗ | Angka kartu loss monitor |
| `GET` | `/anomalies` | ✗ | Daftar temuan, terurut severity lalu nominal |
| `POST` | `/anomalies/scan` | ✗ | Jalankan pemindaian ulang |
| `POST` | `/anomalies/{id}/resolve` | ✗ | Tandai selesai / bukan masalah |
| `POST` | `/anomalies/{id}/ai-explanation` | ✅ | Penjelasan AI untuk satu temuan |

## Batas paket

| | Starter | Basic | Pro |
| --- | --- | --- | --- |
| `anomaly.detection` | ✗ | ✅ | ✅ |
| `ai.anomaly_explanation` | ✗ | ✅ | ✅ |

Starter tidak memiliki rekening bank (`bankAccounts: 0`), sehingga sebagian besar aturan tidak
akan pernah menghasilkan temuan untuknya.

## Aturan deteksi

Semua ambang batas ada di `config/anomaly.php` dan dimaksudkan untuk dikalibrasi terhadap data
nyata, bukan ditebak sekali lalu dikunci di kode.

| Tipe | Kondisi | Ambang bawaan |
| --- | --- | --- |
| `unmatched_incoming` | Mutasi masuk belum tercocokkan | usia ≥ 7 hari, nominal ≥ Rp 50.000 |
| `excessive_fee` | Potongan bank/marketplace tidak wajar | > Rp 25.000 atau > 5% nilai pembayaran |
| `excessive_discount` | Diskon invoice melebihi kewajaran | > 20% subtotal, nominal ≥ Rp 50.000 |
| `unsettled_balance` | Sisa tagihan tertinggal usai rekonsiliasi | usia ≥ 14 hari, sisa ≥ Rp 10.000 |
| `duplicate_payment` | Dua pembayaran identik berdekatan | selisih ≤ 48 jam |
| `repeated_reversal` | Mutasi dicocokkan lalu dibatalkan berulang | ≥ 2 kali |

Pemindaian melihat mundur `ANOMALY_LOOKBACK_DAYS` (bawaan 90 hari).

### Sifat pemindaian

- **Idempoten.** Memindai dua kali tidak menggandakan temuan; teks dan nominalnya diperbarui.
- **Menutup sendiri.** Temuan yang sudah tidak reproduksi lagi ditandai `resolved` dengan
  `resolution: auto_resolved`.
- **Menghormati keputusan pengguna.** Temuan yang sudah di-`dismiss` tidak pernah dibuka lagi.

## Ringkasan

```http
GET /api/v1/anomalies/summary
```

```json
{
  "success": true,
  "data": {
    "summary": {
      "openCount": 3, "criticalCount": 0, "warningCount": 3,
      "amountAtRisk": 1008000, "resolvedCount": 0, "explainedCount": 1,
      "lastDetectedAt": "2026-08-19T11:20:00+07:00"
    }
  }
}
```

## Daftar temuan

```http
GET /api/v1/anomalies?status=open&severity=critical&type=duplicate_payment&perPage=15&page=1
```

```json
{
  "id": 4, "type": "unmatched_incoming", "typeLabel": "Uang masuk belum tercocokkan",
  "severity": "warning", "tone": "yellow", "status": "open",
  "title": "Transfer masuk Rp 750.000 belum tercocokkan",
  "description": "Uang masuk sejak 29/07/2026 belum dikaitkan ke invoice mana pun...",
  "amountAtRisk": 750000,
  "source": { "type": "bank_transaction", "id": 2 },
  "actionUrl": "/reconciliation",
  "explanation": null,
  "detectedAt": "2026-08-19T11:20:00+07:00"
}
```

`tone` memakai kosakata warna yang sudah dipakai kartu dashboard, jadi frontend tidak perlu
menerjemahkan severity sendiri.

## Penjelasan AI

```http
POST /api/v1/anomalies/4/ai-explanation
```

Kirim `{"force": true}` untuk memaksa penjelasan baru; tanpa itu, penjelasan yang sudah
tersimpan dipakai ulang tanpa biaya (`source: "cache"`).

```json
{
  "data": {
    "explanation": {
      "explanation": "Ada transfer masuk sebesar Rp 750.000 dari SRI WAHYUNI pada 29/07/2026 yang belum terhubung ke tagihan mana pun...",
      "likelyCause": "Pelanggan tidak mencantumkan nomor invoice pada berita transfer...",
      "preventionTip": "Minta pelanggan untuk selalu mencantumkan nomor tagihan...",
      "recommendedAction": "reconcile_manually",
      "actionLabel": "Cocokkan Manual",
      "actionUrl": "/reconciliation",
      "confidence": 85,
      "aiRunId": 21,
      "aiAvailable": true,
      "source": "ai"
    }
  }
}
```

### Validasi

- **Nominal rupiah** yang disebut AI harus ada persis pada data temuan. Pemeriksaan sengaja
  dibatasi pada angka berawalan `Rp`: tahun, jumlah hari, dan persentase bukan risiko dan
  akan menimbulkan penolakan palsu bila ikut diperiksa.
- `recommendedAction` harus ada di enum `AnomalyAction`; **URL tujuan diturunkan Laravel** dari
  sumber temuan, bukan dari AI.
- `confidence` bilangan bulat 0–100.
- Panjang: explanation 600, likelyCause 300, preventionTip 300 karakter.

Keluaran yang gagal validasi dicoba ulang, dicatat sebagai `invalid_output`, lalu jatuh ke
deskripsi deterministik (`source: "rules"`).

### AI tidak mendeteksi apa pun

AI menerima temuan yang sudah jadi dan hanya menjelaskannya. AI tidak menentukan apakah sesuatu
janggal, tidak menghitung nominal, dan tidak mengubah status temuan.

## Menyelesaikan temuan

```http
POST /api/v1/anomalies/4/resolve
```

```json
{ "resolution": "fixed", "notes": "Sudah dicocokkan manual" }
```

`resolution` salah satu dari `fixed`, `not_an_issue` (menjadi `dismissed`), atau `accepted_loss`.

## Penjadwalan

| Jadwal | Job | Queue |
| --- | --- | --- |
| 06:30 WIB harian | `DispatchAnomalyDetection` → `DetectFinancialAnomalies` | `reports` |

Deteksi berjalan di queue `reports`, bukan `ai`, karena tidak memanggil provider mana pun dan
tidak boleh tertahan di belakang permintaan AI yang lambat.

## Keterbatasan yang diketahui

Mockup dashboard menyebut *"Ongkir belum tertagih"*. Ini **belum bisa** dideteksi: `invoice_items`
hanya menyimpan `description`, `quantity`, dan `price` tanpa kategori, sehingga sistem tidak dapat
mengetahui sebuah baris adalah ongkos kirim. Mendukungnya memerlukan perubahan skema invoice.

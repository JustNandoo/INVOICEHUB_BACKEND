# InvoiceHub AI API

Fitur AI berjalan sebagai **lapisan analisis**, bukan pelaku aksi. Laravel tetap satu-satunya
yang menghitung uang, mengubah status, dan menulis ke database. AI hanya meranking kandidat
yang sudah disediakan Laravel dan menjelaskan alasannya.

Semua endpoint memerlukan bearer token Sanctum dan email terverifikasi.

```http
Accept: application/json
Authorization: Bearer YOUR_TOKEN
```

Base URL lokal: `http://127.0.0.1:8000/api/v1`

## Endpoint

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `POST` | `/bank-transactions/{id}/ai-analysis` | Analisis AI untuk kandidat rekonsiliasi |
| `POST` | `/invoices/{id}/ai-reminder-draft` | Draft pesan pengingat pembayaran |
| `GET` | `/ai/insights` | Insight keuangan tersimpan (tidak memanggil AI) |
| `POST` | `/ai/insights/refresh` | Antre pembuatan insight baru |
| `DELETE` | `/ai/insights/{id}` | Sembunyikan satu insight |
| `POST` | `/anomalies/{id}/ai-explanation` | Penjelasan temuan kebocoran — lihat [Anomaly API](ANOMALY_API.md) |
| `POST` | `/ai/runs/{aiRun}/feedback` | Menyimpan masukan pengguna atas satu hasil AI |
| `GET` | `/ai/usage` | Sisa kredit AI harian dan bulanan |

## Batas paket

| | Starter | Basic | Pro |
| --- | --- | --- | --- |
| `ai.reminder_draft`, `ai.insights` | ✅ | ✅ | ✅ |
| `ai.reconciliation` | ✗ | ✅ | ✅ |
| `ai.reminder_tones` (pilih nada, 3 varian) | ✗ | ✅ | ✅ |
| `ai.on_demand_refresh` (insight) | ✗ | ✅ | ✅ |
| `ai.anomaly_explanation` | ✗ | ✅ | ✅ |
| Insight maksimal | 2 | 3 | 5 |
| Interval pembuatan insight | 7 hari | 1 hari | 1 hari |
| `ai.deep_analysis` (model reasoning) | ✗ | ✅ | ✅ |
| Kredit / bulan | 25 | 250 | 900 |
| Kredit / hari | 3 | 30 | 100 |
| Kandidat dianalisis | 0 | 2 | 3 |

Bobot kredit: analisis rekonsiliasi 4, penjelasan anomali 4, insight 3, draft pengingat 1. Tiga varian nada tetap
dihitung 1 kredit karena dihasilkan dalam satu panggilan. Angka ini sementara dan
harus ditinjau ulang setelah pemakaian token nyata terukur.

## Analisis rekonsiliasi

```http
POST /api/v1/bank-transactions/81/ai-analysis
```

Alur di balik layar:

1. `ReconciliationMatchingService` menghasilkan kandidat berbasis aturan (tanpa AI).
2. Maksimal 3 kandidat teratas (sesuai `aiMaxCandidates`) dikirim ke AI setelah disanitasi.
3. AI hanya boleh memilih dari daftar itu. ID di luar daftar ditolak mentah-mentah.
4. Hasil disimpan pada `ai_runs` dan kolom `ai_*` di `reconciliation_suggestions`.
5. **Tidak ada perubahan data keuangan.** Pengguna tetap harus memanggil `POST /reconciliations`.

```json
{
  "success": true,
  "message": "Analisis AI selesai. Konfirmasi Anda tetap diperlukan.",
  "data": {
    "transactionId": 81,
    "candidates": [
      {
        "id": 12, "score": 85, "reasons": ["Selisih nominal masih dalam batas biaya admin."],
        "ai": { "runId": 4, "rank": 1, "confidence": 93,
                "reasons": ["Selisih Rp 1.500 sesuai biaya admin BCA."], "requiresReview": false }
      }
    ],
    "analysis": {
      "recommendedInvoiceId": 42,
      "confidence": 93,
      "reasons": ["Selisih Rp 1.500 sesuai biaya admin BCA."],
      "inferredFee": 1500,
      "verifiedFee": 1500,
      "feeMismatch": false,
      "recommendedAction": "confirm_with_fee",
      "requiresReview": false,
      "aiRunId": 4,
      "aiAvailable": true,
      "source": "ai"
    }
  }
}
```

`recommendedAction` selalu salah satu dari `confirm_exact`, `confirm_with_fee`,
`confirm_partial`, `review_manually`, `not_a_payment`.

### Validasi keluaran AI

Keluaran yang gagal salah satu pemeriksaan berikut **tidak pernah** disimpan sebagai hasil sukses.
Sistem mencoba ulang maksimal dua kali, lalu jatuh ke hasil berbasis aturan.

- `recommendedInvoiceId` wajib ada di daftar kandidat, atau `null`.
- `confidence` bilangan bulat 0–100.
- `recommendedAction` harus ada di daftar aksi yang diizinkan.
- `reasons` minimal satu, maksimal lima, tiap alasan dipotong 200 karakter.
- `inferredFee` dibandingkan dengan `difference_amount` hasil hitungan Laravel. Kalau berbeda,
  `feeMismatch` bernilai true dan `requiresReview` dipaksa true.
- `confidence` di bawah 70 memaksa `requiresReview` true.

### Saat AI tidak tersedia

Respons tetap `200` dengan `aiAvailable: false`, `source: "rules"`, dan kandidat berbasis aturan.
Rekonsiliasi manual tidak pernah terganggu.

```json
{ "analysis": { "aiAvailable": false, "source": "rules", "unavailableReason": "feature_flag_off" } }
```

`unavailableReason` yang mungkin: `feature_flag_off`, `daily_cost_limit_reached`,
`rate_limited`, `provider_unavailable`, `invalid_output`, `no_candidates`.

## Draft pengingat pembayaran

```http
POST /api/v1/invoices/42/ai-reminder-draft
Content-Type: application/json
```

```json
{ "channel": "whatsapp", "tone": "firm" }
```

- `channel`: `email` atau `whatsapp` (default `whatsapp`). Email menyertakan `subject`.
- `tone`: `polite`, `neutral`, atau `firm`. **Hanya paket dengan `ai.reminder_tones`.**
  Tanpa `tone`: paket berbayar menerima 3 varian sekaligus (tetap 1 kredit), paket Starter
  menerima 1 draft dengan nada yang dipilih otomatis dari lama keterlambatan
  (≤7 hari `polite`, 8–30 hari `neutral`, >30 hari `firm`).

```json
{
  "success": true,
  "message": "Draft pengingat siap. Silakan tinjau dan sunting sebelum dikirim.",
  "data": {
    "invoice": { "id": 42, "invoiceNumber": "INV-2026-042", "customerName": "Toko Budi",
                 "balanceDue": 450000, "dueDate": "2026-07-29" },
    "reminder": {
      "drafts": [
        { "tone": "firm", "subject": null,
          "message": "Kepada Toko Budi, kami mencatat bahwa invoice INV-2026-042 sebesar Rp 450.000 telah jatuh tempo sejak 29 Juli 2026 (terlambat 21 hari)..." }
      ],
      "channel": "whatsapp",
      "aiRunId": 7,
      "aiAvailable": true,
      "source": "ai"
    },
    "sendWith": {
      "method": "POST",
      "url": "/api/v1/invoices/42/send",
      "body": { "channel": "whatsapp", "message": "<draft yang sudah Anda sunting>" }
    }
  }
}
```

**AI tidak mengirim apa pun.** Endpoint ini hanya menulis draft: `sent_at` tidak berubah dan
tidak ada `invoice_activities` yang dibuat. Pengiriman tetap lewat `POST /invoices/{id}/send`
yang sudah ada, setelah pengguna menyunting draftnya.

### Validasi draft

Draft yang gagal salah satu pemeriksaan berikut ditolak, dicoba ulang, lalu jatuh ke template Laravel:

- Panjang pesan maksimal **1000 karakter**, dicek sebelum pembersihan sehingga pesan kepanjangan
  ditolak, bukan dipotong di tengah kalimat. Batas ini mengikuti aturan `message` pada
  `SendInvoiceRequest` agar draft selalu bisa langsung dikirim.
- Pesan wajib menyebut nomor invoice persis.
- Nada harus termasuk yang diminta, dan tidak boleh ada nada ganda.
- Placeholder yang belum diisi (`{{nama}}`, `[isi di sini]`) ditolak.
- `subject` hanya dikembalikan untuk kanal email, maksimal 150 karakter.

Nominal rupiah dikirim ke AI dalam bentuk yang sudah diformat Laravel (`balanceDueFormatted`),
dan prompt memerintahkan model menyalinnya apa adanya — model tidak pernah menghitung uang.

Invoice yang sudah lunas atau tanpa sisa tagihan ditolak dengan `422`.

### Saat AI tidak tersedia

Respons tetap `200` dengan `source: "template"` dan satu draft yang disusun Laravel dari data
invoice. Pengingat manual tidak pernah terhalang.

## Insight keuangan

Fitur ini **asinkron**. Pembuatan berjalan di queue `ai`, dan endpoint dashboard hanya membaca
database.

```
Scheduler 07:00 WIB → DispatchFinancialInsightRefresh (fan-out, lewati paket & user yang belum jatuh tempo)
                    → GenerateFinancialInsights (per user, queue "ai", unik per user per hari)
                    → metrik agregat Laravel → AI → validasi → ai_insights → notifikasi
```

### Membaca insight

```http
GET /api/v1/ai/insights
```

**Endpoint ini tidak pernah memanggil provider AI.** Membuka dashboard seratus kali tetap
berbiaya nol.

```json
{
  "success": true,
  "data": {
    "insights": [
      {
        "id": 9, "aiRunId": 12, "type": "receivable", "severity": "critical", "tone": "pink",
        "title": "Tagihan Jatuh Tempo Perlu Segera Ditindaklanjuti",
        "summary": "Terdapat tagihan menunggak yang telah melewati batas waktu pembayaran...",
        "evidence": [
          { "label": "Jumlah invoice menunggak", "value": 3 },
          { "label": "Total nilai tagihan jatuh tempo", "value": 5560000 }
        ],
        "recommendedAction": "send_reminders",
        "actionLabel": "Kirim Pengingat",
        "actionUrl": "/invoices?status=overdue",
        "position": 1,
        "validUntil": "2026-08-20T07:00:00+07:00"
      }
    ],
    "meta": {
      "enabled": true, "maxInsights": 5,
      "lastGeneratedAt": "2026-08-19T07:00:00+07:00",
      "canRefreshOnDemand": true,
      "nextRefreshAvailableAt": "2026-08-19T08:00:00+07:00"
    }
  }
}
```

`tone` (`pink`/`yellow`/`blue`) memetakan severity ke kosakata warna yang sudah dipakai kartu
dashboard, sehingga frontend tidak perlu menerjemahkan sendiri.

### Meminta insight baru

```http
POST /api/v1/ai/insights/refresh
```

Membalas `202` dan menaruh job di queue. Hanya untuk paket dengan `ai.on_demand_refresh`,
dengan jeda **1 jam** sejak pembuatan terakhir; lebih cepat dari itu dibalas `422`.

### Validasi anti-halusinasi

Ini pemeriksaan terpenting pada fitur ini. Laravel mengumpulkan **seluruh angka** pada blok
metrics menjadi daftar yang diizinkan, lalu setiap nilai `evidence` harus ada persis di daftar
itu. Satu angka karangan membuat insight tersebut dibuang; kalau tidak ada yang tersisa,
seluruh keluaran ditolak, dicoba ulang, dan `ai_runs.status` menjadi `invalid_output`.

Selain itu:

- `type`, `severity`, dan `recommendedAction` harus ada di daftar tertutup.
- **`actionUrl` tidak berasal dari AI.** AI hanya memilih `recommendedAction`, lalu Laravel
  menurunkan URL-nya dari enum `InsightAction`, sehingga model tidak bisa mengarahkan pengguna
  ke tautan yang tidak terduga.
- Jumlah insight dipotong sesuai `aiMaxInsights` paket.
- `title` maksimal 150 karakter, `summary` 500, evidence maksimal 4 angka.

### Yang dikirim ke AI

Hanya **metrik agregat**, tidak pernah buku besar. Nomor invoice, nama pelanggan, dan email
tidak ikut. Isinya ringkasan invoice, pelanggan, arus kas mingguan, dan — hanya untuk paket
yang punya modul rekonsiliasi — ringkasan pencocokan mutasi.

### Masa berlaku dan riwayat

Insight berlaku 24 jam (`valid_until`). Saat batch baru dibuat, batch lama **tidak dihapus**
melainkan dikedaluwarsakan, sehingga riwayat tetap bisa diaudit. Insight yang disembunyikan
pengguna menyimpan `dismissed_at`.

### Latensi

Panggilan insight terukur 12–23 detik pada model reasoning karena token *thinking*. Inilah
alasan fitur ini berjalan di queue, bukan di request sinkron.

## Masukan pengguna

```http
POST /api/v1/ai/runs/4/feedback
```

```json
{ "accepted": false, "rating": 2, "correctedValue": { "invoiceId": 57 }, "comment": "Salah invoice" }
```

Masukan juga tercatat otomatis: setiap kali pengguna mengonfirmasi rekonsiliasi,
listener `RecordReconciliationAiFeedback` mencatat apakah pilihan pengguna sama dengan
rekomendasi AI. Jalur penulisan keuangan tidak disentuh sama sekali.

## Sisa kredit

```http
GET /api/v1/ai/usage
```

```json
{
  "success": true,
  "data": {
    "enabled": true,
    "credits": {
      "daily": { "used": 8, "limit": 30, "remaining": 22, "unlimited": false },
      "monthly": { "used": 64, "limit": 250, "remaining": 186, "unlimited": false }
    }
  }
}
```

## Kode error

| HTTP | `error.code` | Arti |
| --- | --- | --- |
| 403 | `PLAN_UPGRADE_REQUIRED` | Paket tidak punya fitur AI tersebut |
| 429 | `AI_QUOTA_EXCEEDED` | Kredit harian atau bulanan habis |
| 503 | `AI_UNAVAILABLE` | Kill switch aktif atau batas biaya harian global tercapai |

## Queue dan penjadwalan

Fitur insight memerlukan worker pada queue `ai`:

```bash
php artisan queue:work --queue=ai,notifications,reports --tries=3 --timeout=120
```

| Jadwal | Job | Fungsi |
| --- | --- | --- |
| 07:00 WIB harian | `DispatchFinancialInsightRefresh` | Fan-out pembuatan insight |
| 02:30 WIB harian | `PruneAiRuns` | Hapus `ai_runs` melewati `AI_RETENTION_DAYS` |

## Keamanan

- Kunci API hanya ada di backend. React tidak pernah memanggil provider AI langsung.
- Deskripsi mutasi, nama pengirim, dan nama pelanggan diperlakukan sebagai **data tidak tepercaya**
  dan dibungkus blok `<data>` dengan instruksi eksplisit untuk tidak dianggap perintah.
- Email, WhatsApp, alamat, dan nomor rekening lengkap tidak pernah dikirim ke provider.
- Walaupun prompt injection berhasil mengelabui model, allowlist kandidat membuatnya mustahil
  menunjuk invoice di luar daftar, dan AI tidak punya jalur eksekusi apa pun ke service domain.

## Konfigurasi

```
AI_ENABLED=true
AI_PROVIDER=gemini
AI_TIMEOUT_SECONDS=30
AI_MAX_RETRIES=2
AI_DAILY_COST_LIMIT_IDR=100000

GEMINI_API_KEY=
GEMINI_MODEL_REASONING=gemini-3.6-flash
GEMINI_MODEL_FAST=gemini-3.5-flash-lite
```

Catatan provider Gemini:

- `thinkingBudget: 0` **ditolak** Gemini 3.x. Nilai `0` pada `config/ai.php` berarti kunci tidak
  dikirim sama sekali sehingga model memakai bawaannya.
- Token *thinking* dihitung terhadap `maxOutputTokens`. Anggaran yang terlalu kecil membuat JSON
  terpotong (`finishReason: MAX_TOKENS`) dan permintaan diulang otomatis.
- Free tier memiliki batas laju; 503 dan 429 ditangani sebagai error sementara dan diulang.

Mengganti provider cukup dengan menulis implementasi `AiProvider` baru dan mengubah `AI_PROVIDER`.
Service fitur tidak pernah menyebut nama provider maupun nama model.

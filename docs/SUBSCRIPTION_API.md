# Subscription API

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Modul ini menyediakan katalog paket, paket aktif akun, hak fitur, pemakaian limit, serta pembayaran langganan lewat **Midtrans Snap**.

## Paket

| Paket | Harga | Limit utama |
|---|---:|---|
| Starter | Gratis | 10 invoice/bulan, tanpa rekening bank |
| Basic | Rp99.000/bulan | Invoice unlimited, 1 rekening bank, rekonsiliasi dan laporan pajak bulanan |
| Pro | Rp299.000/bulan | Semua fitur Basic, 3 rekening bank, audit serta laporan pajak tahunan |

Akun baru otomatis mendapat Starter. Akun lama yang belum memiliki record langganan juga otomatis mendapat Starter ketika API langganan atau fitur berlimit pertama kali dipanggil.

## Endpoint

### GET `/subscriptions/plans`

Publik, tidak memerlukan Bearer token. Mengembalikan katalog tiga paket untuk kartu pricing/settings.

### GET `/subscription`

Memerlukan Bearer token dan email terverifikasi. Mengembalikan paket aktif, periode, feature flags, limit, usage, serta riwayat pembayaran. Contoh di bawah adalah akun Starter yang belum pernah membayar dan server yang kredensial Midtrans-nya belum diisi.

```json
{
  "success": true,
  "data": {
    "subscription": {
      "id": 1,
      "status": "active",
      "source": "default",
      "plan": {
        "code": "starter",
        "name": "Starter",
        "price": 0,
        "currency": "IDR",
        "billingInterval": null,
        "description": "Mulai rapikan invoice bisnis pertamamu.",
        "features": {
          "invoice.create": true,
          "bank.integration": false
        },
        "benefits": [
          {"label": "Maks. 10 invoice / bulan", "included": true}
        ],
        "limits": {
          "monthlyInvoices": 10,
          "bankAccounts": 0,
          "stores": 1
        },
        "isRecommended": false
      },
      "startsAt": "2026-08-16T15:00:00+00:00",
      "currentPeriodStartsAt": "2026-08-16T15:00:00+00:00",
      "currentPeriodEndsAt": null,
      "endsAt": null
    },
    "usage": {
      "monthlyInvoices": {"used": 2, "limit": 10, "remaining": 8, "unlimited": false},
      "bankAccounts": {"used": 0, "limit": 0, "remaining": 0, "unlimited": false},
      "stores": {"used": 1, "limit": 1, "remaining": 0, "unlimited": false}
    },
    "paymentHistory": [],
    "paymentsAvailable": false
  }
}
```

### GET `/subscription/usage`

Memerlukan Bearer token. Mengembalikan pemakaian limit saja, cocok untuk refresh counter setelah membuat invoice.

### GET `/subscription/payment-history`

Memerlukan Bearer token. Mengembalikan pembayaran yang sudah berstatus akhir; order yang masih `pending` tidak ditampilkan. `paymentsAvailable` bernilai `false` selama kredensial Midtrans belum diisi di server.

### POST `/subscription/checkout`

Memerlukan Bearer token. Menyiapkan transaksi Snap. **Tidak mengubah paket apa pun.**

```json
{ "planCode": "basic" }
```

Nominal selalu dibaca dari katalog di server, jadi harga tidak dapat dimanipulasi dari client. Paket gratis ditolak dengan `PAYMENT_PLAN_NOT_PURCHASABLE`.

```json
{
  "success": true,
  "data": {
    "checkout": {
      "orderId": "SUB-20260826143012-A7K2QM",
      "snapToken": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",
      "amount": 99000,
      "planName": "Basic",
      "expiresAt": "2026-08-26T15:30:12+00:00",
      "clientKey": "SB-Mid-client-xxx",
      "snapJsUrl": "https://app.sandbox.midtrans.com/snap/snap.js",
      "isProduction": false
    }
  }
}
```

`clientKey` dan `snapJsUrl` dikirim dari server agar kunci sandbox dan produksi tidak pernah tertukar di frontend. Pengguna yang menutup popup lalu mencoba lagi menerima transaksi yang sama selama belum kedaluwarsa.

Kode error: `PAYMENT_NOT_CONFIGURED` (503), `PAYMENT_PLAN_NOT_PURCHASABLE` (422), `PAYMENT_PROVIDER_ERROR` (502).

### POST `/webhooks/midtrans`

Dipanggil server Midtrans, bukan browser, jadi **tanpa Sanctum**. Keabsahannya dijamin `signature_key`:

```
sha512(order_id + status_code + gross_amount + MIDTRANS_SERVER_KEY)
```

**Inilah satu-satunya jalur yang mengaktifkan paket berbayar.** Callback `onSuccess` di browser tidak pernah dipercaya karena siapa pun dapat memanggilnya dari konsol.

Pemetaan status Midtrans:

| `transaction_status` | Status internal | Paket aktif? |
|---|---|---|
| `settlement` | `paid` | Ya |
| `capture` + `fraud_status: accept` | `paid` | Ya |
| `capture` + `fraud_status: challenge` | `challenge` | Tidak |
| `pending` | `pending` | Tidak |
| `expire` | `expired` | Tidak |
| `refund`, `partial_refund` | `refunded` | Tidak |
| lainnya | `failed` | Tidak |

Endpoint ini aman dipanggil berulang: Midtrans mengirim ulang notifikasi sampai menerima 200, dan notifikasi susulan untuk order yang sudah lunas tidak mengaktifkan paket dua kali. Nominal notifikasi juga dicocokkan ulang dengan catatan lokal (`PAYMENT_AMOUNT_MISMATCH`).

Perpanjangan sebelum masa aktif habis menyambung dari `current_period_ends_at` yang lama, sehingga sisa hari yang sudah dibayar tidak hangus.

> Midtrans hanya dapat menarik dana otomatis lewat token kartu kredit dan GoPay. QRIS dan Virtual Account bersifat sekali bayar, jadi untuk mayoritas pengguna modelnya adalah **perpanjangan manual**.

## Enforcement ke API lain

| Entitlement | Dampak |
|---|---|
| `monthlyInvoices` | Starter maksimal membuat 10 invoice per bulan |
| `whatsapp.reminder` | Pengiriman invoice via WhatsApp hanya Basic/Pro |
| `reconciliation.automatic` | API bank dan rekonsiliasi hanya Basic/Pro |
| `tax.monthly_report` | API laporan pajak bulanan hanya Basic/Pro |
| `tax.automated_audit` | Temuan/audit pajak otomatis hanya Pro |
| `tax.annual_report` | PDF laporan pajak tahunan hanya Pro |

Ketika akses ditolak, responsnya:

```json
{
  "success": false,
  "message": "Fitur ini tidak tersedia pada paket langganan Anda saat ini.",
  "error": {
    "code": "PLAN_UPGRADE_REQUIRED",
    "requiredFeature": "reconciliation.automatic",
    "currentPlan": "starter",
    "limit": null
  }
}
```

## Konfigurasi

```dotenv
MIDTRANS_SERVER_KEY=SB-Mid-server-xxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxx
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_EXPIRY_MINUTES=60
MIDTRANS_FINISH_URL="${FRONTEND_URL}/settings/subscription"
```

Kunci sandbox gratis dari `dashboard.sandbox.midtrans.com`, tanpa dokumen usaha dan tanpa uang sungguhan.

Kunci sandbox dan produksi tidak dapat dibedakan dari bentuknya — keduanya berawalan `Mid-server-`/`Mid-client-` — sehingga pastikan environment di dashboard sudah benar saat menyalin. Keduanya tidak saling kompatibel: kunci yang salah environment ditolak `401 Unknown Merchant server_key/id`. Cara memastikan tanpa membuat transaksi:

```bash
curl -s -u "$MIDTRANS_SERVER_KEY:" https://api.sandbox.midtrans.com/v2/CEK/status
```

`404 Transaction doesn't exist` berarti kunci cocok dengan environment itu; `401` berarti tidak.

Daftarkan URL notifikasi di dashboard Midtrans → Settings → Configuration:

```
https://<domain-anda>/api/v1/webhooks/midtrans
```

Urutan metode pembayaran di popup Snap diatur lewat `services.midtrans.enabled_payments`. QRIS sengaja didahulukan karena biayanya paling murah bagi penerima (0,7%), sedangkan kartu kredit paling mahal (2,9% + Rp2.000).

## Aktivasi tanpa pembayaran

Tidak ada endpoint untuk mengubah paket langsung dari client. Untuk aktivasi manual oleh admin, panggil service internal:

```php
app(\App\Services\Subscription\SubscriptionService::class)->activatePlan(
    user: $user,
    planCode: 'pro',
    source: 'payment_provider',
    periodEndsAt: now()->addMonth()->toImmutable(),
    metadata: ['paymentReference' => $verifiedReference],
);
```

Service tersebut menutup plan aktif sebelumnya, menyimpan histori langganan, mengaktifkan plan baru, dan langsung mengubah entitlement seluruh API terkait.

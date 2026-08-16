# Subscription API

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Modul ini menyediakan katalog paket, paket aktif akun, hak fitur, dan pemakaian limit. Endpoint pembelian, checkout, webhook pembayaran publik, serta invoice pembayaran langganan **belum disediakan** sampai payment provider dipilih.

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

Memerlukan Bearer token dan email terverifikasi. Mengembalikan paket aktif, periode, feature flags, limit, usage, serta payment history kosong.

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

Memerlukan Bearer token. Untuk saat ini selalu mengembalikan `payments: []` dan `paymentsAvailable: false`. Tidak ada data pembayaran palsu.

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

## Integrasi payment provider nanti

Tidak ada endpoint untuk mengubah paket dari client, sehingga user tidak dapat menaikkan paketnya sendiri tanpa pembayaran. Setelah provider dipilih, webhook yang sudah diverifikasi cukup memanggil service internal:

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

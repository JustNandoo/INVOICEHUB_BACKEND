# InvoiceHub Marketplace Integration API

Menghubungkan InvoiceHub dengan toko online (Shopee, Tokopedia, Lazada, TikTok Shop)
agar pesanan otomatis menjadi **pelanggan** dan **invoice**.

Semua endpoint memerlukan bearer token Sanctum, email terverifikasi, dan fitur paket
`marketplace.integration` — kecuali callback OAuth yang dipanggil marketplace.

## Dua jalur penyambungan

| Jalur | Butuh persetujuan platform? | Bisa dipakai sekarang? |
| --- | --- | --- |
| **Impor Manual (CSV/XLSX)** | Tidak | ✅ Ya |
| **OAuth** (Shopee, Tokopedia, Lazada, TikTok Shop) | Ya, akun partner developer | Setelah kredensial diisi |

Jalur manual ada karena persetujuan partner marketplace butuh waktu lama, sementara
setiap Seller Center sudah menyediakan ekspor pesanan. Hasil akhirnya identik: pelanggan
dan invoice terbentuk lewat service domain yang sama.

## Batas paket

| | Starter | Basic | Pro |
| --- | --- | --- | --- |
| `marketplace.integration` | ✗ | ✅ | ✅ |

## Endpoint

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `GET` | `/marketplaces` | Katalog platform + status sambungan |
| `POST` | `/marketplaces/connect` | Mulai sambungkan satu platform |
| `GET` | `/marketplaces/callback` | Pendaratan OAuth (dipanggil marketplace) |
| `DELETE` | `/marketplaces/{id}` | Putuskan sambungan |
| `POST` | `/marketplaces/{id}/sync` | Tarik pesanan terbaru (OAuth) |
| `POST` | `/marketplaces/{id}/import` | Unggah ekspor pesanan (manual) |
| `GET` | `/marketplaces/orders` | Daftar pesanan yang masuk |

## Katalog platform

```http
GET /api/v1/marketplaces
```

```json
{
  "platform": "shopee",
  "label": "Shopee",
  "kind": "oauth",
  "configured": false,
  "connection": null
}
```

`configured: false` berarti kredensial partner belum diisi di server, sehingga tombol
Hubungkan dinonaktifkan dan penyebabnya ditampilkan apa adanya kepada pengguna.

## Menyambungkan

```http
POST /api/v1/marketplaces/connect
{ "platform": "shopee" }
```

Platform OAuth membalas `authorizationUrl`; frontend mengarahkan pengguna ke sana.
Platform manual langsung berstatus `connected`.

### Keamanan alur OAuth

- Setiap penyambungan membuat **state token sekali pakai** berumur 15 menit.
- Callback hanya diterima bila state cocok dan belum kedaluwarsa, sehingga callback
  dari pihak lain tidak dapat membajak sambungan.
- `access_token` dan `refresh_token` disimpan **terenkripsi** dan tidak pernah
  dikembalikan lewat API (`$hidden` pada model).
- Memutus sambungan menghapus token, tetapi pesanan dan invoice yang sudah masuk tetap ada.

## Impor pesanan dari Seller Center

```http
POST /api/v1/marketplaces/{id}/import
Content-Type: multipart/form-data
```

Berkas CSV/XLS/XLSX maksimal 10 MB. Baris pertama harus berisi judul kolom; kolom
dikenali lewat **alias**, bukan posisi, jadi ekspor dari marketplace mana pun bisa
dipakai selama judulnya wajar.

| Kolom | Alias yang dikenali |
| --- | --- |
| Nomor pesanan | `No. Pesanan`, `Order ID`, `Order SN`, `Invoice` |
| Tanggal | `Tanggal`, `Order Date`, `Waktu Pesanan` |
| Nama pembeli | `Nama Pembeli`, `Buyer Name`, `Penerima` |
| Telepon | `No. Telepon`, `No HP`, `Phone`, `WhatsApp` |
| Kota, Alamat | `Kota`, `Alamat`, `City`, `Shipping Address` |
| Produk, Jumlah, Harga | `Nama Produk`, `Jumlah`, `Harga Satuan` |
| Ongkir, Biaya admin, Diskon | `Ongkos Kirim`, `Biaya Admin`, `Diskon` |

Satu pesanan boleh menempati beberapa baris (satu per produk); baris digabung
berdasarkan nomor pesanan. Contoh berkas: [`reconciliation-marketplace-template.csv`](reconciliation-marketplace-template.csv).

```json
{
  "data": {
    "import": {
      "parsed": 2, "stored": 2, "imported": 2, "skipped": 0, "failed": 0,
      "errors": []
    }
  }
}
```

## Bagaimana pesanan menjadi data InvoiceHub

```
Pesanan marketplace
   ↓ cocokkan pembeli lewat nomor WhatsApp ternormalisasi
Pelanggan  (CustomerService::create — penomoran & validasi seperti input manual)
   ↓
Invoice    (InvoiceService::create — batas paket, pajak, penomoran, event tetap berlaku)
```

Hal-hal penting:

- **Pembeli berulang tidak digandakan.** Pencocokan memakai nomor WhatsApp yang sudah
  dinormalisasi, jadi pembeli yang sama pada beberapa pesanan tetap satu pelanggan.
- **Ongkos kirim menjadi baris invoice tersendiri**, bukan lenyap di dalam total.
  Ini membuat ongkir ikut tertagih dan terlihat oleh deteksi kebocoran.
- **Pesanan tanpa nomor telepon dilewati** dengan alasan yang bisa dibaca, bukan
  membuat pelanggan berdata kosong.
- **Impor ulang berkas yang sama tidak menggandakan apa pun** — pesanan unik per
  `(sambungan, nomor pesanan eksternal)`.
- **Batas invoice paket tetap berlaku.** Bila kuota habis, sisa pesanan tetap
  berstatus `pending` dan bisa diproses lagi setelah paket ditingkatkan.
- `source` pelanggan mengikuti platform: `shopee`, `tokopedia`, atau `other`.
  Impor manual memakai `other` karena berkas tidak menyebut asal marketplace-nya.

## Sinkronisasi berkala

| Jadwal | Job | Queue |
| --- | --- | --- |
| Tiap jam | `DispatchMarketplaceSync` → `SyncMarketplaceConnection` | `reports` |

Hanya sambungan OAuth yang ditarik otomatis dan hanya bila `auto_sync` aktif. Impor
manual menunggu unggahan pengguna.

## Konfigurasi

```
MARKETPLACE_ENABLED=true
MARKETPLACE_LOOKBACK_DAYS=30
MARKETPLACE_INVOICE_DUE_DAYS=7
MARKETPLACE_REDIRECT_URL="${APP_URL}/api/v1/marketplaces/callback"
MARKETPLACE_RETURN_URL="${FRONTEND_URL}/settings/integrations"

SHOPEE_PARTNER_ID=
SHOPEE_PARTNER_KEY=
TOKOPEDIA_CLIENT_ID=
TOKOPEDIA_CLIENT_SECRET=
TOKOPEDIA_FS_ID=
LAZADA_APP_KEY=
LAZADA_APP_SECRET=
TIKTOK_SHOP_APP_KEY=
TIKTOK_SHOP_APP_SECRET=
```

`MARKETPLACE_REDIRECT_URL` harus didaftarkan **persis sama** di dashboard partner
masing-masing platform.

## Menambah platform baru

Tulis satu kelas yang mengimplementasikan `MarketplaceProvider`
(`authorizationUrl`, `exchangeCallback`, `fetchOrders`), daftarkan di
`MarketplaceProviderFactory`, lalu tambahkan entri di `config/marketplaces.php`.
Sisa aplikasi tidak berubah.

## Keterbatasan yang diketahui

- Adapter Shopee, Tokopedia, Lazada, dan TikTok Shop **belum pernah diuji terhadap API
  produksi** karena memerlukan akun partner yang disetujui. Struktur permintaan dan
  penandatanganan mengikuti dokumentasi masing-masing platform, tetapi harus
  diverifikasi ulang saat kredensial tersedia — API mereka berubah dari waktu ke waktu.
- TikTok Shop belum punya adapter khusus; sementara memakai jalur impor manual.
- Refresh token belum diperbarui otomatis saat kedaluwarsa; sambungan akan berstatus
  `expired` dan perlu dihubungkan ulang.

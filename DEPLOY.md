# Deploy InvoiceHub

Produksi: **https://invoicehub.my.id** — VPS BiznetGio `103.197.191.52`, user SSH `dedymadura`.

## Perintah

```bash
cd /Users/mm/InvoiceHub_Backend && ./deploy.sh
```

| Perintah | Yang dikerjakan |
|---|---|
| `./deploy.sh` | Backend + frontend |
| `./deploy.sh backend` | Hanya Laravel |
| `./deploy.sh frontend` | Hanya React |

Skrip sudah menangani rsync dengan pengecualian yang benar, `composer install --no-dev`,
`migrate --force`, rebuild cache, perbaikan symlink storage, perbaikan izin berkas,
build frontend di lokal, dan pemeriksaan HTTP di akhir.

## Sebelum deploy

```bash
cd /Users/mm/INVOICEHUB && npm run build
cd /Users/mm/InvoiceHub_Backend && php artisan test && vendor/bin/pint --test
```

Kalau menyentuh gambar: pastikan setiap path di `src/` punya berkasnya, **dan** cek
rujukan dari database — gambar blog terdaftar di `database/seeders/BlogPostSeeder.php`
serta kolom `blog_posts.cover_image_url`, bukan di kode frontend.

## Setelah deploy

Jangan hanya percaya "Selesai.". Periksa sendiri:

- `/`, `/features`, `/pricing`, `/about`, `/blog` → 200
- `/api/v1/subscriptions/plans` → 3 paket
- Gambar → pastikan `content_type` berupa `image/*`, bukan `text/html`
- Bila menyentuh foto profil → `/storage/profile-photos/...` harus 200

```bash
curl -s -o /dev/null -w "%{http_code} %{content_type}\n" https://invoicehub.my.id/images/logo-invoicehub.png
```

## Jebakan yang pernah terjadi

| Masalah | Sebab | Status |
|---|---|---|
| Foto profil 404 | Symlink `public/storage` tertimpa symlink laptop | Ditangani deploy.sh |
| Gambar 403 | rsync mempertahankan izin 600 dari laptop | Ditangani deploy.sh |
| Laravel error `PailServiceProvider` | `bootstrap/cache` dari laptop berisi paket dev | Ditangani deploy.sh |
| Gambar hilang balas 200 HTML | Fallback SPA Nginx | Ditangani config Nginx |

**Jangan hapus pengecualian rsync di `deploy.sh`** — semuanya ada karena pernah menjatuhkan produksi.

## Jangan

- Jangan ubah `.env` produksi kecuali diminta.
- Jangan jalankan `composer update` — lock file terkunci ke PHP 8.5, server juga 8.5.
- Jangan hapus gambar tanpa mengecek rujukan dari database lebih dulu.

## Catatan terbuka

- **Nameserver kedua BiznetGio rusak** (`dua.neodns.id` SERVFAIL untuk zona ini).
  Perpanjangan SSL otomatis akan gagal. Batas waktu ~25 September 2026.
- **Midtrans masih kunci sandbox** — pembayaran belum memakai uang sungguhan.

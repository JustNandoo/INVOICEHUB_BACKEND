# Akun Demo InvoiceHub

Akun ini hanya dibuat pada environment `local`. Seeder tidak membuat akun demo di
production sehingga kredensial demonstrasi tidak ikut aktif pada deployment nyata.

## Kredensial

| Field | Nilai |
| --- | --- |
| Email | `demo@invoicehub.id` |
| Password | `DemoInvoiceHub2026!` |
| Paket | Pro |
| Pemilik | Rani Prameswari |
| Usaha | Kopi Karsa Nusantara |

Email akun sudah terverifikasi agar dapat langsung dipakai untuk login.

## Membuat atau mereset data demo

```bash
php artisan db:seed --class=DemoAccountSeeder
php artisan db:seed --class=BlogPostSeeder
```

Perintah pertama aman dijalankan ulang. Data milik `demo@invoicehub.id` akan diganti
dengan snapshot demo baru tanpa menyentuh akun pengguna lain. `php artisan db:seed`
juga menjalankan akun demo secara otomatis saat aplikasi berada pada environment
`local`.

## Isi simulasi

- Profil pemilik UMKM terverifikasi dengan langganan Pro aktif.
- 12 pelanggan dari beberapa kanal penjualan.
- 35 invoice selama enam bulan: 30 lunas, 4 belum lunas, dan 1 draft.
- Total piutang Rp8.750.000 dengan dua invoice jatuh tempo.
- 3 rekening bank dan 23 mutasi kredit; 20 cocok otomatis (87%), 1 perlu
  konfirmasi, dan 2 belum cocok.
- Riwayat pembayaran, aktivitas invoice, penyesuaian biaya bank, serta kandidat
  rekonsiliasi.
- Profil pajak, buku pajak, enam laporan bulanan, sumber laporan, dan temuan audit.
- 4 koneksi marketplace dan 8 pesanan yang terhubung ke pelanggan serta invoice.
- Insight keuangan, temuan anomali, pemakaian kredit AI, target pendapatan, laporan
  mingguan, dan notifikasi.
- Artikel publik yang ditulis ulang berdasarkan sumber resmi DJP, Bank Indonesia,
  dan Kementerian Komunikasi dan Digital.

## Catatan keamanan

Jangan memakai password demo untuk akun produksi. Akun ini sengaja memakai domain
lokal dokumentasi dan data pelanggan fiktif; tidak ada data pribadi orang nyata atau
token marketplace produksi di dalam seeder.

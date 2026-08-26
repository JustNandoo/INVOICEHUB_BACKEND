<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $article) {
            BlogPost::query()->updateOrCreate(
                ['slug' => $article['slug']],
                $article,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            [
                'slug' => 'pph-final-umkm-2026-panduan-praktis-pemilik-usaha',
                'title' => 'PPh Final UMKM 2026: Panduan Praktis untuk Pemilik Usaha',
                'excerpt' => 'Pahami tarif 0,5%, batas omzet, dan catatan bulanan yang perlu disiapkan agar kewajiban pajak usaha lebih teratur.',
                'category' => 'Panduan Pajak',
                'cover_image_url' => '/images/blog-featured-tax.png',
                'cover_image_alt' => 'Ilustrasi pemilik UMKM memeriksa laporan pajak',
                'author_name' => 'Tim Pajak InvoiceHub',
                'reading_time_minutes' => 8,
                'is_featured' => true,
                'published_at' => '2026-08-20 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => [
                            'Skema PPh Final membantu pelaku UMKM menghitung pajak menggunakan peredaran bruto. Tarif yang berlaku adalah 0,5% bagi wajib pajak yang memenuhi persyaratan dan memiliki omzet tahunan tidak lebih dari Rp4,8 miliar.',
                            'Untuk wajib pajak orang pribadi, bagian omzet sampai Rp500 juta dalam satu tahun tetap tidak dikenai PPh. Karena batas tersebut dihitung secara tahunan, rekap omzet bulanan harus disimpan secara berurutan.',
                        ],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Catatan yang perlu disiapkan setiap bulan',
                        'paragraphs' => ['Gunakan invoice lunas dan mutasi yang sudah direkonsiliasi sebagai dasar pencatatan. Pisahkan transaksi yang masih menunggu verifikasi agar tidak dihitung dua kali.'],
                        'bullets' => [
                            'Rekap omzet seluruh kanal penjualan, termasuk marketplace.',
                            'Bukti pembayaran pelanggan dan settlement marketplace.',
                            'Daftar retur, refund, serta transaksi yang dibatalkan.',
                            'Bukti pembayaran dan pelaporan pajak pada periode sebelumnya.',
                        ],
                    ],
                    [
                        'heading' => 'Periksa kelayakan skema secara berkala',
                        'paragraphs' => [
                            'Tarif dan fasilitas hanya dapat digunakan selama usaha memenuhi kriteria yang berlaku. Saat bentuk usaha, omzet, atau sumber penghasilan berubah, lakukan pemeriksaan kembali atau konsultasikan dengan petugas pajak.',
                            'Referensi resmi: Direktorat Jenderal Pajak, “PPh Final UMKM Tetap 0,5 Persen, DJP Perkuat Ketepatan Sasaran” — https://pajak.go.id/id/siaran-pers/pph-final-umkm-tetap-05-persen-djp-perkuat-ketepatan-sasaran',
                        ],
                        'bullets' => [],
                    ],
                ],
            ],
            [
                'slug' => 'pencatatan-keuangan-digital-agar-umkm-naik-kelas',
                'title' => 'Pencatatan Keuangan Digital agar UMKM Naik Kelas',
                'excerpt' => 'Bangun kebiasaan pencatatan sederhana yang membantu pemilik usaha memahami performa dan menyiapkan akses pembiayaan.',
                'category' => 'Tips Keuangan',
                'cover_image_url' => '/images/blog-personal-business.png',
                'cover_image_alt' => 'Ilustrasi pencatatan keuangan digital untuk UMKM',
                'author_name' => 'Tim Edukasi InvoiceHub',
                'reading_time_minutes' => 7,
                'is_featured' => false,
                'published_at' => '2026-08-12 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => [
                            'Pencatatan bukan hanya kebutuhan saat membayar pajak. Data transaksi yang konsisten membantu pemilik melihat arus kas, laba rugi, kebutuhan modal, dan kemampuan usaha membayar kewajiban.',
                            'Bank Indonesia menempatkan pencatatan keuangan sebagai salah satu fondasi agar laporan UMKM lebih mudah dipahami lembaga keuangan ketika usaha membutuhkan pembiayaan.',
                        ],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Rutinitas singkat yang dapat dimulai hari ini',
                        'paragraphs' => ['Tidak perlu menunggu sistem yang rumit. Mulai dari proses yang dapat dilakukan setiap hari dan evaluasi hasilnya pada akhir minggu.'],
                        'bullets' => [
                            'Catat setiap penjualan dan biaya pada hari transaksi.',
                            'Pisahkan rekening usaha dari rekening pribadi.',
                            'Cocokkan mutasi bank dengan invoice secara berkala.',
                            'Tinjau piutang, persediaan, dan arus kas setiap minggu.',
                        ],
                    ],
                    [
                        'heading' => 'Gunakan laporan sebagai alat keputusan',
                        'paragraphs' => [
                            'Laporan yang rapi membantu menentukan produk yang menguntungkan, waktu pembelian stok, dan batas pengeluaran yang aman. Nilai utamanya bukan banyaknya laporan, melainkan keputusan yang dapat diambil dari data tersebut.',
                            'Referensi resmi: Bank Indonesia, Pedoman Pencatatan Transaksi Keuangan untuk UMK — https://www.bi.go.id/id/umkm/penelitian/Pages/Pedoman-Umum-Pedoman-Teknis-dan-Modul-PTK-untuk-UMK.aspx',
                        ],
                        'bullets' => [],
                    ],
                ],
            ],
            [
                'slug' => 'strategi-rapi-berjualan-di-banyak-marketplace',
                'title' => 'Strategi Rapi Berjualan di Banyak Marketplace',
                'excerpt' => 'Satukan data pesanan, pelanggan, biaya platform, dan pencairan dana agar ekspansi kanal penjualan tidak membuat pembukuan berantakan.',
                'category' => 'Tips Keuangan',
                'cover_image_url' => '/images/blog-whatsapp-feature.png',
                'cover_image_alt' => 'Ilustrasi UMKM mengelola penjualan dari berbagai kanal digital',
                'author_name' => 'Tim Bisnis Digital InvoiceHub',
                'reading_time_minutes' => 6,
                'is_featured' => false,
                'published_at' => '2026-08-07 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => [
                            'Menambah marketplace dapat memperluas jangkauan usaha, tetapi juga menambah sumber pesanan, potongan, refund, dan jadwal settlement. Tanpa identitas transaksi yang konsisten, omzet mudah tercatat ganda atau justru terlewat.',
                        ],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Gunakan satu alur untuk seluruh kanal',
                        'paragraphs' => ['Tentukan data minimum yang harus tersedia pada setiap pesanan sebelum masuk ke pembukuan utama.'],
                        'bullets' => [
                            'Simpan nomor pesanan asli dari setiap marketplace.',
                            'Pisahkan harga barang, ongkir, diskon, dan biaya platform.',
                            'Hubungkan settlement bank dengan kelompok pesanan terkait.',
                            'Gunakan data pelanggan yang sama untuk menghindari duplikasi.',
                        ],
                    ],
                    [
                        'heading' => 'Jaga kendali atas data usaha',
                        'paragraphs' => [
                            'Ekosistem perdagangan digital terus bergerak menuju layanan yang lebih terhubung. Pemilik UMKM tetap perlu mempunyai catatan internal yang dapat digunakan lintas platform agar keputusan bisnis tidak bergantung pada satu dashboard marketplace.',
                            'Referensi resmi: Kementerian Komunikasi dan Digital, “ION Mudahkan UMKM Bertransaksi Lintas Aplikasi” — https://www.komdigi.go.id/berita/siaran-pers/detail/wamen-nezar-ion-mudahkan-umkm-bertransaksi-lintas-aplikasi',
                        ],
                        'bullets' => [],
                    ],
                ],
            ],
            [
                'slug' => 'panduan-lengkap-lapor-spt-pajak-umkm-2024',
                'title' => 'Panduan Lengkap Lapor SPT Pajak UMKM 2024',
                'excerpt' => 'Pahami langkah demi langkah cara melaporkan SPT tahunan untuk usaha kecil menengah agar terhindar dari denda.',
                'category' => 'Panduan Pajak',
                'cover_image_url' => '/images/blog-featured-tax.png',
                'cover_image_alt' => 'Ilustrasi pemilik UMKM menyiapkan laporan pajak',
                'author_name' => 'Tim Pajak InvoiceHub',
                'reading_time_minutes' => 8,
                'is_featured' => false,
                'published_at' => '2024-10-12 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => [
                            'Pelaporan SPT tahunan adalah kewajiban penting bagi pelaku UMKM. Selain menjaga kepatuhan usaha, laporan yang rapi membantu Anda memahami kondisi bisnis secara menyeluruh.',
                            'Dengan catatan transaksi yang lengkap dan persiapan yang tepat, SPT dapat diselesaikan dengan lebih cepat dan akurat.',
                        ],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Dokumen yang perlu disiapkan',
                        'paragraphs' => ['Kumpulkan seluruh dokumen pendukung dalam satu tempat dan pastikan periodenya sesuai dengan tahun pajak yang dilaporkan.'],
                        'bullets' => [
                            'NPWP dan data identitas wajib pajak.',
                            'Rekap omzet bulanan selama satu tahun pajak.',
                            'Bukti pembayaran PPh Final.',
                            'Daftar aset, kewajiban, dan dokumen pendukung transaksi.',
                        ],
                    ],
                    [
                        'heading' => 'Periksa sebelum mengirim SPT',
                        'paragraphs' => ['Periksa identitas, periode pajak, nilai omzet, dan lampiran. Simpan Bukti Penerimaan Elektronik setelah pelaporan berhasil.'],
                        'bullets' => [],
                    ],
                ],
            ],
            [
                'slug' => 'cara-rekonsiliasi-bank-untuk-reseller-pemula',
                'title' => 'Cara Rekonsiliasi Bank untuk Reseller Pemula',
                'excerpt' => 'Jangan biarkan transaksi terlewat. Ini cara mudah mencocokkan saldo bank dengan catatan usaha.',
                'category' => 'Tips Keuangan',
                'cover_image_url' => '/images/blog-personal-business.png',
                'cover_image_alt' => 'Ilustrasi pengelolaan keuangan bisnis',
                'author_name' => 'Tim Edukasi InvoiceHub',
                'reading_time_minutes' => 6,
                'is_featured' => false,
                'published_at' => '2024-10-10 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => ['Rekonsiliasi bank adalah proses mencocokkan catatan transaksi bisnis dengan mutasi rekening. Langkah ini penting karena pembayaran dapat datang dari banyak pelanggan dan marketplace.'],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Mulai dari periode yang pendek',
                        'paragraphs' => ['Lakukan rekonsiliasi setiap hari atau setidaknya setiap minggu agar transaksi lebih mudah dikenali.'],
                        'bullets' => [
                            'Unduh mutasi rekening pada periode yang sama.',
                            'Cocokkan tanggal, nominal, dan nama pengirim.',
                            'Tandai biaya admin, refund, atau potongan marketplace.',
                            'Catat transaksi yang belum memiliki invoice.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'kisah-sukses-toko-kelontong-beralih-ke-digital',
                'title' => 'Kisah Sukses Toko Kelontong Modern Beralih ke Digital',
                'excerpt' => 'Membaca peluang dan merapikan pembukuan menjadi kunci sukses usaha yang terus bertumbuh.',
                'category' => 'Cerita Sukses',
                'cover_image_url' => '/images/blog-whatsapp-feature.png',
                'cover_image_alt' => 'Ilustrasi perkembangan bisnis UMKM digital',
                'author_name' => 'Tim Komunitas InvoiceHub',
                'reading_time_minutes' => 7,
                'is_featured' => false,
                'published_at' => '2024-10-08 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => ['Sebuah toko kelontong keluarga memulai transformasinya dari buku tulis menjadi pembukuan digital. Perubahan itu memberi pemilik gambaran lebih jelas tentang arus kas dan produk terlaris.'],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Perubahan dari kebiasaan sederhana',
                        'paragraphs' => ['Setiap penjualan dicatat pada hari yang sama. Tagihan pemasok dan pembayaran pelanggan juga dipisahkan.'],
                        'bullets' => [
                            'Memisahkan rekening pribadi dan usaha.',
                            'Membuat invoice untuk pelanggan langganan.',
                            'Memeriksa stok dan arus kas setiap akhir minggu.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'integrasi-invoice-langsung-ke-whatsapp',
                'title' => 'Fitur Baru: Integrasi Langsung ke WhatsApp',
                'excerpt' => 'Kirim tagihan langsung ke pelanggan melalui WhatsApp dengan proses yang lebih praktis.',
                'category' => 'Update Fitur',
                'cover_image_url' => '/images/blog-whatsapp-feature.png',
                'cover_image_alt' => 'Tampilan integrasi invoice melalui WhatsApp',
                'author_name' => 'Tim Produk InvoiceHub',
                'reading_time_minutes' => 5,
                'is_featured' => false,
                'published_at' => '2024-10-05 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => ['InvoiceHub kini membantu Anda mengirim invoice langsung melalui WhatsApp tanpa menyalin data secara manual.'],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Kirim invoice dalam beberapa langkah',
                        'paragraphs' => ['Setelah invoice selesai dibuat, pilih Kirim via WhatsApp dan periksa pesan sebelum mengirimkannya.'],
                        'bullets' => [
                            'Pesan dibuat otomatis dari data invoice.',
                            'Nomor diambil dari profil pelanggan.',
                            'Riwayat pengiriman tercatat pada detail invoice.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'memisahkan-keuangan-pribadi-dan-usaha',
                'title' => 'Memisahkan Keuangan Pribadi dan Usaha',
                'excerpt' => 'Langkah krusial yang sering diabaikan pemula namun sangat penting untuk kesehatan bisnis.',
                'category' => 'Tips Keuangan',
                'cover_image_url' => '/images/blog-personal-business.png',
                'cover_image_alt' => 'Ilustrasi pemisahan keuangan pribadi dan usaha',
                'author_name' => 'Tim Edukasi InvoiceHub',
                'reading_time_minutes' => 7,
                'is_featured' => false,
                'published_at' => '2024-10-02 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => ['Mencampur uang pribadi dan usaha membuat keuntungan bisnis sulit diukur. Pemisahan sejak awal membantu Anda mengetahui modal, biaya operasional, dan hasil usaha yang sebenarnya.'],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Buat batas yang jelas',
                        'paragraphs' => ['Gunakan rekening khusus usaha dan tentukan nominal pengambilan pribadi secara teratur.'],
                        'bullets' => [
                            'Gunakan rekening bank yang berbeda.',
                            'Catat modal dan pengambilan pribadi.',
                            'Bayar kebutuhan usaha dari rekening usaha.',
                            'Evaluasi arus kas secara berkala.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'biaya-usaha-yang-bisa-dikurangkan-dari-pajak',
                'title' => 'Daftar Biaya yang Bisa Dikurangkan dari Pajak',
                'excerpt' => 'Maksimalkan efisiensi pajak bisnis dengan mengetahui jenis biaya yang dapat dikurangkan.',
                'category' => 'Panduan Pajak',
                'cover_image_url' => '/images/blog-tax-expenses.png',
                'cover_image_alt' => 'Ilustrasi pencatatan biaya dan laporan pajak',
                'author_name' => 'Tim Pajak InvoiceHub',
                'reading_time_minutes' => 4,
                'is_featured' => false,
                'published_at' => '2024-09-28 09:00:00',
                'content' => [
                    [
                        'heading' => null,
                        'paragraphs' => ['Biaya yang berhubungan dengan kegiatan usaha perlu dicatat secara rapi dan didukung bukti transaksi.'],
                        'bullets' => [],
                    ],
                    [
                        'heading' => 'Contoh biaya operasional usaha',
                        'paragraphs' => ['Jenis biaya dapat berbeda sesuai bidang usaha dan kondisi wajib pajak.'],
                        'bullets' => [
                            'Pembelian bahan baku dan biaya produksi.',
                            'Sewa, listrik, internet, dan kebutuhan operasional.',
                            'Gaji atau upah tenaga kerja.',
                            'Biaya pemasaran, pengiriman, dan administrasi.',
                        ],
                    ],
                ],
            ],
        ];
    }
}

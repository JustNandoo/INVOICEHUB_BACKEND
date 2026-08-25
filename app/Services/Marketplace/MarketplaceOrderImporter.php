<?php

namespace App\Services\Marketplace;

use App\Exceptions\Marketplace\MarketplaceException;
use App\Models\MarketplaceConnection;
use App\Services\Marketplace\Support\MarketplaceOrderData;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Impor pesanan dari berkas ekspor Seller Center.
 *
 * Ini jalur yang bisa dipakai tanpa menunggu persetujuan partner: setiap marketplace
 * menyediakan ekspor pesanan dalam CSV/XLSX. Nama kolomnya berbeda-beda, jadi header
 * dikenali lewat daftar alias, bukan lewat posisi kolom.
 */
class MarketplaceOrderImporter
{
    /** Alias header yang dikenali, semua dibandingkan dalam huruf kecil. */
    private const COLUMNS = [
        'orderId' => ['no. pesanan', 'nomor pesanan', 'order id', 'order_id', 'order sn', 'invoice', 'no pesanan'],
        'orderedAt' => ['tanggal', 'tanggal pesanan', 'order date', 'created at', 'waktu pesanan', 'tanggal order'],
        'status' => ['status', 'status pesanan', 'order status'],
        'buyerName' => ['nama pembeli', 'buyer name', 'penerima', 'nama penerima', 'customer name', 'nama'],
        'buyerPhone' => ['no. telepon', 'nomor telepon', 'telepon', 'phone', 'no hp', 'nomor hp', 'whatsapp'],
        'buyerEmail' => ['email', 'email pembeli', 'buyer email'],
        'buyerCity' => ['kota', 'kota/kabupaten', 'city', 'kabupaten'],
        'buyerAddress' => ['alamat', 'alamat pengiriman', 'address', 'shipping address'],
        'productName' => ['nama produk', 'produk', 'product name', 'item name', 'nama barang'],
        'quantity' => ['jumlah', 'qty', 'quantity', 'jumlah produk'],
        'unitPrice' => ['harga satuan', 'harga', 'unit price', 'price', 'harga produk'],
        'shippingFee' => ['ongkos kirim', 'ongkir', 'shipping fee', 'biaya pengiriman'],
        'platformFee' => ['biaya admin', 'biaya administrasi', 'platform fee', 'komisi', 'admin fee'],
        'discount' => ['diskon', 'potongan', 'voucher', 'discount'],
        'total' => ['total', 'total pesanan', 'total amount', 'grand total', 'total pembayaran'],
    ];

    /**
     * @return array{orders: list<MarketplaceOrderData>, errors: list<array{row: int|null, message: string}>}
     */
    public function parse(MarketplaceConnection $connection, UploadedFile $file): array
    {
        try {
            $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        } catch (Throwable $exception) {
            throw new MarketplaceException(
                'Berkas tidak dapat dibaca: '.$exception->getMessage(),
                'MARKETPLACE_IMPORT_UNREADABLE',
            );
        }

        $header = $this->mapHeader(array_shift($rows) ?? []);

        if (! isset($header['orderId'])) {
            throw new MarketplaceException(
                'Kolom nomor pesanan tidak ditemukan. Pastikan baris pertama berisi judul kolom.',
                'MARKETPLACE_IMPORT_NO_HEADER',
            );
        }

        $grouped = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $orderId = $this->value($row, $header, 'orderId');

            if ($orderId === null || $orderId === '') {
                continue;
            }

            $name = $this->value($row, $header, 'buyerName');

            if ($name === null || $name === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Nama pembeli kosong.'];

                continue;
            }

            // Satu pesanan bisa menempati beberapa baris, satu per produk.
            $grouped[$orderId] ??= [
                'orderId' => $orderId,
                'orderedAt' => $this->value($row, $header, 'orderedAt'),
                'status' => $this->value($row, $header, 'status') ?? 'imported',
                'buyerName' => $name,
                'buyerPhone' => $this->value($row, $header, 'buyerPhone'),
                'buyerEmail' => $this->value($row, $header, 'buyerEmail'),
                'buyerCity' => $this->value($row, $header, 'buyerCity'),
                'buyerAddress' => $this->value($row, $header, 'buyerAddress'),
                'shippingFee' => $this->money($row, $header, 'shippingFee'),
                'platformFee' => $this->money($row, $header, 'platformFee'),
                'discount' => $this->money($row, $header, 'discount'),
                'total' => $this->money($row, $header, 'total'),
                'items' => [],
            ];

            $product = $this->value($row, $header, 'productName');
            $unitPrice = $this->money($row, $header, 'unitPrice');

            if ($product !== null && $product !== '' && $unitPrice > 0) {
                $grouped[$orderId]['items'][] = [
                    'description' => mb_substr($product, 0, 255),
                    'quantity' => max(1, (int) round($this->money($row, $header, 'quantity') ?: 1)),
                    'unitPrice' => $unitPrice,
                ];
            }
        }

        $orders = [];

        foreach ($grouped as $data) {
            $orderedAt = $this->parseDate($data['orderedAt']);
            $itemsTotal = array_sum(array_map(
                fn (array $item): int => $item['quantity'] * $item['unitPrice'],
                $data['items'],
            ));

            $orders[] = new MarketplaceOrderData(
                externalOrderId: (string) $data['orderId'],
                orderNumber: (string) $data['orderId'],
                status: (string) $data['status'],
                buyerName: (string) $data['buyerName'],
                buyerPhone: $data['buyerPhone'],
                buyerEmail: $data['buyerEmail'],
                buyerCity: $data['buyerCity'],
                buyerAddress: $data['buyerAddress'],
                totalAmount: $data['total'] > 0 ? $data['total'] : $itemsTotal + $data['shippingFee'],
                shippingFee: $data['shippingFee'],
                platformFee: $data['platformFee'],
                discountAmount: $data['discount'],
                items: $data['items'],
                orderedAt: $orderedAt,
                raw: ['source' => 'import', 'orderId' => $data['orderId']],
            );
        }

        return ['orders' => $orders, 'errors' => $errors];
    }

    /**
     * @param  list<mixed>  $row
     * @return array<string, int>
     */
    private function mapHeader(array $row): array
    {
        $header = [];

        foreach ($row as $index => $label) {
            $normalized = mb_strtolower(trim((string) $label));

            foreach (self::COLUMNS as $key => $aliases) {
                if (! isset($header[$key]) && in_array($normalized, $aliases, true)) {
                    $header[$key] = $index;
                }
            }
        }

        return $header;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $header
     */
    private function value(array $row, array $header, string $key): ?string
    {
        if (! isset($header[$key])) {
            return null;
        }

        $value = trim((string) ($row[$header[$key]] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $header
     */
    private function money(array $row, array $header, string $key): int
    {
        $raw = $this->value($row, $header, $key);

        if ($raw === null) {
            return 0;
        }

        // "Rp 1.250.000" dan "1250000,00" sama-sama harus terbaca.
        $digits = preg_replace('/[^\d]/', '', explode(',', $raw)[0]) ?? '';

        return $digits === '' ? 0 : (int) $digits;
    }

    private function parseDate(?string $value): CarbonImmutable
    {
        if ($value === null) {
            return CarbonImmutable::now();
        }

        // Carbon melempar exception bila format tidak cocok, tidak mengembalikan false.
        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $value);
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }
}

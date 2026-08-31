<?php

namespace Database\Seeders;

use App\Enums\Marketplace\ConnectionStatus;
use App\Enums\Marketplace\MarketplacePlatform;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCommerceSeeder extends Seeder
{
    /** @var list<array{name: string, email: string, whatsapp: string, city: string, address: string, source: string}> */
    private array $customerFixtures = [
        ['name' => 'Toko Makmur Jaya', 'email' => 'finance@makmurjaya.test', 'whatsapp' => '+628111001001', 'city' => 'Bandung', 'address' => 'Jl. Melati No. 12, Bandung', 'source' => 'tokopedia'],
        ['name' => 'CV Maju Terus', 'email' => 'admin@cvmaju.test', 'whatsapp' => '+628111001002', 'city' => 'Jakarta', 'address' => 'Jl. Kemang Raya No. 18, Jakarta', 'source' => 'shopee'],
        ['name' => 'Kedai Senja', 'email' => 'halo@kedaisenja.test', 'whatsapp' => '+628111001003', 'city' => 'Yogyakarta', 'address' => 'Jl. Kaliurang Km 5, Yogyakarta', 'source' => 'tiktok_shop'],
        ['name' => 'PT Sejahtera Abadi', 'email' => 'ap@sejahteraabadi.test', 'whatsapp' => '+628111001004', 'city' => 'Surabaya', 'address' => 'Jl. Basuki Rahmat No. 91, Surabaya', 'source' => 'manual'],
        ['name' => 'Dapur Nusa', 'email' => 'owner@dapurnusa.test', 'whatsapp' => '+628111001005', 'city' => 'Semarang', 'address' => 'Jl. Gajahmada No. 33, Semarang', 'source' => 'lazada'],
        ['name' => 'Bumi Organik Store', 'email' => 'order@bumiorganik.test', 'whatsapp' => '+628111001006', 'city' => 'Bogor', 'address' => 'Jl. Pajajaran No. 7, Bogor', 'source' => 'tokopedia'],
        ['name' => 'Ruang Rasa Coffee', 'email' => 'kasir@ruangrasa.test', 'whatsapp' => '+628111001007', 'city' => 'Malang', 'address' => 'Jl. Ijen No. 24, Malang', 'source' => 'shopee'],
        ['name' => 'Warung Bu Sari', 'email' => 'busari@example.test', 'whatsapp' => '+628111001008', 'city' => 'Cirebon', 'address' => 'Jl. Tuparev No. 5, Cirebon', 'source' => 'manual'],
        ['name' => 'Kantor Kita Bersama', 'email' => 'procurement@kantorkita.test', 'whatsapp' => '+628111001009', 'city' => 'Depok', 'address' => 'Jl. Margonda No. 40, Depok', 'source' => 'tokopedia'],
        ['name' => 'Laras Hampers', 'email' => 'laras@hampers.test', 'whatsapp' => '+628111001010', 'city' => 'Solo', 'address' => 'Jl. Slamet Riyadi No. 61, Solo', 'source' => 'instagram'],
        ['name' => 'Nusa Kreatif', 'email' => 'billing@nusakreatif.test', 'whatsapp' => '+628111001011', 'city' => 'Tangerang', 'address' => 'Jl. BSD Boulevard No. 8, Tangerang', 'source' => 'lazada'],
        ['name' => 'Sahabat Kopi Bali', 'email' => 'order@sahabatkopi.test', 'whatsapp' => '+628111001012', 'city' => 'Denpasar', 'address' => 'Jl. Teuku Umar No. 14, Denpasar', 'source' => 'tiktok_shop'],
    ];

    /** @var list<array{string, string}> */
    private array $productPairs = [
        ['Kopi Arabika Gayo 1 kg', 'Drip Bag House Blend (20 pcs)'],
        ['Kopi Robusta Temanggung 1 kg', 'Packaging Pouch Premium (50 pcs)'],
        ['Cold Brew Concentrate 1 liter', 'Sirup Gula Aren 500 ml'],
        ['Hampers Kopi Nusantara', 'Kartu Ucapan Kustom'],
        ['Kopi Flores Bajawa 1 kg', 'Filter Paper V60 (100 pcs)'],
    ];

    public function run(): void
    {
        $user = User::query()->where('email', DemoAccountSeeder::email())->firstOrFail();
        $customers = $this->createCustomers($user);
        $invoices = $this->createInvoices($user, $customers);
        $this->createMarketplaceData($user, $customers, $invoices);
    }

    /** @return list<Customer> */
    private function createCustomers(User $user): array
    {
        $customers = [];
        foreach ($this->customerFixtures as $index => $fixture) {
            $createdAt = CarbonImmutable::now()->subMonths(10 - min($index, 9))->addDays($index);
            $customer = Customer::query()->create([
                'user_id' => $user->id,
                'customer_code' => sprintf('CUST-%03d', $index + 1),
                ...$fixture,
                'whatsapp_normalized' => ltrim($fixture['whatsapp'], '+'),
                'is_active' => $index !== 11,
            ]);
            $customer->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
            $customers[] = $customer;
        }

        DB::table('customer_number_sequences')->updateOrInsert(
            ['user_id' => $user->id],
            ['last_number' => count($customers), 'created_at' => now(), 'updated_at' => now()],
        );

        return $customers;
    }

    /** @param list<Customer> $customers @return list<Invoice> */
    private function createInvoices(User $user, array $customers): array
    {
        $monthlyPaidAmounts = [
            [5_200_000, 4_800_000, 6_100_000, 3_900_000, 4_600_000],
            [6_400_000, 5_200_000, 7_100_000, 4_300_000, 5_900_000],
            [7_200_000, 6_800_000, 5_600_000, 8_100_000, 5_500_000],
            [9_500_000, 7_800_000, 8_400_000, 6_100_000, 8_000_000],
            [10_200_000, 9_400_000, 8_700_000, 6_300_000, 7_500_000],
            [12_500_000, 10_200_000, 9_400_000, 8_150_000, 8_500_000],
        ];
        $invoices = [];
        $sequence = 0;

        foreach ($monthlyPaidAmounts as $monthIndex => $amounts) {
            $period = CarbonImmutable::now()->subMonthsNoOverflow(5 - $monthIndex)->startOfMonth();
            foreach ($amounts as $itemIndex => $amount) {
                $sequence++;
                $issueDate = $period->addDays(2 + ($itemIndex * 5));
                $invoices[] = $this->createInvoice(
                    $user,
                    $customers[($sequence - 1) % count($customers)],
                    $sequence,
                    $amount,
                    Invoice::STATUS_PAID,
                    $issueDate,
                    $issueDate->addDays(14),
                    $this->productPairs[$itemIndex % count($this->productPairs)],
                );
            }
        }

        $openInvoices = [
            [3_500_000, CarbonImmutable::now()->subDays(42), CarbonImmutable::now()->subDays(25)],
            [2_800_000, CarbonImmutable::now()->subDays(31), CarbonImmutable::now()->subDays(14)],
            [2_000_000, CarbonImmutable::now()->subDays(8), CarbonImmutable::now()->addDays(4)],
            [450_000, CarbonImmutable::now()->subDays(3), CarbonImmutable::now()->addDays(7)],
        ];
        foreach ($openInvoices as $index => [$amount, $issueDate, $dueDate]) {
            $sequence++;
            $invoices[] = $this->createInvoice(
                $user,
                $customers[$index],
                $sequence,
                $amount,
                Invoice::STATUS_UNPAID,
                $issueDate,
                $dueDate,
                $this->productPairs[$index],
            );
        }

        $sequence++;
        $invoices[] = $this->createInvoice(
            $user,
            $customers[8],
            $sequence,
            4_200_000,
            Invoice::STATUS_DRAFT,
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            ['Paket Kopi Kantor Bulanan', 'Sewa Coffee Brewer'],
        );

        DB::table('invoice_number_sequences')->updateOrInsert(
            ['user_id' => $user->id, 'year' => CarbonImmutable::now()->year],
            ['last_number' => $sequence, 'created_at' => now(), 'updated_at' => now()],
        );

        return $invoices;
    }

    /** @param array{string, string} $products */
    private function createInvoice(
        User $user,
        Customer $customer,
        int $sequence,
        int $amount,
        string $status,
        CarbonImmutable $issueDate,
        CarbonImmutable $dueDate,
        array $products,
    ): Invoice {
        $firstLine = intdiv($amount * 7, 10);
        $secondLine = $amount - $firstLine;
        $sentAt = $status === Invoice::STATUS_DRAFT ? null : $issueDate->setTime(9, 15);
        $viewedAt = $status === Invoice::STATUS_DRAFT ? null : $issueDate->setTime(13, 40);
        $paidAt = $status === Invoice::STATUS_PAID ? $issueDate->addDays(2)->setTime(10, 5) : null;

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'number' => sprintf('INV-%d-%03d', $issueDate->year, $sequence),
            'status' => $status,
            'issuer_name' => $user->business_name,
            'issuer_email' => $user->email,
            'issuer_address' => 'Jl. Braga No. 88, Bandung, Jawa Barat 40111',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_whatsapp' => $customer->whatsapp,
            'customer_address' => $customer->address,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $amount,
            'tax_rate_basis_points' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => $status === Invoice::STATUS_PAID ? $amount : 0,
            'balance_due' => $status === Invoice::STATUS_PAID ? 0 : $amount,
            'notes' => 'Terima kasih telah mempercayai produk Kopi Karsa Nusantara.',
            'sent_at' => $sentAt,
            'viewed_at' => $viewedAt,
            'paid_at' => $paidAt,
        ]);
        $invoice->forceFill(['created_at' => $issueDate, 'updated_at' => $paidAt ?? $issueDate])->saveQuietly();

        $invoice->items()->createMany([
            ['description' => $products[0], 'quantity' => 1, 'unit_price' => $firstLine, 'line_total' => $firstLine, 'position' => 1],
            ['description' => $products[1], 'quantity' => 1, 'unit_price' => $secondLine, 'line_total' => $secondLine, 'position' => 2],
        ]);
        $invoice->activities()->create([
            'actor_id' => $user->id, 'type' => 'created', 'title' => 'Invoice Dibuat',
            'description' => 'Dibuat oleh Rani Prameswari.', 'metadata' => ['demo' => true],
            'occurred_at' => $issueDate->setTime(9, 0),
        ]);

        if ($status !== Invoice::STATUS_DRAFT) {
            $invoice->activities()->create([
                'actor_id' => $user->id, 'type' => 'sent', 'title' => 'Dikirim ke Pelanggan',
                'description' => 'Invoice dikirim melalui email dan WhatsApp.',
                'metadata' => ['channel' => 'whatsapp'], 'occurred_at' => $sentAt,
            ]);
            $invoice->activities()->create([
                'actor_id' => null, 'type' => 'viewed', 'title' => 'Invoice Dilihat',
                'description' => 'Pelanggan membuka tautan invoice.', 'metadata' => null,
                'occurred_at' => $viewedAt,
            ]);
        }

        if ($status === Invoice::STATUS_PAID) {
            $payment = $invoice->payments()->create([
                'recorded_by' => $user->id,
                'amount' => $amount,
                'method' => 'bank_transfer',
                'source' => 'bank_sync',
                'reference' => 'PAY-'.$invoice->number,
                'paid_at' => $paidAt,
                'notes' => 'Pembayaran terverifikasi dari mutasi rekening.',
            ]);
            $invoice->activities()->create([
                'actor_id' => $user->id, 'type' => 'paid', 'title' => 'Pembayaran Diterima (Lunas)',
                'description' => 'Pembayaran terverifikasi otomatis melalui transfer bank.',
                'metadata' => ['paymentId' => $payment->id, 'amount' => $amount], 'occurred_at' => $paidAt,
            ]);
        }

        return $invoice->refresh();
    }

    /** @param list<Customer> $customers @param list<Invoice> $invoices */
    private function createMarketplaceData(User $user, array $customers, array $invoices): void
    {
        $platforms = [
            MarketplacePlatform::Tokopedia,
            MarketplacePlatform::Shopee,
            MarketplacePlatform::Lazada,
            MarketplacePlatform::TiktokShop,
        ];

        foreach ($platforms as $platformIndex => $platform) {
            $connection = MarketplaceConnection::query()->create([
                'user_id' => $user->id,
                'platform' => $platform->value,
                'shop_id' => 'KARSA-'.strtoupper(substr($platform->value, 0, 4)).'-01',
                'shop_name' => 'Kopi Karsa Official',
                'status' => ConnectionStatus::Connected->value,
                'access_token' => 'demo-access-token-'.$platform->value,
                'refresh_token' => 'demo-refresh-token-'.$platform->value,
                'token_expires_at' => now()->addMonths(3),
                'scopes' => ['orders.read', 'shop.read'],
                'auto_sync' => true,
                'last_synced_at' => now()->subMinutes(15 + ($platformIndex * 7)),
                'synced_until' => now()->subMinutes(20),
                'imported_order_count' => 2,
            ]);

            foreach ([0, 1] as $orderIndex) {
                $invoice = $invoices[($platformIndex * 2) + $orderIndex];
                $customer = $customers[($platformIndex * 2) + $orderIndex];
                MarketplaceOrder::query()->create([
                    'user_id' => $user->id,
                    'marketplace_connection_id' => $connection->id,
                    'external_order_id' => strtoupper($platform->value).'-'.str_pad((string) ($orderIndex + 1), 5, '0', STR_PAD_LEFT),
                    'order_number' => 'ORD-'.$invoice->number,
                    'status' => 'completed',
                    'buyer_name' => $customer->name,
                    'buyer_phone' => $customer->whatsapp,
                    'buyer_email' => $customer->email,
                    'buyer_city' => $customer->city,
                    'buyer_address' => $customer->address,
                    'total_amount' => $invoice->total_amount,
                    'shipping_fee' => 25_000,
                    'platform_fee' => 78_000,
                    'discount_amount' => 50_000,
                    'items' => $invoice->items->map(fn ($item): array => [
                        'name' => $item->description, 'quantity' => $item->quantity,
                        'unitPrice' => $item->unit_price,
                    ])->all(),
                    'ordered_at' => $invoice->issue_date->setTime(8, 30),
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'sync_status' => MarketplaceOrder::SYNC_IMPORTED,
                    'raw_payload' => ['demo' => true, 'source' => $platform->value],
                ]);
            }
        }
    }
}

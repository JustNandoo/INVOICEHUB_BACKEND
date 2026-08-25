<?php

namespace Tests\Feature\Marketplace;

use App\Enums\Marketplace\ConnectionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_verified_authentication(): void
    {
        $this->getJson('/api/v1/marketplaces')->assertUnauthorized();

        $user = User::factory()->unverified()->create();
        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/marketplaces')->assertForbidden();
    }

    public function test_the_free_plan_cannot_use_marketplace_integration(): void
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'starter');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/marketplaces')
            ->assertForbidden()
            ->assertJsonPath('error.requiredFeature', 'marketplace.integration');
    }

    public function test_the_catalog_reports_which_platforms_are_ready_to_use(): void
    {
        [$user, $token] = $this->owner();

        $response = $this->withToken($token)->getJson('/api/v1/marketplaces')->assertOk();
        $platforms = collect($response->json('data.platforms'))->keyBy('platform');

        // Impor manual selalu siap; OAuth menunggu kredensial partner.
        $this->assertTrue($platforms['manual']['configured']);
        $this->assertSame('import', $platforms['manual']['kind']);
        $this->assertFalse($platforms['shopee']['configured']);
        $this->assertSame('oauth', $platforms['shopee']['kind']);
        $this->assertNull($platforms['manual']['connection']);
        $this->assertSame($user->id, $user->id);
    }

    public function test_manual_import_connects_without_any_oauth_step(): void
    {
        [, $token] = $this->owner();

        $this->withToken($token)
            ->postJson('/api/v1/marketplaces/connect', ['platform' => 'manual'])
            ->assertCreated()
            ->assertJsonPath('data.connection.status', ConnectionStatus::Connected->value)
            ->assertJsonPath('data.authorizationUrl', null);
    }

    public function test_an_unconfigured_oauth_platform_is_refused_with_a_clear_reason(): void
    {
        [, $token] = $this->owner();

        $this->withToken($token)
            ->postJson('/api/v1/marketplaces/connect', ['platform' => 'shopee'])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'MARKETPLACE_NOT_CONFIGURED');
    }

    public function test_a_configured_platform_returns_an_authorization_url_carrying_the_state(): void
    {
        config()->set('marketplaces.platforms.shopee.partner_id', '123456');
        config()->set('marketplaces.platforms.shopee.partner_key', 'secret-key');
        [, $token] = $this->owner();

        $response = $this->withToken($token)
            ->postJson('/api/v1/marketplaces/connect', ['platform' => 'shopee'])
            ->assertCreated();

        $url = $response->json('data.authorizationUrl');
        $state = MarketplaceConnection::query()->first()->state_token;

        $this->assertStringContainsString('partner.shopeemobile.com', $url);
        $this->assertStringContainsString('sign=', $url);
        $this->assertStringContainsString(urlencode((string) $state), $url);
    }

    public function test_a_callback_with_an_unknown_state_is_rejected(): void
    {
        $this->get('/api/v1/marketplaces/callback?state=tidak-dikenal&code=abc')
            ->assertRedirectContains('marketplace_error');
    }

    public function test_a_valid_callback_stores_the_token_and_marks_the_shop_connected(): void
    {
        config()->set('marketplaces.platforms.shopee.partner_id', '123456');
        config()->set('marketplaces.platforms.shopee.partner_key', 'secret-key');
        Http::fake(['*/api/v2/auth/token/get*' => Http::response([
            'access_token' => 'token-rahasia', 'refresh_token' => 'refresh-rahasia', 'expire_in' => 14400,
        ])]);
        [, $token] = $this->owner();

        $this->withToken($token)->postJson('/api/v1/marketplaces/connect', ['platform' => 'shopee'])->assertCreated();
        $state = MarketplaceConnection::query()->first()->state_token;

        $this->get("/api/v1/marketplaces/callback?state={$state}&code=kode-otorisasi&shop_id=99887")
            ->assertRedirectContains('marketplace_connected=shopee');

        $connection = MarketplaceConnection::query()->first();
        $this->assertSame(ConnectionStatus::Connected, $connection->status);
        $this->assertSame('99887', $connection->shop_id);
        $this->assertSame('token-rahasia', $connection->access_token);
        $this->assertNull($connection->state_token);
    }

    public function test_importing_an_export_creates_customers_and_invoices(): void
    {
        [$user, $token] = $this->owner();
        $connection = $this->manualConnection($token);

        $this->withToken($token)
            ->post("/api/v1/marketplaces/{$connection}/import", ['file' => $this->ordersFile()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.import.stored', 2)
            ->assertJsonPath('data.import.imported', 2);

        $this->assertSame(2, Customer::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, Invoice::query()->where('user_id', $user->id)->count());

        $customer = Customer::query()->where('name', 'Sari Dewi')->first();
        $this->assertSame('other', $customer->source);
        $this->assertSame('6281200011122', $customer->whatsapp_normalized);
        $this->assertSame('Bandung', $customer->city);
    }

    public function test_shipping_cost_becomes_its_own_invoice_line_so_it_is_actually_billed(): void
    {
        [$user, $token] = $this->owner();
        $connection = $this->manualConnection($token);

        $this->withToken($token)->post("/api/v1/marketplaces/{$connection}/import", ['file' => $this->ordersFile()], ['Accept' => 'application/json'])->assertCreated();

        $invoice = Invoice::query()->where('user_id', $user->id)->with('items')->first();
        $shipping = $invoice->items->firstWhere('description', 'Ongkos kirim');

        $this->assertNotNull($shipping, 'Ongkos kirim harus menjadi baris invoice tersendiri.');
        $this->assertSame(20000, $shipping->unit_price);
    }

    public function test_a_repeat_buyer_is_matched_instead_of_duplicated(): void
    {
        [$user, $token] = $this->owner();
        $connection = $this->manualConnection($token);

        $content = "No. Pesanan,Tanggal,Nama Pembeli,No. Telepon,Kota,Nama Produk,Jumlah,Harga Satuan\n"
            ."ORD-1,2026-08-01,Sari Dewi,081200011122,Bandung,Kopi Arabica,1,120000\n"
            ."ORD-2,2026-08-05,Sari Dewi,081200011122,Bandung,Kopi Robusta,2,90000\n";

        $this->withToken($token)->post("/api/v1/marketplaces/{$connection}/import", [
            'file' => UploadedFile::fake()->createWithContent('pesanan.csv', $content),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(1, Customer::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, Invoice::query()->where('user_id', $user->id)->count());
    }

    public function test_re_importing_the_same_export_does_not_duplicate_anything(): void
    {
        [$user, $token] = $this->owner();
        $connection = $this->manualConnection($token);

        foreach (range(1, 2) as $ignored) {
            $this->withToken($token)->post("/api/v1/marketplaces/{$connection}/import", [
                'file' => $this->ordersFile(),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->assertSame(2, MarketplaceOrder::query()->count());
        $this->assertSame(2, Invoice::query()->where('user_id', $user->id)->count());
    }

    public function test_an_order_without_a_phone_number_is_skipped_with_a_readable_reason(): void
    {
        [$user, $token] = $this->owner();
        $connection = $this->manualConnection($token);

        $content = "No. Pesanan,Tanggal,Nama Pembeli,No. Telepon,Nama Produk,Jumlah,Harga Satuan\n"
            ."ORD-9,2026-08-01,Pembeli Anonim,,Kopi Arabica,1,120000\n";

        $this->withToken($token)->post("/api/v1/marketplaces/{$connection}/import", [
            'file' => UploadedFile::fake()->createWithContent('tanpa-telepon.csv', $content),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.import.skipped', 1)
            ->assertJsonPath('data.import.imported', 0);

        $order = MarketplaceOrder::query()->first();
        $this->assertSame(MarketplaceOrder::SYNC_SKIPPED, $order->sync_status);
        $this->assertStringContainsString('nomor telepon', $order->sync_error);
        $this->assertSame(0, Invoice::query()->where('user_id', $user->id)->count());
    }

    public function test_disconnecting_wipes_the_token_but_keeps_imported_data(): void
    {
        [$user, $token] = $this->owner();
        $connection = $this->manualConnection($token);
        $this->withToken($token)->post("/api/v1/marketplaces/{$connection}/import", ['file' => $this->ordersFile()], ['Accept' => 'application/json'])->assertCreated();

        $this->withToken($token)->deleteJson("/api/v1/marketplaces/{$connection}")
            ->assertOk()
            ->assertJsonPath('data.connection.status', ConnectionStatus::Disconnected->value);

        $this->assertNull(MarketplaceConnection::query()->find($connection)->access_token);
        $this->assertSame(2, Invoice::query()->where('user_id', $user->id)->count());
    }

    public function test_another_users_connection_is_not_reachable(): void
    {
        // Sambungan dibuat langsung lewat model supaya test ini hanya melakukan satu
        // permintaan HTTP: guard auth Laravel meng-cache pengguna antar-permintaan.
        $owner = User::factory()->create();
        $this->activatePlan($owner, 'pro');
        $connection = MarketplaceConnection::query()->create([
            'user_id' => $owner->id, 'platform' => 'manual', 'shop_name' => 'Impor Manual',
            'status' => ConnectionStatus::Connected->value,
        ]);

        $intruder = User::factory()->create();
        $this->activatePlan($intruder, 'pro');

        $this->withToken($intruder->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/marketplaces/{$connection->id}")
            ->assertNotFound();

        $this->assertSame(ConnectionStatus::Connected, $connection->refresh()->status);
    }

    public function test_orders_can_be_listed_and_filtered(): void
    {
        [, $token] = $this->owner();
        $connection = $this->manualConnection($token);
        $this->withToken($token)->post("/api/v1/marketplaces/{$connection}/import", ['file' => $this->ordersFile()], ['Accept' => 'application/json'])->assertCreated();

        $this->withToken($token)->getJson('/api/v1/marketplaces/orders?syncStatus=imported')
            ->assertOk()
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonPath('data.orders.0.syncStatus', 'imported');
    }

    /** @return array{User, string} */
    private function owner(): array
    {
        $user = User::factory()->create();
        $this->activatePlan($user, 'pro');

        return [$user, $user->createToken('test')->plainTextToken];
    }

    private function manualConnection(string $token): int
    {
        return (int) $this->withToken($token)
            ->postJson('/api/v1/marketplaces/connect', ['platform' => 'manual'])
            ->assertCreated()
            ->json('data.connection.id');
    }

    private function ordersFile(): UploadedFile
    {
        $content = "No. Pesanan,Tanggal,Nama Pembeli,No. Telepon,Kota,Alamat,Nama Produk,Jumlah,Harga Satuan,Ongkos Kirim,Biaya Admin\n"
            ."ORD-1001,2026-08-01,Sari Dewi,081200011122,Bandung,Jl. Merdeka 10,Kopi Arabica 1kg,2,120000,20000,7500\n"
            ."ORD-1002,2026-08-03,Andi Wijaya,081399922211,Jakarta,Jl. Sudirman 5,Kopi Robusta 1kg,1,95000,15000,5000\n";

        return UploadedFile::fake()->createWithContent('pesanan-marketplace.csv', $content);
    }
}
